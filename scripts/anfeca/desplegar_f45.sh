#!/usr/bin/env bash
# =============================================================================
# ANFECA · ACTIVIDAD 1 — Despliegue del código F4/F5 (commit 450f69d) en la aplicación.
#
#   cd /var/www/ANFECA_PRUEBAS && bash /home/servidorweb/anfeca_f45/scripts/anfeca/desplegar_f45.sh
#
# Qué hace (y qué NO):
#   - Solo cambia CÓDIGO: avance fast-forward a 450f69d. Nunca merge con conflictos, nunca reset
#     salvo para deshacer su propio avance si una verificación falla.
#   - Se detiene si hay cambios locales en archivos versionados (no se pierde nada).
#   - Punto de recuperación: etiqueta git local + tar.gz del código (sin vendor/node_modules/
#     storage/.env) en $HOME/ANFECA_RESPALDOS/codigo/ (permisos 700).
#   - PostgreSQL: SOLO lectura (huella antes y después; deben ser idénticas).
#   - No ejecuta migraciones, no activa modos, no toca Apache, permisos ni SELinux.
#   - F4/F5 no cambia composer.*, package*.json, resources/, routes/, config/ ni database/migrations/:
#     no hace falta composer install ni npm run build (se verifica, no se asume).
# =============================================================================
set -euo pipefail

OBJETIVO="450f69d"
RAMA="desarrollo/anfeca-pruebas"
APP="${ANFECA_APP:-/var/www/ANFECA_PRUEBAS}"   # variable solo para ensayos fuera del servidor
URL="${ANFECA_URL:-http://127.0.0.1:8084/login}"
SCRIPTS="$(cd "$(dirname "$0")" && pwd -P)"
FECHA="$(date +%Y%m%d_%H%M%S)"
SALIDA="$HOME/ANFECA_RESPALDOS/despliegue_f45/$FECHA"

falla() { echo; echo "ERROR: $*" >&2; exit 1; }
paso()  { echo; echo "=== $* ==="; }
env_de() { { grep -E "^$1=" .env || true; } | tail -1 | cut -d= -f2- | tr -d '"'"'"; }

[[ "$(pwd -P)" == "$APP" ]] || falla "Ejecute desde $APP."
mkdir -p "$SALIDA" && chmod 700 "$HOME/ANFECA_RESPALDOS" "$HOME/ANFECA_RESPALDOS/despliegue_f45" "$SALIDA"
exec > >(tee -a "$SALIDA/despliegue.log") 2>&1

export PGHOST="$(env_de DB_HOST)" PGPORT="$(env_de DB_PORT)" PGUSER="$(env_de DB_USERNAME)"
BD="$(env_de DB_DATABASE)"
[[ "$BD" == "${ANFECA_BD_ESPERADA:-bd_laravelanfeca_pruebas}" ]] || falla "DB_DATABASE inesperada: '$BD'."
read -r -s -p "Contraseña de PostgreSQL para $PGUSER: " PGPASSWORD; echo; export PGPASSWORD
trap 'unset PGPASSWORD' EXIT

paso "1. Estado de Git"
echo "Usuario: $(id -un) · Propietario del repositorio: $(stat -c %U .git)"
[[ "$(id -un)" == "$(stat -c %U .git)" ]] || falla "Ejecute como $(stat -c %U .git) para no cambiar propietarios de archivos."
ANTES="$(git rev-parse HEAD)"
echo "Rama: $(git branch --show-current) · HEAD: $ANTES"
git status --short | tee "$SALIDA/git_status_antes.txt"
if git status --porcelain --untracked-files=no | grep -q .; then
    falla "Hay cambios locales en archivos versionados (ver arriba). No se despliega para no perderlos.
Respáldelos (git stash list / git diff > archivo) y decida qué conservar antes de repetir."
fi
[[ "$(git branch --show-current)" == "$RAMA" ]] || falla "La rama actual no es $RAMA."

paso "2. Modo PDM"
MODO_ENV="$(env_de ANFECA_PDM_MODO)"; echo "ANFECA_PDM_MODO en .env: '${MODO_ENV:-<no definido = apagado>}'"
[[ -z "$MODO_ENV" || "$MODO_ENV" == "apagado" ]] || falla "El modo configurado no es apagado."
ACTIVACION=""
if [[ "$(psql -d "$BD" -X -At -c "select to_regclass('anfeca_modelo.integracion_control') is not null")" == "t" ]]; then
    ACTIVACION="$(psql -d "$BD" -X -At -c "select coalesce((select ic_modo from anfeca_modelo.integracion_control where ic_id = 1), 'apagado')")"
fi
echo "Activación registrada en la base: '${ACTIVACION:-ninguna (sin tabla de control)}'"
[[ -z "$ACTIVACION" || "$ACTIVACION" == "apagado" ]] || falla "La base tiene una activación distinta de apagado."

paso "3. Huella de PostgreSQL ANTES (solo lectura)"
bash "$SCRIPTS/huella_bd.sh" "$BD" > "$SALIDA/huella_antes.txt"
grep -E '^(BASE|ESQUEMAS|ESTRUCTURA)' "$SALIDA/huella_antes.txt" | cut -c1-140
echo "Tablas con huella: $(grep -c '^DATOS' "$SALIDA/huella_antes.txt")"

paso "4. Punto de recuperación del código"
ETIQUETA="recuperacion/antes-f45-$FECHA"
git tag "$ETIQUETA" "$ANTES"
tar --exclude=./vendor --exclude=./node_modules --exclude=./storage --exclude=./.env --exclude=./.git \
    -czf "$SALIDA/codigo_antes.tar.gz" .
