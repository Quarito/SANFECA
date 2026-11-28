#!/bin/bash
# BLOQUE 6 · DESPLIEGUE en $APP desde $PREPARACION (ya compilado en el bloque 3). Conserva .env y storage/.
# Uso: CONFIRMAR=si RESPALDO=/var/backups/anfeca8088/fase9_<fecha> sudo -E bash 06_compilar_desplegar.sh
source "$(dirname "$0")/comun.inc.sh"
exigir_confirmacion; exigir_respaldo
[ -f "$PREPARACION/public/build/manifest.json" ] || alto "Falta la compilación de Vite (bloque 3)."
[ "$(git -C "$PREPARACION" rev-parse HEAD)" = "$(cat "$PREPARACION/.commit_preparado" 2>/dev/null)" ] || alto "$PREPARACION cambió después del bloque 3."
git -C "$PREPARACION" merge-base --is-ancestor "$COMMIT_ESPERADO" HEAD || alto "$PREPARACION no contiene $COMMIT_ESPERADO."
[ -z "$(git -C "$PREPARACION" status --porcelain --untracked-files=no)" ] || alto "$PREPARACION tiene archivos versionados modificados."
paso "Modo mantenimiento"; DIR_APP="$APP" artisan down --retry=60 || true
paso "Sincronización del código (se excluyen .env, storage/, .git y archivos de desarrollo)"
rsync -a --delete --exclude .git --exclude .commit_preparado --exclude .env --exclude 'storage/' --exclude node_modules --exclude 'bootstrap/cache/' \
    --exclude tests/ "$PREPARACION/" "$APP/" || { DIR_APP="$APP" artisan up; alto "rsync falló: revise y, si es necesario, use el bloque 8."; }
git -C "$PREPARACION" rev-parse HEAD > "$APP/storage/app/version_desplegada.txt"
chown -R root:"$USUARIO_WEB" "$APP" && chmod -R g+rX "$APP" && chmod -R g+rwX "$APP/storage" "$APP/bootstrap/cache"
command -v restorecon >/dev/null && restorecon -R "$APP"
paso "Caché de configuración y vistas"
DIR_APP="$APP" artisan config:clear && DIR_APP="$APP" artisan config:cache && DIR_APP="$APP" artisan view:clear && DIR_APP="$APP" artisan view:cache
paso "PHP-FPM del 8088"
if systemctl list-unit-files | grep -q '^php-fpm-anfeca8088'; then systemctl reload php-fpm-anfeca8088;
else echo "Sin servicio PHP-FPM exclusivo: no se recarga ningún servicio compartido (OPcache revalida por fecha si validate_timestamps=1)."; fi
paso "Salir de mantenimiento"; DIR_APP="$APP" artisan up
curl -s -o /dev/null -w "login → %{http_code}\n" http://127.0.0.1:8088/login
echo; echo "Desplegado $(cat "$APP/storage/app/version_desplegada.txt"). Continúe con el bloque 7."
