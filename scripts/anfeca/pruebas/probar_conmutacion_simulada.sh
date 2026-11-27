#!/usr/bin/env bash
# =============================================================================
# ANFECA 8086 · Ensayo SIN privilegios de la recuperación de 02_conmutar_root.sh y 03_revertir_root.sh.
#
#   bash scripts/anfeca/pruebas/probar_conmutacion_simulada.sh <directorio_nuevo>
#
# Ejecuta los scripts REALES en modo ANFECA_SIMULACION=1 sobre una caja de arena (VirtualHost, aplicación y evidencia
# ficticios; las rutas reales se rechazan) con dobles de systemctl, apachectl, podman, curl, ss, respaldo y
# sincronización. 03 lee en solo lectura las huellas reales de certificacion_anfeca (127.0.0.1:5433).
# Escenarios: falla del respaldo, falla de la sincronización, falla tras cambiar el VirtualHost, éxito, reejecución
# tras el éxito, reversión con escrituras nuevas (permanece en mantenimiento) y reversión sin escrituras.
# =============================================================================
set -uo pipefail
T="${1:?directorio nuevo}"; [ ! -e "$T" ] || { echo "ya existe $T" >&2; exit 1; }
D=$(cd "$(dirname "$0")/../despliegue_8086" && pwd); mkdir -p "$T"/{bin,scr,evid,app/storage/framework,app/storage/app/private/anfeca,app/storage/logs,rel/current/public}
T=$(cd "$T" && pwd)
printf '<VirtualHost *:8086>\n    DocumentRoot /var/www/ANFECA_ENSAYO/public\n</VirtualHost>\n' > "$T/vhost.conf"; cp "$T/vhost.conf" "$T/vhost.original"
printf 'DB_HOST=127.0.0.1\nDB_PORT=5432\nDB_USERNAME=x\nDB_PASSWORD=x\n' > "$T/app/.env"; echo 'ANFECA_PDM_MODO=apagado' > "$T/rel/current/.env"
echo doc > "$T/app/storage/app/private/anfeca/doc1"; : > "$T/app/storage/logs/laravel.log"
python3 -c "import socket,sys; s=socket.socket(socket.AF_UNIX); s.bind(sys.argv[1])" "$T/fpm.sock"
# ---- dobles
cat > "$T/bin/php" <<EOF
#!/bin/bash
case "\$*" in *artisan\ down*) touch "$T/app/storage/framework/down";; *artisan\ up*) rm -f "$T/app/storage/framework/down";; esac
EOF
cat > "$T/bin/systemctl" <<'EOF'
#!/bin/bash
echo "systemctl $*" >> "$SIM_LOG"; E="$SIM_LOG.detenido"
case "$1" in stop) touch "$E";; start|restart) rm -f "$E";; is-active) [ ! -e "$E" ]; exit;; esac; exit 0
EOF
cat > "$T/bin/apachectl" <<'EOF'
#!/bin/bash
echo "Syntax OK"
EOF
cat > "$T/bin/podman" <<'EOF'
#!/bin/bash
case "$*" in *cgi-fcgi*) echo "${SIM_IDENTIDAD:-8.5.12 fpm-fcgi 13.42.0 certificacion_anfeca cert8086_app 5433 18.6 apagado}";; esac; exit 0
EOF
cat > "$T/bin/curl" <<'EOF'
#!/bin/bash
for a in "$@"; do case "$a" in *__identidad_*) echo "8.5.12 fpm-fcgi"; exit 0;; esac; done
for a in "$@"; do [ "$a" = "%{http_code}" ] && { echo 200; exit 0; }; done; exit 0
EOF
cat > "$T/bin/ss" <<'EOF'
#!/bin/bash
exit 0
EOF
cat > "$T/bin/psql_origen" <<'EOF'
#!/bin/bash
echo 0
EOF
cat > "$T/scr/respaldo_verificado.sh" <<EOF
#!/bin/bash
[ "\${SIM_FALLA:-}" = respaldo ] && { echo "pg_dump falló" >&2; exit 1; }
mkdir -p "$T/resp"; cp "$T/huellas_origen.txt" "$T/resp/huellas_origen.txt"; touch "$T/resp/base.dump"
echo "RESULTADO: respaldo restaurable y equivalente. Archivo: $T/resp/base.dump"
EOF
cat > "$T/scr/huellas_documentos.sh" <<EOF
#!/bin/bash
# Defecto 2 del despliegue: servidorweb no puede escribir en la evidencia de root.
case "\$2" in "$T/evid"*) echo "PermissionError: \$2" >&2; exit 13;; esac
echo '{}' > "\$2"
EOF
cat > "$T/sincronizar.sh" <<'EOF'
#!/bin/bash
# Defecto 1 del despliegue: sudo reinicia el entorno; el socket del servicio debe llegar explícitamente.
case "${PG8086_ADMIN:-}" in *"-h /run/anfeca-pg8086 "*) ;; *) echo "socket incorrecto: '${PG8086_ADMIN:-}'" >&2; exit 2;; esac
[ "${SIM_FALLA:-}" = sincronizacion ] && { echo "huellas distintas" >&2; exit 1; }; echo "sincronizada"
EOF
chmod +x "$T"/bin/* "$T"/scr/*.sh "$T/sincronizar.sh"
# huellas reales (solo lectura) de certificacion_anfeca, como «al conmutar»
source "$D/../huellas_bd.inc.sh"
PGPASSWORD=$(awk -F'\t' '$1=="cert8086_propietario"{print $2}' ~/ANFECA_CREDENCIALES/pg8086_certificacion_anfeca.txt) PGHOST=127.0.0.1 PGPORT=5433 PGUSER=cert8086_propietario PGDATABASE=certificacion_anfeca \
    PATH=/usr/pgsql-18/bin:$PATH huellas_bd_instantanea psql > "$T/huellas_origen.txt"

export ANFECA_SIMULACION=1 ANFECA_APP="$T/app" ANFECA_REL_RAIZ="$T/rel" ANFECA_VHOST="$T/vhost.conf" ANFECA_EVIDENCIA="$T/evid" \
       ANFECA_FPM_SOCKET="$T/fpm.sock" ANFECA_SCR="$T/scr" ANFECA_SINCRONIZAR="$T/sincronizar.sh" ANFECA_PSQL="$T/bin/psql_origen" \
       ANFECA_AUTORIZO_CONMUTACION=si SIM_LOG="$T/systemctl.log" PATH="$T/bin:$PATH"
abierto() { [ ! -e "$T/app/storage/framework/down" ]; }
conmutado() { grep -q ANFECA_8086 "$T/vhost.conf"; }
R=0; caso() { local n=$1 esperado=$2 obtenido=$3; if [ "$esperado" = "$obtenido" ]; then echo "ok    $n"; else echo "FALLA $n (esperado $esperado, obtenido $obtenido)"; R=1; fi; }

SIM_FALLA=respaldo bash "$D/02_conmutar_root.sh" > "$T/s1.log" 2>&1; rc=$?
caso "1. falla del respaldo → reabierto, VirtualHost intacto" "1 abierto original" "$rc $(abierto && echo abierto || echo mantenimiento) $(cmp -s "$T/vhost.conf" "$T/vhost.original" && echo original || echo modificado)"
SIM_FALLA=sincronizacion bash "$D/02_conmutar_root.sh" > "$T/s2.log" 2>&1; rc=$?
caso "2. falla de la sincronización → reabierto, VirtualHost intacto, pool reiniciado" "1 abierto original si" "$rc $(abierto && echo abierto || echo mantenimiento) $(cmp -s "$T/vhost.conf" "$T/vhost.original" && echo original || echo modificado) $(grep -q 'start anfeca8086-fpm' "$T/systemctl.log" && echo si || echo no)"
SIM_IDENTIDAD="8.4.27 fpm-fcgi 13.42.0 bd_anfeca_ensayo_pruebas_pg x 5432 18.6 apagado" bash "$D/02_conmutar_root.sh" > "$T/s3.log" 2>&1; rc=$?
caso "3. identidad incorrecta tras cambiar el VirtualHost → revertido y reabierto" "1 abierto original" "$rc $(abierto && echo abierto || echo mantenimiento) $(cmp -s "$T/vhost.conf" "$T/vhost.original" && echo original || echo modificado)"
bash "$D/02_conmutar_root.sh" > "$T/s4.log" 2>&1; rc=$?
caso "4. conmutación correcta" "0 abierto conmutado" "$rc $(abierto && echo abierto || echo mantenimiento) $(conmutado && echo conmutado || echo original)"
cp "$T/vhost.conf" "$T/vhost.conmutado"; bash "$D/02_conmutar_root.sh" > "$T/s5.log" 2>&1; rc=$?
caso "5. reejecución tras el éxito → se detiene sin cambios" "1 igual" "$rc $(cmp -s "$T/vhost.conf" "$T/vhost.conmutado" && echo igual || echo distinto)"
U=$(cat "$T/evid/anfeca8086_ultima_conmutacion"); cp "$U/huellas_al_conmutar.txt" "$T/huellas_reales.txt"
sed -i '1s/|[0-9]*|/|999|/' "$U/huellas_al_conmutar.txt"    # simula escrituras posteriores
bash "$D/03_revertir_root.sh" "$U" > "$T/s6.log" 2>&1; rc=$?
caso "6. reversión con escrituras nuevas → mantenimiento, VirtualHost original, tablas listadas" "2 mantenimiento original si" "$rc $(abierto && echo abierto || echo mantenimiento) $(cmp -s "$T/vhost.conf" "$T/vhost.original" && echo original || echo modificado) $([ -s "$U/tablas_con_escrituras_nuevas.txt" ] && echo si || echo no)"
cp "$T/huellas_reales.txt" "$U/huellas_al_conmutar.txt"; bash "$D/03_revertir_root.sh" "$U" > "$T/s7.log" 2>&1; rc=$?
caso "7. reversión sin escrituras (reejecución) → reabierto con el VirtualHost original" "0 abierto original" "$rc $(abierto && echo abierto || echo mantenimiento) $(cmp -s "$T/vhost.conf" "$T/vhost.original" && echo original || echo modificado)"
caso "9. sin directorios temporales de evidencia remanentes" "0" "$(find /tmp -maxdepth 1 -name 'anfeca8086_docs.*' -user "$(id -u)" -newer "$T/vhost.original" 2>/dev/null | wc -l)"
D_SIN=$(sed -n 's/^ADMIN=(\${PG8086_ADMIN:-\(.*\)})$/\1/p' "$D/sincronizar_bd.sh"); D_SERV=$(grep -o "unix_socket_directories = '[^']*'" "$D/01_preparar_servicios_root.sh" | cut -d"'" -f2)
caso "10. socket por omisión de sincronizar_bd.sh = socket del servicio" "si" "$(echo "$D_SIN" | grep -q -- "-h $D_SERV " && echo si || echo "no ($D_SIN / $D_SERV)")"
grep -rhiE 'password|contraseña=' "$T"/s*.log "$T"/evid/*/bitacora.txt 2>/dev/null | grep -v 'password@-' | head -3 && { echo "FALLA bitácoras con contraseñas"; R=1; } || echo "ok    8. bitácoras sin contraseñas"
exit $R
