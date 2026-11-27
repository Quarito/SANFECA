#!/usr/bin/env bash
# =============================================================================
# ANFECA 8086 · Reversión (root) al estado anterior: VirtualHost original → /var/www/ANFECA_ENSAYO, pool www (PHP 8.4.27)
# y base de origen bd_anfeca_ensayo_pruebas_pg (nunca se modificó).
#
#   sudo bash scripts/anfeca/despliegue_8086/03_revertir_root.sh [/root/anfeca8086_conmutacion_<ts>]
#
# Protección de datos: si certificacion_anfeca recibió escrituras después de la conmutación (huellas distintas de
# las tomadas al conmutar), 8086 queda en MANTENIMIENTO y se listan las tablas con cambios: deben conciliarse hacia
# el origen antes de reabrir; nunca se reabre la base anterior perdiendo solicitudes nuevas.
# No borra bases, releases, respaldos ni la configuración de los servicios exclusivos (quedan disponibles).
# =============================================================================
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo "ejecutar con sudo" >&2; exit 1; }
R="${1:-$(cat /root/anfeca8086_ultima_conmutacion)}"; [ -s "$R/anfeca-ensayo.conf.anterior" ] || { echo "sin respaldo del VirtualHost en $R" >&2; exit 1; }
DIR=$(cd "$(dirname "$0")" && pwd); source "$DIR/../huellas_bd.inc.sh"
APP=/var/www/ANFECA_ENSAYO; VH=/etc/httpd/conf.d/anfeca-ensayo.conf; SW() { sudo -u servidorweb "$@"; }

echo "== 1. Mantenimiento y VirtualHost original"
SW php "$APP/artisan" down --retry=60 >/dev/null || true
cp -a "$R/anfeca-ensayo.conf.anterior" "$VH"
apachectl configtest 2>&1 | grep -q "Syntax OK" || { echo "configtest falló con el VirtualHost original: revisar $VH" >&2; exit 1; }
systemctl reload httpd; sleep 2
cmp -s "$R/env_anterior" "$APP/.env" || { echo "El .env de $APP difiere del guardado; se conserva el actual y se informa." >&2; }

echo "== 2. Escrituras posteriores en certificacion_anfeca"
export PGHOST=127.0.0.1 PGPORT=5433 PGUSER=cert8086_propietario PGDATABASE=certificacion_anfeca
export PGPASSWORD=$(awk -F'\t' '$1=="cert8086_propietario"{print $2}' /home/servidorweb/ANFECA_CREDENCIALES/pg8086_certificacion_anfeca.txt)
PATH=/usr/pgsql-18/bin:$PATH huellas_bd_instantanea psql > "$R/huellas_al_revertir.txt"; unset PGPASSWORD
if [ -s "$R/huellas_al_conmutar.txt" ] && ! cmp -s "$R/huellas_al_conmutar.txt" "$R/huellas_al_revertir.txt"; then
    diff "$R/huellas_al_conmutar.txt" "$R/huellas_al_revertir.txt" | grep '^>' | cut -d'|' -f1-2 | tee "$R/tablas_con_escrituras_nuevas.txt"
    echo "ATENCIÓN: hay escrituras nuevas en certificacion_anfeca. 8086 queda en MANTENIMIENTO con el VirtualHost original." >&2
    echo "Conciliarlas hacia bd_anfeca_ensayo_pruebas_pg y después: sudo -u servidorweb php $APP/artisan up" >&2
    exit 2
fi
SW php "$APP/artisan" up >/dev/null
echo "✔ 8086 revertido: $(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8086/login) en /login con PHP $(php -r 'echo PHP_VERSION;') (pool www) y la base de origen."
echo "  Servicios exclusivos (anfeca-pg8086, anfeca8086-fpm) y release conservados para un nuevo intento."
