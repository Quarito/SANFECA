#!/bin/bash
# BLOQUE 8 · REVERSIÓN. Simula por omisión; aplica con CONFIRMAR=si. Requiere RESPALDO (bloque 2).
#  MODO=codigo  (predeterminado) restaura el código desplegado y su .env del respaldo. La base no cambia: la fase 9 no
#               modifica la estructura y el catálogo SEPOMEX solo agrega filas de catálogo, inocuas para la versión anterior.
#  MODO=datos   además restaura el volcado en una base NUEVA (<bd>_reversion_<fecha>) y apunta la aplicación a ella.
#               No borra ni sobrescribe la base actual; quedan ambas para su revisión.
# Uso: RESPALDO=/var/backups/anfeca8088/fase9_<fecha> [MODO=datos] [CONFIRMAR=si] sudo -E bash 08_reversion.sh
source "$(dirname "$0")/comun.inc.sh"
exigir_respaldo
MODO="${MODO:-codigo}"
run() { printf '    $ %s\n' "$*"; [ "${CONFIRMAR:-no}" = si ] && eval "$@"; }
[ "${CONFIRMAR:-no}" = si ] || echo "SIMULACIÓN: no se ejecuta nada (CONFIRMAR=si para aplicar)."
paso "Código y configuración de la aplicación"
run "DIR_APP='$APP' artisan down --retry=60 || true"
run "mv '$APP' '$APP.fase9_retirado_$(date +%Y%m%d%H%M%S)'"
run "tar -C '$(dirname "$APP")' -xzf '$RESPALDO/codigo_desplegado.tgz'"
run "install -m 640 -o root -g $USUARIO_WEB '$RESPALDO/env.respaldo' '$APP/.env'"
run "mkdir -p '$APP/storage/framework/'{cache/data,sessions,views} '$APP/storage/logs' && chown -R root:$USUARIO_WEB '$APP' && chmod -R g+rwX '$APP/storage' '$APP/bootstrap/cache'"
run "command -v restorecon >/dev/null && restorecon -R '$APP'"
if [ "$MODO" = datos ]; then
    paso "Datos: restauración en una base NUEVA"
    BD_REV="${BD}_reversion_$(date +%Y%m%d_%H%M%S)"
    pedir_clave
    run "admin_psql -v ON_ERROR_STOP=1 -c 'create database $BD_REV owner $ROL_APP'"
    run "'$PG_BIN/pg_restore' -U '$ROL_APP' -d '$BD_REV' --no-owner --exit-on-error '$RESPALDO/$BD.dump'"
    run "sed -i 's/^DB_DATABASE=.*/DB_DATABASE=$BD_REV/' '$APP/.env'"
fi
run "DIR_APP='$APP' artisan config:clear && DIR_APP='$APP' artisan config:cache && DIR_APP='$APP' artisan view:clear"
run "systemctl list-unit-files | grep -q '^php-fpm-anfeca8088' && systemctl reload php-fpm-anfeca8088 || true"
run "DIR_APP='$APP' artisan up"
run "curl -s -o /dev/null -w 'login → %{http_code}\n' http://127.0.0.1:8088/login"
echo; echo "El directorio retirado y, en su caso, la base anterior se conservan hasta su revisión."
