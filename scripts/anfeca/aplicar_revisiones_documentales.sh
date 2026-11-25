#!/usr/bin/env bash
# =============================================================================
# ANFECA · Aplicación CONTROLADA de la migración revisiones_documentales (fase 6.3.1) en el ensayo 8086.
#
#   cd /var/www/ANFECA_ENSAYO
#   ANFECA_AUTORIZO_MIGRACION=revisiones_documentales \
#     bash scripts/anfeca/aplicar_revisiones_documentales.sh <base> <commit> <directorio_respaldo_verificado>
#
# Se detiene si falla cualquier condición previa. Aplica SOLO la migración autorizada (--path), nunca
# «migrate» general, migrate:fresh, db:wipe ni reversiones. Compara huellas de TODAS las tablas antes y
# después: solo pueden cambiar «migrations» (+1 fila) y aparecer revisiones_documentales vacía.
# =============================================================================
set -euo pipefail
BASE="${1:?base}"; COMMIT="${2:?commit}"; RESPALDO="${3:?directorio del respaldo verificado}"
MIG=2026_11_26_000000_create_revisiones_documentales_table
RUTA=database/migrations/$MIG.php
detener() { echo "DETENIDO: $*" >&2; exit 1; }
valor() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
source "$(dirname "$0")/huellas_bd.inc.sh"
source "$(dirname "$0")/pg_herramientas.inc.sh"

[ "${ANFECA_AUTORIZO_MIGRACION:-}" = revisiones_documentales ] || detener "falta ANFECA_AUTORIZO_MIGRACION=revisiones_documentales"
[ "$(df -Pm / | awk 'NR==2 {print $4}')" -ge 5120 ] || detener "menos de 5 GB libres en /"
[ "$(valor DB_DATABASE)" = "$BASE" ] || detener ".env no apunta a $BASE"
[ "$(git rev-parse --abbrev-ref HEAD)" = desarrollo/modulo-coordinacion ] || detener "rama no autorizada"
[ "$(git rev-parse --short=7 HEAD)" = "${COMMIT:0:7}" ] || detener "commit distinto de $COMMIT"
[ -f "$RUTA" ] || detener "no existe $RUTA"
[ ! -f bootstrap/cache/config.php ] || detener "configuración en caché"
BASE="$BASE" php -r 'require "vendor/autoload.php"; $a = require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  exit((config("anfeca_pdm.modo") === "apagado" && config("anfeca_coordinacion.revision_documental") === false && config("database.connections.".config("database.default").".database") === getenv("BASE")) ? 0 : 1);' \
  || detener "PDM no apagado, bandera de revisión activa o conexión efectiva distinta"
grep -q "RESULTADO: respaldo restaurable y equivalente" "$RESPALDO/RESULTADO.txt" || detener "el respaldo indicado no está verificado"
grep -q "commit=${COMMIT:0:7}" "$RESPALDO/RESULTADO.txt" || detener "el respaldo no corresponde al commit $COMMIT"
[ "$(find "$RESPALDO/RESULTADO.txt" -mmin -120 | wc -l)" -eq 1 ] || detener "el respaldo tiene más de 2 horas: genere uno nuevo"
(cd "$RESPALDO" && sha256sum -c --quiet base.dump.sha256) || detener "SHA-256 del respaldo no coincide"

export PGHOST="$(valor DB_HOST)" PGPORT="$(valor DB_PORT)" PGUSER="$(valor DB_USERNAME)" PGPASSWORD="$(valor DB_PASSWORD)" PGDATABASE="$BASE"
anfeca_pg_preparar
PENDIENTES=$(php artisan migrate:status --no-ansi | grep Pending | awk '{print $1}')
grep -qx "$MIG" <<< "$PENDIENTES" || detener "la migración autorizada no está pendiente"
PRETEND=$(php artisan migrate --path="$RUTA" --pretend --force --no-ansi)
grep -q escuela_facultad <<< "$PRETEND" && detener "el ensayo en seco incluye otra migración"

SALIDA="$RESPALDO/aplicacion"; mkdir -p "$SALIDA"
huellas_bd > "$SALIDA/huellas_antes.txt"
echo "Aplicando $MIG ($(date -Is))"
php artisan migrate --path="$RUTA" --force --no-ansi | tee "$SALIDA/migrate.txt"
huellas_bd > "$SALIDA/huellas_despues.txt"

diff "$SALIDA/huellas_antes.txt" "$SALIDA/huellas_despues.txt" > "$SALIDA/diferencias.txt" || true
INESPERADAS=$( { grep -E '^[<>]' "$SALIDA/diferencias.txt" || true; } | { grep -vE '^[<>] public\.migrations\||^> public\.revisiones_documentales\|0\|' || true; } | wc -l)
echo "Diferencias de huellas:"; sed 's/^/  /' "$SALIDA/diferencias.txt"
[ "$INESPERADAS" -eq 0 ] || detener "ALERTA: cambiaron otras tablas (ver $SALIDA/diferencias.txt)"
echo "Solo cambiaron migrations (+1) y la tabla nueva vacía: correcto."
bash "$(dirname "$0")/verificar_revisiones_documentales.sh" | tee "$SALIDA/verificacion.txt"
