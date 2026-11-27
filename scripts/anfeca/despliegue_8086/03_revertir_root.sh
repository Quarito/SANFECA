#!/usr/bin/env bash
# =============================================================================
# ANFECA 8086 · Reversión (root): VirtualHost previo a la PRIMERA conmutación → /var/www/ANFECA_ENSAYO con el pool www
# (PHP 8.4.27) y la base de origen bd_anfeca_ensayo_pruebas_pg (nunca se modifica).
#
#   sudo bash scripts/anfeca/despliegue_8086/03_revertir_root.sh [/root/anfeca8086_conmutacion_<ts>]
#
# Protección de datos: si certificacion_anfeca recibió escrituras después de la conmutación (huellas distintas de las
# tomadas al conmutar), 8086 queda en MANTENIMIENTO con el VirtualHost original y se enumeran las tablas con cambios
# para conciliarlas hacia el origen antes de reabrir. Idempotente: puede ejecutarse de nuevo sin efectos adicionales.
# No borra bases, releases, respaldos ni los servicios exclusivos.
# =============================================================================
set -Euo pipefail
DIR=$(cd "$(dirname "$0")" && pwd); source "$DIR/comun.inc.sh"; source "$DIR/../huellas_bd.inc.sh"
R="${1:-$(cat "$EVID/anfeca8086_ultima_conmutacion" 2>/dev/null)}"; [ -d "$R" ] || fallo "sin directorio de evidencia de la conmutación"
ANTERIOR="$ORIGINAL/anfeca-ensayo.conf"; [ -s "$ANTERIOR" ] || ANTERIOR="$R/anfeca-ensayo.conf.anterior"
[ -s "$ANTERIOR" ] || fallo "no hay copia del VirtualHost original"
grep -q 'ANFECA_8086' "$ANTERIOR" && fallo "la copia $ANTERIOR ya apunta al release: no es la original"

echo "== 1. Mantenimiento y VirtualHost original"
php "$APP/artisan" down --retry=60 >/dev/null 2>&1 || true
if vhost_conmutado || ! cmp -s "$ANTERIOR" "$VH"; then cp -a "$ANTERIOR" "$VH"; fi
apachectl configtest 2>&1 | grep -q "Syntax OK" || fallo "configtest falló con el VirtualHost original: revisar $VH (8086 en mantenimiento)"
systemctl reload httpd; sleep 2
[ -s "$ORIGINAL/env" ] && ! cmp -s "$ORIGINAL/env" "$APP/.env" && echo "AVISO: $APP/.env difiere del guardado antes de la conmutación; se conserva el actual." >&2

echo "== 2. Escrituras posteriores en certificacion_anfeca"
[ -s "$R/huellas_al_conmutar.txt" ] || fallo "faltan las huellas de conmutación (8086 sigue en mantenimiento)"
if [ "${ANFECA_SIMULACION:-0}" = 1 ]; then
    huellas_bd_instantanea psql > "$R/huellas_al_revertir.txt"
else
    PGPASSWORD=$(awk -F'\t' '$1=="cert8086_propietario"{print $2}' /home/servidorweb/ANFECA_CREDENCIALES/pg8086_certificacion_anfeca.txt)
    [ -n "$PGPASSWORD" ] || fallo "faltan las credenciales para verificar huellas"
    PGHOST=127.0.0.1 PGPORT=5433 PGUSER=cert8086_propietario PGDATABASE=certificacion_anfeca \
        PGPASSWORD="$PGPASSWORD" PATH=/usr/pgsql-18/bin:$PATH huellas_bd_instantanea psql > "$R/huellas_al_revertir.txt"
    unset PGPASSWORD
fi
[ -s "$R/huellas_al_revertir.txt" ] || fallo "no se obtuvieron huellas (8086 sigue en mantenimiento)"
if ! cmp -s "$R/huellas_al_conmutar.txt" "$R/huellas_al_revertir.txt"; then
    awk -F'|' 'NR == FNR { anterior[$1] = $0; next }
        $0 != anterior[$1] { print $1 }
        { delete anterior[$1] }
        END { for (tabla in anterior) print tabla }
    ' "$R/huellas_al_conmutar.txt" "$R/huellas_al_revertir.txt" | LC_ALL=C sort | tee "$R/tablas_con_escrituras_nuevas.txt"
    echo "ATENCIÓN: las huellas cambiaron. 8086 queda en MANTENIMIENTO con el VirtualHost original." >&2
    echo "Conciliar los cambios hacia bd_anfeca_ensayo_pruebas_pg antes de reabrir 8086." >&2
    exit 2
fi
php "$APP/artisan" up >/dev/null
echo "✔ 8086 revertido: /login → $(curl -s -o /dev/null -w '%{http_code}' http://192.168.7.130:8086/login) con el pool www (PHP 8.4.27) y la base de origen."
echo "  Servicios exclusivos (anfeca-pg8086, anfeca8086-fpm) y release conservados para un nuevo intento."
