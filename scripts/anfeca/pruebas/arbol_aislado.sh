#!/usr/bin/env bash
# =============================================================================
# ANFECA · Árbol de pruebas VERDADERAMENTE aislado (medida correctiva del incidente 2026-11-25).
#
#   bash scripts/anfeca/pruebas/arbol_aislado.sh <destino> [arbol]
#
#   <destino>  directorio NUEVO fuera de /var/www (por ejemplo, en /tmp o en el directorio temporal de la sesión).
#   [arbol]    árbol o commit a probar (por omisión, el índice actual: git write-tree).
#
# - Extrae el árbol con git archive, COPIA vendor y node_modules (nunca enlaces simbólicos).
# - .env de pruebas propio; sin credenciales del sitio.
# - Comprobaciones previas: sin enlaces simbólicos, raíz de Laravel = destino, configuración
#   de phpunit.xml con SQLite en memoria. Si alguna falla, se detiene.
# =============================================================================
set -euo pipefail

DESTINO="${1:?Indique el directorio de destino}"
RAIZ=$(git rev-parse --show-toplevel)
ARBOL="${2:-$(git -C "$RAIZ" write-tree)}"

case "$(realpath -m "$DESTINO")" in
    /var/www/*) echo "SEGURIDAD: el destino no puede estar en /var/www" >&2; exit 1 ;;
esac
[ -e "$DESTINO" ] && { echo "El destino ya existe: $DESTINO" >&2; exit 1; }
DISPONIBLE=$(df -Pm "$(dirname "$(realpath -m "$DESTINO")")" | awk 'NR==2 {print $4}')
[ "$DISPONIBLE" -ge 800 ] || { echo "Espacio insuficiente (${DISPONIBLE} MB; se requieren 800 MB)" >&2; exit 1; }

mkdir -p "$DESTINO"
git -C "$RAIZ" archive "$ARBOL" | tar -x -C "$DESTINO"
cp -a "$RAIZ/vendor" "$DESTINO/vendor"
[ -d "$RAIZ/node_modules" ] && cp -a "$RAIZ/node_modules" "$DESTINO/node_modules"
mkdir -p "$DESTINO"/storage/{app/public,app/private,framework/{cache/data,sessions,testing,views},logs} "$DESTINO/bootstrap/cache"
cp "$DESTINO/.env.example" "$DESTINO/.env"
sed -i 's/^APP_ENV=.*/APP_ENV=testing/' "$DESTINO/.env"
# La suite PDM y la de revisión en PostgreSQL solo se ejecutan contra la base desechable.
sed -i 's#<env name="DB_DATABASE" value="bd_anfeca_pruebas_pg" force="true"/>#<env name="DB_DATABASE" value="bd_anfeca_desechable_pruebas_pg" force="true"/>#' "$DESTINO/phpunit.pgsql.xml"
chmod -R a+rwX "$DESTINO/storage" "$DESTINO/bootstrap/cache"

# --- Comprobaciones previas --------------------------------------------------
ENLACES=$(find "$DESTINO" -maxdepth 2 -type l | wc -l)
[ "$ENLACES" -eq 0 ] || { echo "SEGURIDAD: hay enlaces simbólicos en el árbol aislado" >&2; exit 1; }
cd "$DESTINO"
php artisan key:generate --force -q
BASE=$(php -r 'require "vendor/autoload.php"; $a = require "bootstrap/app.php"; echo realpath($a->basePath());')
[ "$BASE" = "$(realpath "$DESTINO")" ] || { echo "SEGURIDAD: Laravel resuelve la raíz en $BASE" >&2; exit 1; }
grep -q '<env name="DB_DATABASE" value=":memory:" force="true"/>' phpunit.xml || { echo "SEGURIDAD: phpunit.xml no fuerza SQLite en memoria" >&2; exit 1; }
grep -q '<env name="DB_CONNECTION" value="sqlite" force="true"/>' phpunit.xml || { echo "SEGURIDAD: phpunit.xml no fuerza SQLite" >&2; exit 1; }
echo "Árbol aislado listo: $DESTINO (árbol $ARBOL, raíz Laravel verificada)"
