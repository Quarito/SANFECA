#!/usr/bin/env bash
# =============================================================================
# ANFECA 8086 · Conmutación (root) a PHP-FPM 8.5.12 + certificacion_anfeca. Requiere 01_preparar_servicios_root.sh.
#
#   sudo ANFECA_AUTORIZO_CONMUTACION=si bash scripts/anfeca/despliegue_8086/02_conmutar_root.sh
#
# Ventana: mantenimiento (detiene escrituras) → respaldo final verificado del origen → huellas de documentos (disco)
# → sincronización de certificacion_anfeca por renombre → caché con PHP 8.5.12 → VirtualHost de 8086 al release y al
# pool exclusivo → verificaciones (identidad FastCGI, HTTP, documentos, logs, 8084/8085) → apertura.
# Ante cualquier falla posterior al cambio del VirtualHost se ejecuta 03_revertir_root.sh automáticamente.
# PDM permanece «apagado». No toca el pool www, PHP 8.4.27, el clúster 5432, la base de origen ni 8008/8084/8085.
# =============================================================================
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo "ejecutar con sudo" >&2; exit 1; }
[ "${ANFECA_AUTORIZO_CONMUTACION:-}" = si ] || { echo "Defina ANFECA_AUTORIZO_CONMUTACION=si solo con la autorización de la ventana" >&2; exit 1; }
DIR=$(cd "$(dirname "$0")" && pwd); SCR=$(cd "$DIR/.." && pwd)
APP=/var/www/ANFECA_ENSAYO; REL=/var/www/ANFECA_8086/current; VH=/etc/httpd/conf.d/anfeca-ensayo.conf
TS=$(date +%Y%m%d_%H%M%S); R=/root/anfeca8086_conmutacion_$TS; mkdir -m 700 -p "$R"
SW() { sudo -u servidorweb "$@"; }
export PG8086_ADMIN="/usr/pgsql-18/bin/psql -h /run/anfeca-pg8086 -p 5433 -U servidorweb"
fallo() { echo "DETENIDO: $*" >&2; exit 1; }
paso() { echo; echo "== $*" | tee -a "$R/bitacora.txt"; }
huellas_docs() { (cd "$APP/storage/app/private" && find anfeca -type f -print0 | sort -z | xargs -0 sha256sum) > "$1"; }

paso "0. Precondiciones"
systemctl is-active --quiet anfeca-pg8086 anfeca8086-fpm httpd || fallo "servicios exclusivos no activos"
[ "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:18087/login)" = 200 ] || fallo "el candidato 127.0.0.1:18087 no responde 200"
grep -q '^ANFECA_PDM_MODO=apagado' "$REL/.env" || fallo "PDM no está apagado en el release"
cp -a "$VH" "$R/anfeca-ensayo.conf.anterior"; cp -a "$APP/.env" "$R/env_anterior"
for u in 8084 8085; do echo "$u $(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:$u/)"; done > "$R/http_protegidos_antes.txt"
echo "$R" > /root/anfeca8086_ultima_conmutacion

paso "1. Mantenimiento: se detienen las escrituras de 8086"
SW php "$APP/artisan" down --retry=60 >/dev/null
sleep 5

paso "2. Respaldo final verificado del origen (bd_anfeca_ensayo_pruebas_pg)"
SW bash -c "cd $APP && bash $SCR/respaldo_verificado.sh bd_anfeca_ensayo_pruebas_pg \$(git symbolic-ref --short HEAD) \$(git rev-parse --short HEAD)" | tee "$R/respaldo.txt" | tail -3
RESP=$(grep -oP 'Archivo: \K\S+' "$R/respaldo.txt" | xargs dirname); [ -d "$RESP" ] || fallo "sin respaldo final (el sitio sigue en mantenimiento; reabrir con: sudo -u servidorweb php $APP/artisan up)"
huellas_docs "$R/documentos_antes.sha256"; echo "   documentos: $(wc -l < "$R/documentos_antes.sha256")"

paso "3. Sincronización de certificacion_anfeca"
systemctl stop anfeca8086-fpm
SW bash "$DIR/sincronizar_bd.sh" "$RESP" definitivo | tee "$R/sincronizacion.txt" | tail -2
cp "$RESP/huellas_origen.txt" "$R/huellas_al_conmutar.txt"
systemctl start anfeca8086-fpm; sleep 4

trap 'echo "FALLA: se revierte"; bash "$DIR/03_revertir_root.sh" "$R"' ERR
paso "4. Caché Laravel con PHP 8.5.12 y VirtualHost de 8086"
podman exec anfeca8086-fpm php "$REL/artisan" optimize:clear >/dev/null
podman exec anfeca8086-fpm php "$REL/artisan" config:cache >/dev/null
podman exec anfeca8086-fpm php "$REL/artisan" view:cache >/dev/null
python3 - "$VH" <<'PY'
import re, sys
p = sys.argv[1]; s = open(p).read()
s = s.replace('/var/www/ANFECA_ENSAYO/public', '/var/www/ANFECA_8086/current/public')
s = s.replace('</VirtualHost>', '    <FilesMatch \\.php$>\n        SetHandler "proxy:fcgi://127.0.0.1:19086"\n    </FilesMatch>\n</VirtualHost>')
open(p, 'w').write(s)
PY
apachectl configtest 2>&1 | grep -q "Syntax OK"
systemctl reload httpd; sleep 2

paso "5. Verificaciones"
podman exec anfeca8086-fpm sh -c "cat > /tmp/s.php <<'EOF'
<?php require '$REL/vendor/autoload.php'; \$a = require '$REL/bootstrap/app.php'; \$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\$r = Illuminate\Support\Facades\DB::selectOne('select current_database() b, inet_server_port() p');
echo PHP_VERSION, ' ', PHP_SAPI, ' ', \$r->b, ' ', \$r->p, ' ', config('anfeca_pdm.modo');
EOF
SCRIPT_FILENAME=/tmp/s.php REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:19086 | tail -1" | tee "$R/identidad.txt"; echo
grep -q '^8.5.12 fpm-fcgi certificacion_anfeca 5433 apagado$' "$R/identidad.txt"
podman exec anfeca8086-fpm php "$REL/artisan" up >/dev/null
[ "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8086/login)" = 200 ]
SW bash "$SCR/huellas_documentos.sh" http://192.168.7.130:8086 "$R/documentos_http.json" >/dev/null
huellas_docs "$R/documentos_despues.sha256"; cmp -s "$R/documentos_antes.sha256" "$R/documentos_despues.sha256"
for u in 8084 8085; do echo "$u $(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:$u/)"; done > "$R/http_protegidos_despues.txt"
cmp -s "$R/http_protegidos_antes.txt" "$R/http_protegidos_despues.txt"
! grep -q 'production\.\(ERROR\|CRITICAL\)' <(tail -n 200 "$APP/storage/logs/laravel.log" 2>/dev/null)
trap - ERR
echo; echo "✔ 8086 conmutado: $(cat "$R/identidad.txt"). Evidencia: $R"
echo "  Etapa C (no destructiva, como servidorweb), comparada con la línea base de la etapa A:"
echo "    BASE=http://192.168.7.130:8086 SALIDA=<dir> node $SCR/pruebas/regresion_visual.js"
echo "    A=/home/servidorweb/anfeca_trabajo/evidencias_regresion/visual_actual B=<dir> SALIDA=<dir_cmp> node $SCR/pruebas/comparar_regresion_visual.js"
echo "  Cualquier página distinta de la línea base (salvo fechas) es motivo para ejecutar 03_revertir_root.sh."
