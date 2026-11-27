#!/usr/bin/env bash
# =============================================================================
# ANFECA 8086 · Conmutación (root) a PHP-FPM 8.5.12 + certificacion_anfeca. Requiere 01_preparar_servicios_root.sh.
#
#   sudo ANFECA_AUTORIZO_CONMUTACION=si bash scripts/anfeca/despliegue_8086/02_conmutar_root.sh
#
# Mantenimiento: «artisan down/up» se ejecuta como root porque storage/framework es root:root 755 (el archivo
# «down» queda 644, legible por Apache y por ambos pools); no se cambian permisos.
# Recuperación automática DESDE EL INICIO DEL MANTENIMIENTO (trap ERR/INT/TERM):
#   - falla antes de cambiar el VirtualHost (respaldo, sincronización, caché) → se reabre 8086 con su configuración
#     anterior (PHP 8.4.27 + bd_anfeca_ensayo_pruebas_pg, que no se modificó) y el pool exclusivo vuelve a iniciar;
#   - falla después de cambiar el VirtualHost → 03_revertir_root.sh (preserva escrituras nuevas: si las hay, queda
#     en mantenimiento y las enumera para conciliarlas).
# Reejecución segura: si 8086 ya está conmutado, se detiene sin tocar nada. El VirtualHost y el .env previos a la
# PRIMERA conmutación se guardan una sola vez en /root/anfeca8086_original. Bitácora sin contraseñas.
# PDM permanece «apagado». No toca el pool www, PHP 8.4.27, el clúster 5432, la base de origen ni 8008/8084/8085.
# =============================================================================
set -Eeuo pipefail
DIR=$(cd "$(dirname "$0")" && pwd); SCR=${ANFECA_SCR:-$(cd "$DIR/.." && pwd)}
source "$DIR/comun.inc.sh"
[ "${ANFECA_AUTORIZO_CONMUTACION:-}" = si ] || fallo "defina ANFECA_AUTORIZO_CONMUTACION=si solo con la autorización de la ventana"
TS=$(date +%Y%m%d_%H%M%S); R=$EVID/anfeca8086_conmutacion_$TS
export PG8086_ADMIN="${PG8086_ADMIN:-/usr/pgsql-18/bin/psql -h /run/anfeca-pg8086 -p 5433 -U servidorweb}"
paso() { echo; echo "== $*" | tee -a "$R/bitacora.txt"; }
huellas_docs() { (cd "$APP/storage/app/private" && find anfeca -type f -print0 | sort -z | xargs -0 -r sha256sum) > "$1"; }
FASE=previa; SONDA=""

recuperar() {
    local rc=$?; trap - ERR INT TERM; set +e
    [ -n "$SONDA" ] && rm -f "$SONDA"
    echo "FALLA en la fase «$FASE» (código $rc): recuperación automática" | tee -a "$R/bitacora.txt" >&2
    if vhost_conmutado; then
        bash "$DIR/03_revertir_root.sh" "$R"
    else
        systemctl is-active --quiet anfeca8086-fpm || systemctl start anfeca8086-fpm
        php "$APP/artisan" up >/dev/null 2>&1
        echo "8086 reabierto con su configuración anterior (VirtualHost sin cambios; base de origen intacta)." | tee -a "$R/bitacora.txt" >&2
    fi
    exit 1
}

mkdir -m 700 -p "$R"; paso "0. Precondiciones"
vhost_conmutado && fallo "8086 ya está conmutado al release (sin cambios). Para volver: 03_revertir_root.sh"
systemctl is-active --quiet anfeca-pg8086 anfeca8086-fpm httpd || fallo "servicios exclusivos o httpd no activos"
[ -S "$SOCK" ] || fallo "no existe el socket de PHP-FPM 8.5.12 ($SOCK)"
[ "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:18087/login)" = 200 ] || fallo "el candidato 127.0.0.1:18087 no responde 200"
grep -q '^ANFECA_PDM_MODO=apagado' "$REL/.env" || fallo "PDM no está apagado en el release"
[ "$(df -Pm "$EVID" | awk 'NR==2{print $4}')" -ge 2048 ] || fallo "menos de 2 GB libres para el respaldo final"
if [ ! -d "$ORIGINAL" ]; then mkdir -m 700 -p "$ORIGINAL"; cp -a "$VH" "$ORIGINAL/anfeca-ensayo.conf"; cp -a "$APP/.env" "$ORIGINAL/env"; fi
cp -a "$VH" "$R/anfeca-ensayo.conf.anterior"; cp -a "$APP/.env" "$R/env_anterior"
protegidos > "$R/protegidos_antes.txt"
LOG="$APP/storage/logs/laravel.log"; LOG_INICIO=$(stat -c %s "$LOG" 2>/dev/null || echo 0)
echo "$R" > "$EVID/anfeca8086_ultima_conmutacion"