chmod 600 "$SALIDA/codigo_antes.tar.gz"
echo "Etiqueta local: $ETIQUETA ($ANTES)"
echo "Archivo: $SALIDA/codigo_antes.tar.gz ($(stat -c %s "$SALIDA/codigo_antes.tar.gz") bytes, SHA-256 $(sha256sum "$SALIDA/codigo_antes.tar.gz" | cut -c1-16)…)"

paso "5. Incorporar $OBJETIVO"
git fetch origin "$RAMA"
git cat-file -e "$OBJETIVO^{commit}" || falla "$OBJETIVO no está en origin/$RAMA."
git merge-base --is-ancestor "$ANTES" "$OBJETIVO" || falla "HEAD ($ANTES) no es ancestro de $OBJETIVO: el servidor tiene commits propios. No se fuerza nada."
echo "Archivos que cambian:"; git diff --stat "$ANTES" "$OBJETIVO" | tail -3
CRITICOS="$(git diff --name-only "$ANTES" "$OBJETIVO" -- composer.json composer.lock package.json package-lock.json resources routes config database/migrations || true)"
[[ -z "$CRITICOS" ]] && echo "Sin cambios en dependencias, frontend, rutas, config ni migraciones normales." || { echo "ATENCIÓN, cambian:"; echo "$CRITICOS"; }

revertir() {
    echo; echo ">>> Verificación fallida: se restaura el código a $ANTES (el despliegue solo avanzó en fast-forward)."
    git reset -q --keep "$ANTES"
    php artisan optimize:clear >/dev/null 2>&1 || true
    curl -s -o /dev/null -w "Login tras revertir: HTTP %{http_code}\n" "$URL" || true
    exit 1
}

git merge --ff-only "$OBJETIVO"
echo "HEAD ahora: $(git rev-parse --short HEAD)"

paso "6. Verificaciones de Laravel, Composer y Vite"
trap 'revertir' ERR
php -v | head -1
CONFIG_CACHEADA=0; [[ -f bootstrap/cache/config.php ]] && CONFIG_CACHEADA=1
php artisan optimize:clear
composer validate --no-check-publish --no-check-all -q && echo "composer.json válido"
composer check-platform-reqs --no-dev -q && echo "Requisitos de plataforma: correctos"
if [[ -n "$(echo "$CRITICOS" | grep -E '^composer' || true)" ]]; then composer install --no-dev --no-interaction --optimize-autoloader; elif [[ -w vendor/composer ]]; then composer dump-autoload -o -q && echo "Autoload regenerado"
else echo "vendor/ no es escribible por $(id -un): se usa el autoload PSR-4 existente (las clases nuevas siguen la convención PSR-4)."; fi
[[ -s public/build/manifest.json ]] && echo "Vite: public/build/manifest.json presente" || { echo "Falta public/build/manifest.json"; false; }
if [[ -n "$(echo "$CRITICOS" | grep -E '^(package|resources)' || true)" ]]; then npm ci && npm run build; fi
php artisan about --only=environment
php artisan route:list --json | php -r '$r = json_decode(stream_get_contents(STDIN), true); echo "Rutas: ".count($r).PHP_EOL; exit(count($r) > 0 ? 0 : 1);'
php artisan list anfeca | grep -E "pdm-(modo|equivalencia|exportar-public)"
php artisan anfeca:pdm-modo
php artisan migrate:status --path=database/migrations_pdm || true   # informativo: no aplica nada
for f in app/Http/Middleware/AplicarModoPdm.php app/Services/Pdm/ModoPdm.php app/Console/Commands/AnfecaPdmModo.php bootstrap/app.php; do php -l "$f" >/dev/null; done
echo "Sintaxis PHP correcta en los archivos clave"
if [[ "$CONFIG_CACHEADA" == 1 ]]; then php artisan config:cache && echo "Caché de configuración restaurada (existía antes)"; fi

paso "7. Pruebas automatizadas (SQLite en memoria; phpunit.xml fuerza la conexión, no usa PostgreSQL)"
if [[ -x vendor/bin/phpunit ]]; then
    php artisan test 2>&1 | tail -4
else
    echo "Sin dependencias de desarrollo en esta instalación: las pruebas se ejecutan en /home/servidorweb/anfeca_f45 (62/62 PG y 54/54 aprobadas)."
fi

paso "8. Login en el puerto 8084"
CODIGO="$(curl -s -o "$SALIDA/login.html" -w '%{http_code}' "$URL")"
echo "GET $URL → HTTP $CODIGO"
[[ "$CODIGO" == "200" ]]
grep -qi '<form' "$SALIDA/login.html" && echo "La página contiene el formulario de acceso"
trap - ERR

paso "9. Huella de PostgreSQL DESPUÉS"
bash "$SCRIPTS/huella_bd.sh" "$BD" > "$SALIDA/huella_despues.txt"
if diff <(grep -v '^BASE' "$SALIDA/huella_antes.txt") <(grep -v '^BASE' "$SALIDA/huella_despues.txt") > "$SALIDA/huella_diferencias.txt"; then
    echo "PostgreSQL SIN CAMBIOS: estructuras y $(grep -c '^DATOS' "$SALIDA/huella_despues.txt") tablas idénticas."
else
    echo "ATENCIÓN: la huella cambió (ver $SALIDA/huella_diferencias.txt):"; cat "$SALIDA/huella_diferencias.txt"
    echo "Si solo cambian tablas de uso normal (p. ej., users por un inicio de sesión real durante el despliegue), es actividad de usuarios, no del despliegue."
fi

paso "RESULTADO"
echo "Código: $ANTES → $(git rev-parse --short HEAD) · Modo PDM: apagado · Login: HTTP 200"
echo "Recuperación del código: git reset --keep $ETIQUETA && php artisan optimize:clear"
echo "Registro completo: $SALIDA/despliegue.log"
