#!/usr/bin/env bash
# =============================================================================
# ANFECA · Demostración AISLADA de Formación Académica con el catálogo de posgrados.
#
#   bash scripts/anfeca/demo_catalogo/iniciar.sh     # crea y arranca (127.0.0.1:8099)
#   bash scripts/anfeca/demo_catalogo/detener.sh     # detiene y elimina todo
#
# - Árbol aislado: git archive del commit actual en ~/ANFECA_DEMO_CATALOGO (fuera de /var/www).
# - Compilación frontend PROPIA de la copia (no toca public/build de 8086).
# - SQLite desechable con usuarios FICTICIOS; la migración PROPUESTA se aplica SOLO ahí.
# - Bandera ANFECA_CATALOGO_POSGRADOS=true SOLO dentro del contenedor (el .env de 8086 no se toca).
# - Catálogo provisional completo (1794) copiado desde ~/ANFECA_FUENTES y verificado por SHA-256.
# - Sin PDM, sin revisión documental, sin PostgreSQL ni base de 8086. Sin cambios en Apache, firewall ni SELinux.
# - Contraseña aleatoria de un solo uso en ~/ANFECA_DEMO_CATALOGO/CLAVE_DEMO.txt (permisos 600).
# =============================================================================
set -euo pipefail

RAIZ=$(git -C "$(dirname "$0")" rev-parse --show-toplevel)
DEMO="$HOME/ANFECA_DEMO_CATALOGO"
IMAGEN="${ANFECA_IMAGEN_PHP:-localhost/anfeca-php:8.5.12}"
NOMBRE=anfeca-demo-catalogo
PUERTO=8099
CATALOGO="$HOME/ANFECA_FUENTES/posgrados_anuies_2025.json"
SHA_CATALOGO=ed15f4f2197bd57f779ed94ad0d53558a4a3e7bcdc31bebf614833aeb16e437c
MIGRACION=database/migrations_propuestas/2026_11_27_000000_add_titulo_especifico_to_formaciones_academicas_solicitud.php

[ -e "$DEMO" ] && { echo "Ya existe $DEMO. Ejecute detener.sh antes de iniciar otra demostración." >&2; exit 1; }
podman container exists "$NOMBRE" && { echo "El contenedor $NOMBRE ya existe. Ejecute detener.sh." >&2; exit 1; }
ss -lnt | grep -q ":$PUERTO " && { echo "El puerto $PUERTO ya está en uso (¿otra demostración activa?)." >&2; exit 1; }
[ "$(sha256sum "$CATALOGO" | cut -d' ' -f1)" = "$SHA_CATALOGO" ] || { echo "El catálogo no existe o su SHA-256 no coincide." >&2; exit 1; }
DISPONIBLE=$(df -Pm "$HOME" | awk 'NR==2 {print $4}')
[ "$DISPONIBLE" -ge 1000 ] || { echo "Espacio insuficiente (${DISPONIBLE} MB; se requieren 1000 MB)." >&2; exit 1; }
echo "Espacio libre antes: ${DISPONIBLE} MB"

umask 077
mkdir -p "$DEMO"
git -C "$RAIZ" archive HEAD | tar -x -C "$DEMO"
# Copias con reflink en XFS (comparten bloques con el original; no duplican espacio).
cp -a --reflink=auto "$RAIZ/vendor" "$DEMO/vendor"
cp -a --reflink=auto "$RAIZ/node_modules" "$DEMO/node_modules"
(cd "$DEMO" && npx vite build --logLevel warn)
rm -rf -- "$DEMO/node_modules"
mkdir -p "$DEMO"/storage/{app/public,app/private/catalogos,framework/{cache/data,sessions,views},logs} "$DEMO/bootstrap/cache"
cp "$CATALOGO" "$DEMO/storage/app/private/catalogos/posgrados_anuies_2025.json"
touch "$DEMO/storage/demo.sqlite"

CLAVE=$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-20)
cat > "$DEMO/CLAVE_DEMO.txt" <<TXT
Demostración del catálogo de posgrados (datos ficticios; solo local)
URL: http://127.0.0.1:$PUERTO/login
Usuarios (misma contraseña):
  demo.formacion@example.test  formación vacía (recomendado para probar)
  demo.guardado@example.test   título ya guardado desde el catálogo
  demo.historico@example.test  registro anterior: Maestría · Otro · «Economía»
Contraseña de un solo uso: $CLAVE
TXT
chmod 600 "$DEMO/CLAVE_DEMO.txt"

cp "$DEMO/.env.example" "$DEMO/.env"
sed -i -e 's/^APP_ENV=.*/APP_ENV=production/' -e 's/^APP_DEBUG=.*/APP_DEBUG=false/' \
       -e "s#^APP_URL=.*#APP_URL=http://127.0.0.1:$PUERTO#" -e 's/^SESSION_DRIVER=.*/SESSION_DRIVER=file/' "$DEMO/.env"
chmod -R a+rwX "$DEMO/storage" "$DEMO/bootstrap/cache"

ENTORNO=(-e DB_CONNECTION=sqlite -e DB_DATABASE=/app/storage/demo.sqlite -e CACHE_STORE=file -e ANFECA_PDM_MODO=apagado
         -e ANFECA_COORDINACION_PROTOTIPOS=false -e ANFECA_COORDINACION_REVISION=false
         -e ANFECA_CATALOGO_POSGRADOS=true -e ANFECA_CATALOGO_POSGRADOS_SHA256=$SHA_CATALOGO)
correr() { podman run --rm --network=none -v "$DEMO:/app:Z" -w /app "${ENTORNO[@]}" "$IMAGEN" "$@"; }
correr php artisan key:generate --force -q
correr php artisan migrate --force -q
correr php artisan migrate --force -q --path="$MIGRACION"          # SOLO en el SQLite desechable
podman run --rm --network=none -v "$DEMO:/app:Z" -w /app "${ENTORNO[@]}" -e DEMO_CLAVE="$CLAVE" "$IMAGEN" \
    php scripts/anfeca/demo_catalogo/sembrar.php
# El contenedor del servidor se crea al final: la etiqueta SELinux privada (:Z) queda asignada a él.
podman run -d --name "$NOMBRE" -p 127.0.0.1:$PUERTO:$PUERTO -v "$DEMO:/app:Z" -w /app/public "${ENTORNO[@]}" "$IMAGEN" \
    php -S 0.0.0.0:$PUERTO /app/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php >/dev/null

for _ in $(seq 1 20); do curl -fs -o /dev/null "http://127.0.0.1:$PUERTO/login" && break; sleep 1; done
curl -fs -o /dev/null "http://127.0.0.1:$PUERTO/login" || { echo "La demostración no respondió; revise: podman logs $NOMBRE" >&2; exit 1; }
echo "Compilación de la demostración: $(grep -o '"file": *"assets/app-[^"]*js"' "$DEMO/public/build/manifest.json")"
echo "Espacio libre después: $(df -Pm "$HOME" | awk 'NR==2 {print $4}') MB"
echo "Demostración activa en http://127.0.0.1:$PUERTO/login (solo local). Credenciales: $DEMO/CLAVE_DEMO.txt"