trap recuperar ERR INT TERM
FASE=mantenimiento; paso "1. Mantenimiento: se detienen las escrituras de 8086"
php "$APP/artisan" down --retry=60 >/dev/null
ORIGEN_ENV() { grep -E "^$1=" "$APP/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
for i in $(seq 30); do   # sin transacciones en curso en el origen (aparte de esta consulta)
    N=$(PGPASSWORD="$(ORIGEN_ENV DB_PASSWORD)" ${ANFECA_PSQL:-/usr/pgsql-18/bin/psql} -XAt -h "$(ORIGEN_ENV DB_HOST)" -p "$(ORIGEN_ENV DB_PORT)" -U "$(ORIGEN_ENV DB_USERNAME)" -d bd_anfeca_ensayo_pruebas_pg \
        -c "select count(*) from pg_stat_activity where datname=current_database() and pid<>pg_backend_pid() and state in ('active','idle in transaction')")
    [ "$N" = 0 ] && break; sleep 2
done
[ "$N" = 0 ] || { echo "Persisten $N transacciones en el origen" >&2; false; }

FASE=respaldo; paso "2. Respaldo final verificado del origen (bd_anfeca_ensayo_pruebas_pg)"
SW bash -c "cd '$APP' && bash '$SCR/respaldo_verificado.sh' bd_anfeca_ensayo_pruebas_pg \$(git symbolic-ref --short HEAD) \$(git rev-parse --short HEAD)" > "$R/respaldo.txt" 2>&1
tail -3 "$R/respaldo.txt"
grep -q "RESULTADO: respaldo restaurable y equivalente" "$R/respaldo.txt"
RESP=$(dirname "$(grep -oP 'Archivo: \K\S+' "$R/respaldo.txt")"); [ -d "$RESP" ]
huellas_docs "$R/documentos_antes.sha256"; echo "   documentos: $(wc -l < "$R/documentos_antes.sha256")"

FASE=sincronizacion; paso "3. Sincronización de certificacion_anfeca"
systemctl stop anfeca8086-fpm
SW bash "${ANFECA_SINCRONIZAR:-$DIR/sincronizar_bd.sh}" "$RESP" definitivo > "$R/sincronizacion.txt" 2>&1; tail -2 "$R/sincronizacion.txt"
cp "$RESP/huellas_origen.txt" "$R/huellas_al_conmutar.txt"
systemctl start anfeca8086-fpm; for i in $(seq 20); do [ -S "$SOCK" ] && break; sleep 1; done; [ -S "$SOCK" ]

FASE=cache; paso "4. Caché Laravel con PHP 8.5.12"
for c in optimize:clear config:cache view:cache; do podman exec anfeca8086-fpm php "$REL/artisan" $c >/dev/null; done

FASE=virtualhost; paso "5. VirtualHost de 8086 → release y PHP-FPM 8.5.12 (socket exclusivo)"
python3 - "$VH" "$SOCK" <<'PY'
import sys
p, sock = sys.argv[1], sys.argv[2]; s = open(p).read()
s = s.replace('/var/www/ANFECA_ENSAYO/public', '/var/www/ANFECA_8086/current/public')
s = s.replace('</VirtualHost>', f'    <FilesMatch \\.php$>\n        SetHandler "proxy:unix:{sock}|fcgi://localhost"\n    </FilesMatch>\n</VirtualHost>')
open(p, 'w').write(s)
PY
apachectl configtest 2>&1 | grep -q "Syntax OK"
systemctl reload httpd; sleep 2

FASE=verificacion; paso "6. Verificaciones (FastCGI y HTTP real)"
podman exec anfeca8086-fpm sh -c "cat > /tmp/s.php <<'EOF'
<?php require '$REL/vendor/autoload.php'; \$a = require '$REL/bootstrap/app.php'; \$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\$r = Illuminate\Support\Facades\DB::selectOne('select current_database() b, current_user u, inet_server_port() p, split_part(version(),\' \',2) v');
echo PHP_VERSION, ' ', PHP_SAPI, ' ', app()->version(), ' ', \$r->b, ' ', \$r->u, ' ', \$r->p, ' ', \$r->v, ' ', config('anfeca_pdm.modo');
EOF
SCRIPT_FILENAME=/tmp/s.php REQUEST_METHOD=GET cgi-fcgi -bind -connect $SOCK | tail -1" > "$R/identidad_fastcgi.txt"
cat "$R/identidad_fastcgi.txt"; echo
grep -qE '^8\.5\.12 fpm-fcgi 13\.42\.0 certificacion_anfeca cert8086_app 5433 18\.6 apagado$' "$R/identidad_fastcgi.txt"
SONDA="$REL/public/__identidad_$(openssl rand -hex 8).php"     # sonda HTTP efímera: solo versiones, sin datos
printf '<?php echo PHP_VERSION," ",PHP_SAPI;\n' > "$SONDA"; chmod 644 "$SONDA"
php "$APP/artisan" up >/dev/null
curl -s "http://192.168.7.130:8086/$(basename "$SONDA")" > "$R/identidad_http.txt"; rm -f "$SONDA"; SONDA=""
grep -qx '8.5.12 fpm-fcgi' "$R/identidad_http.txt"
[ "$(curl -s -o /dev/null -w '%{http_code}' http://192.168.7.130:8086/login)" = 200 ]
SW bash "$SCR/huellas_documentos.sh" http://192.168.7.130:8086 "$R/documentos_http.json" >/dev/null
huellas_docs "$R/documentos_despues.sha256"; cmp -s "$R/documentos_antes.sha256" "$R/documentos_despues.sha256"
protegidos > "$R/protegidos_despues.txt"; cmp -s "$R/protegidos_antes.txt" "$R/protegidos_despues.txt"
! tail -c +"$((LOG_INICIO + 1))" "$LOG" 2>/dev/null | grep -q 'production\.\(ERROR\|CRITICAL\)'
trap - ERR INT TERM
echo; echo "✔ 8086 conmutado. FastCGI: $(cat "$R/identidad_fastcgi.txt") · HTTP: $(cat "$R/identidad_http.txt"). Evidencia: $R"
echo "  Etapa C obligatoria (como servidorweb): regresion_visual.js sobre http://192.168.7.130:8086 y comparar_regresion_visual.js"
echo "  contra /home/servidorweb/anfeca_trabajo/evidencias_regresion/visual_actual. Ante diferencias: 03_revertir_root.sh"
