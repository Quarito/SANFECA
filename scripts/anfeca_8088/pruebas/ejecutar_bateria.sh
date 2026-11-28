#!/bin/bash
# =====================================================================================================
# ANFECA · Batería completa de aceptación (88 verificaciones) contra una COPIA DE PRUEBA de la aplicación conectada a
# una BASE DE PRUEBA limpia (nunca la base institucional). Solo datos ficticios.
#
#   e2e_8088.py (30) · e2e_catalogos_8088.py (4) · prueba_codigo_postal_navegador.cjs (12)
#   prueba_control_sesion.py (22) · prueba_control_sesion_navegador.cjs (13) · + verificación del esquema
#
# Variables obligatorias:
#   COPIA      directorio de la copia de prueba (su .env apunta a la base de prueba; se respalda y se restaura)
#   URL        URL de la copia de prueba (p. ej. http://127.0.0.1:8189 servida con php -S solo en localhost)
#   PGHOST PGPORT PGDATABASE PGUSER PGPASSWORD   conexión a la BASE DE PRUEBA
#   CREDENCIALES  archivo con las cuentas de coordinación ficticias (anfeca:coordinador-alta --credenciales)
#   SALIDA     directorio de resultados
# Opcionales: PHP (php), PSQL (psql), CATALOGO_LIC_VERSION, CATALOGO_POS_VERSION
# =====================================================================================================
set -uo pipefail
: "${COPIA:?}" "${URL:?}" "${PGDATABASE:?}" "${CREDENCIALES:?}" "${SALIDA:?}"
PHP="${PHP:-php}"; export PSQL="${PSQL:-psql}" ANFECA_URL="$URL"
DIR="$(cd "$(dirname "$0")" && pwd)"
mkdir -p "$SALIDA/capturas"
case "$PGDATABASE" in certificacion_anfeca|bd_*) echo "DETENIDO: use una base de PRUEBA, no «$PGDATABASE»."; exit 1;; esac
grep -q "^DB_DATABASE=$PGDATABASE\$" "$COPIA/.env" || { echo "DETENIDO: $COPIA/.env no apunta a $PGDATABASE."; exit 1; }

cp "$COPIA/.env" "$SALIDA/env.original"
restaurar() { cp "$SALIDA/env.original" "$COPIA/.env"; (cd "$COPIA" && "$PHP" artisan config:clear -q); }
trap restaurar EXIT
fijar() { sed -i "s/^$1=.*/$1=$2/" "$COPIA/.env"; grep -q "^$1=" "$COPIA/.env" || echo "$1=$2" >> "$COPIA/.env"; }
limpiar() { (cd "$COPIA" && "$PHP" artisan config:clear -q && "$PHP" artisan cache:clear -q); }   # caché solo de la copia
NODE_PATH="${NODE_PATH:-$(npm root -g)}"; export NODE_PATH

COORD=$(python3 -c "
import json;l=dict(x.split('\t') for x in open('$CREDENCIALES').read().split('\n') if x)
print(json.dumps({k:[c,l[c]] for k,c in [('nacional','coord.nacional@example.test'),('zona7','coord.zona7@example.test'),('zona6','coord.zona6@example.test')]}))")

echo "== 0. Esquema"; (cd "$COPIA" && "$PHP" artisan anfeca:verificar-esquema) | tee "$SALIDA/00_esquema.txt" | tail -1

echo "== 1. Flujo completo (120 min, catálogos ANUIES apagados)"
fijar SESSION_LIFETIME 120; fijar ANFECA_CATALOGO_LICENCIATURAS false; fijar ANFECA_CATALOGO_POSGRADOS false; fijar ANFECA_DECLARACION_POSGRADO false; limpiar
ANFECA_COORDINACION="$COORD" ANFECA_LOG="$COPIA/storage/logs/laravel*.log" ANFECA_E2E_SALIDA="$SALIDA/01_e2e.json" \
    python3 "$DIR/e2e_8088.py" > "$SALIDA/01_e2e.txt" 2>&1; tail -1 "$SALIDA/01_e2e.txt"

echo "== 2. Catálogos ANUIES (banderas de 8086)"
fijar ANFECA_CATALOGO_LICENCIATURAS true; fijar ANFECA_CATALOGO_POSGRADOS true; fijar ANFECA_DECLARACION_POSGRADO true
[ -n "${CATALOGO_LIC_VERSION:-}" ] && fijar ANFECA_CATALOGO_LICENCIATURAS_VERSION "$CATALOGO_LIC_VERSION"
[ -n "${CATALOGO_POS_VERSION:-}" ] && fijar ANFECA_CATALOGO_POSGRADOS_VERSION "$CATALOGO_POS_VERSION"
limpiar
python3 "$DIR/e2e_catalogos_8088.py" > "$SALIDA/02_catalogos.txt" 2>&1; tail -1 "$SALIDA/02_catalogos.txt"

echo "== 3. Domicilio y código postal (navegador)"
ANFECA_CAPTURAS="$SALIDA/capturas" node "$DIR/prueba_codigo_postal_navegador.cjs" > "$SALIDA/03_codigo_postal.txt" 2>&1; tail -1 "$SALIDA/03_codigo_postal.txt"

echo "== 4. Sesiones (duración reducida SOLO en la copia de prueba)"
fijar SESSION_LIFETIME 1; limpiar
CUENTAS=$("$PSQL" -Atc "select string_agg(u.usua_nombre||':'||s.soli_id_solicitud, ' ' order by 1) from usuario u join solicitud s on s.soli_id_persona = u.usua_id_persona where u.usua_nombre ~ '^(ana|luis)\.' and exists (select 1 from estado_solicitud e where e.esso_id_solicitud = s.soli_id_solicitud and e.esso_siglas = 'EN')")
ANA=$(echo "$CUENTAS" | tr ' ' '\n' | grep '^ana' | tail -1); LUIS=$(echo "$CUENTAS" | tr ' ' '\n' | grep '^luis' | tail -1)
ANFECA_CORREO="${ANA%%:*}" ANFECA_CLAVE='NuevaClaveFicticia#2026' ANFECA_SOLICITUD_ENVIADA="${ANA##*:}" ANFECA_SESIONES="$COPIA/storage/framework/sessions" \
    python3 "$DIR/prueba_control_sesion.py" > "$SALIDA/04_sesion_servidor.txt" 2>&1; tail -1 "$SALIDA/04_sesion_servidor.txt"
limpiar
ANFECA_CORREO="${LUIS%%:*}" ANFECA_CLAVE='NuevaClaveFicticia#2026' ANFECA_SESIONES="$COPIA/storage/framework/sessions" ANFECA_CAPTURAS="$SALIDA/capturas" \
    node "$DIR/prueba_control_sesion_navegador.cjs" > "$SALIDA/05_sesion_navegador.txt" 2>&1; tail -1 "$SALIDA/05_sesion_navegador.txt"

echo "== Totales"
grep -h "^Resultado" "$SALIDA"/0[1-5]_*.txt | awk '{split($2,a,"/"); ok+=a[1]; t+=a[2]} END {print ok"/"t" verificaciones aprobadas"}' | tee "$SALIDA/totales.txt"
grep -h "^FALLIDA" "$SALIDA"/0[1-5]_*.txt || true
