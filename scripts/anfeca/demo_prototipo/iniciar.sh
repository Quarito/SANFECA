#!/usr/bin/env bash
# =============================================================================
# ANFECA · Visualización TEMPORAL del prototipo de revisión documental (fase 6.3.0).
#
#   bash scripts/anfeca/demo_prototipo/iniciar.sh     # crea y arranca la demostración
#   bash scripts/anfeca/demo_prototipo/detener.sh     # la detiene y elimina por completo
#
# - Copia desechable del commit actual (git archive HEAD) en ~/ANFECA_DEMO_PROTOTIPO.
# - SQLite propio con datos FICTICIOS; no se conecta a PostgreSQL ni a la base de 8086.
# - Contenedor Podman escuchando SOLO en 127.0.0.1:8099. Sin cambios en Apache, firewall ni SELinux.
# - ANFECA_COORDINACION_PROTOTIPOS=true solo dentro del contenedor; el .env de 8086 no se toca.
# - Contraseña aleatoria de un solo uso en ~/ANFECA_DEMO_PROTOTIPO/CLAVE_DEMO.txt (permisos 600).
# Acceso desde otro equipo: túnel SSH  ssh -L 8099:127.0.0.1:8099 <usuario>@<servidor>
# =============================================================================
set -euo pipefail

RAIZ=$(git rev-parse --show-toplevel)
DEMO="$HOME/ANFECA_DEMO_PROTOTIPO"
IMAGEN="${ANFECA_IMAGEN_PHP:-localhost/anfeca-php:8.5.12}"
NOMBRE=anfeca-demo-prototipo

[ -e "$DEMO" ] && { echo "Ya existe $DEMO. Ejecute detener.sh antes de iniciar otra demostración." >&2; exit 1; }
podman container exists "$NOMBRE" && { echo "El contenedor $NOMBRE ya existe. Ejecute detener.sh." >&2; exit 1; }
[ -f "$RAIZ/public/build/manifest.json" ] || { echo "Falta public/build: ejecute npm run build." >&2; exit 1; }
DISPONIBLE=$(df -Pm "$HOME" | awk 'NR==2 {print $4}')
[ "$DISPONIBLE" -ge 600 ] || { echo "Espacio insuficiente (${DISPONIBLE} MB; se requieren 600 MB)." >&2; exit 1; }

umask 077
mkdir -p "$DEMO"
git -C "$RAIZ" archive HEAD | tar -x -C "$DEMO"
cp -a "$RAIZ/vendor" "$DEMO/vendor"
mkdir -p "$DEMO/public/build" && cp -a "$RAIZ/public/build/." "$DEMO/public/build/"
mkdir -p "$DEMO"/storage/{app/public,app/private,framework/{cache/data,sessions,views},logs} "$DEMO/bootstrap/cache"
touch "$DEMO/storage/demo.sqlite"

CLAVE=$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-20)
printf 'Demostración del prototipo (datos ficticios)\nURL: http://127.0.0.1:8099/login\nCuentas: demo.nacional@anfeca.test, demo.zona@anfeca.test (coordinación); demo.solicitante1@example.test (debe recibir 403)\nContraseña de un solo uso: %s\n' "$CLAVE" > "$DEMO/CLAVE_DEMO.txt"
chmod 600 "$DEMO/CLAVE_DEMO.txt"

cp "$DEMO/.env.example" "$DEMO/.env"
sed -i -e 's/^APP_ENV=.*/APP_ENV=production/' -e 's/^APP_DEBUG=.*/APP_DEBUG=false/' \
       -e 's#^APP_URL=.*#APP_URL=http://127.0.0.1:8099#' -e 's/^SESSION_DRIVER=.*/SESSION_DRIVER=file/' "$DEMO/.env"
chmod -R a+rwX "$DEMO/storage" "$DEMO/bootstrap/cache"

ENTORNO=(-e DB_CONNECTION=sqlite -e DB_DATABASE=/app/storage/demo.sqlite -e CACHE_STORE=file
         -e ANFECA_PDM_MODO=apagado -e ANFECA_COORDINACION_PROTOTIPOS=true)
podman run --rm --network=none -v "$DEMO:/app:Z" -w /app "${ENTORNO[@]}" "$IMAGEN" php artisan key:generate --force -q
podman run --rm --network=none -v "$DEMO:/app:Z" -w /app "${ENTORNO[@]}" -e DEMO_CLAVE="$CLAVE" "$IMAGEN" php scripts/anfeca/demo_prototipo/sembrar.php
podman run -d --name "$NOMBRE" -p 127.0.0.1:8099:8099 -v "$DEMO:/app:Z" -w /app/public "${ENTORNO[@]}" "$IMAGEN" \
    php -S 0.0.0.0:8099 /app/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php >/dev/null

for _ in $(seq 1 20); do curl -fs -o /dev/null http://127.0.0.1:8099/login && break; sleep 1; done
curl -fs -o /dev/null http://127.0.0.1:8099/login || { echo "La demostración no respondió; revise: podman logs $NOMBRE" >&2; exit 1; }
echo "Demostración activa en http://127.0.0.1:8099 (solo local). Prototipo: /coordinacion/prototipos/revision-documental"
echo "Credenciales de un solo uso: $DEMO/CLAVE_DEMO.txt"
