#!/bin/bash
# BLOQUE 3 · PREPARACIÓN DEL CÓDIGO en un directorio NUEVO ($PREPARACION). No modifica $REPO ni $APP.
# Uso: bash 03_preparar_codigo.sh      (REVISADO=si solo si las diferencias del bloque 1 ya se revisaron y protegieron)
source "$(dirname "$0")/comun.inc.sh"
[ -e "$PREPARACION" ] && alto "$PREPARACION ya existe: no se sobrescribe."
ORIGEN=$(git -C "$REPO" remote get-url origin)

paso "Cambios locales en $REPO"
[ -z "$(git -C "$REPO" status --porcelain)" ] || alto "$REPO tiene modificaciones locales sin confirmar: protéjalas antes de continuar."

paso "Clonar $RAMA en $PREPARACION"
git clone --branch "$RAMA" --single-branch "$ORIGEN" "$PREPARACION" || alto "No se pudo clonar la rama."
HEAD=$(git -C "$PREPARACION" rev-parse HEAD); echo "HEAD: $HEAD"
git -C "$PREPARACION" merge-base --is-ancestor "$COMMIT_ESPERADO" HEAD || alto "La rama ($HEAD) no contiene el commit de integración $COMMIT_ESPERADO."
git -C "$PREPARACION" log --oneline "$COMMIT_ESPERADO"..HEAD | tee "$SALIDA/commits_posteriores_a_integracion.txt"   # solo scripts y documentación
echo "$HEAD" > "$PREPARACION/.commit_preparado"

paso "La rama contiene la versión del repositorio del servidor"
git -C "$PREPARACION" fetch -q "$REPO" "$(git -C "$REPO" branch --show-current)"
git -C "$PREPARACION" merge-base --is-ancestor FETCH_HEAD HEAD || alto "El repositorio del servidor tiene commits que la rama no incluye: integrar antes."

paso "Mejoras de sesión y fase 9 presentes"
for f in app/Http/Middleware/ControlInactividad.php app/Http/Controllers/SesionController.php config/anfeca_sesion.php \
         resources/views/partials/control-sesion.blade.php app/Services/Anfeca/Sepomex/ConsultaCodigoPostal.php \
         app/Services/Anfeca/Sepomex/ValidacionDomicilio.php app/Console/Commands/ImportarSepomex.php resources/js/composables/codigoPostal.js; do
    [ -f "$PREPARACION/$f" ] && echo "  ok  $f" || alto "Falta $f"
done
grep -q "ControlInactividad" "$PREPARACION/bootstrap/app.php" || alto "bootstrap/app.php no registra el control de sesión."

paso "Cambios del código desplegado posteriores a la versión del repositorio"
diff -rq --exclude=.git --exclude=vendor --exclude=node_modules --exclude=storage --exclude=.env --exclude=build \
    --exclude=bootstrap/cache "$REPO" "$APP" > "$SALIDA/diferencias.txt"
if [ -s "$SALIDA/diferencias.txt" ]; then
    cat "$SALIDA/diferencias.txt"
    mkdir -p "$SALIDA/cambios_posteriores"
    grep -oE "^Files [^ ]+ and ([^ ]+) differ|^Only in $APP[^:]*: .+" "$SALIDA/diferencias.txt" > "$SALIDA/cambios_posteriores/lista.txt"
    [ "${REVISADO:-no}" = si ] || alto "El código desplegado difiere del repositorio: revise $SALIDA/diferencias.txt, incorpórelo a la rama y repita con REVISADO=si."
fi

paso "Dependencias y compilación (Vite) en $PREPARACION"
(cd "$PREPARACION" && "$PHP" "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction) || alto "composer install falló."
(cd "$PREPARACION" && npm ci && npm run build) || alto "La compilación de Vite falló."
ls -l "$PREPARACION/public/build/manifest.json"
sudo install -m 600 -o "$(id -un)" "$APP/.env" "$PREPARACION/.env"   # misma conexión; solo para los diagnósticos del bloque 4
mkdir -p "$PREPARACION/storage/framework/"{cache/data,sessions,views} "$PREPARACION/storage/logs"
(cd "$PREPARACION" && "$PHP" artisan config:clear -q)   # sin caché: los diagnósticos leen .env y el entorno
echo; echo "Código preparado en $PREPARACION ($HEAD). Nada se ha desplegado."
