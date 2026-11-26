#!/usr/bin/env bash
# =============================================================================
# ANFECA · Respaldo COMPLETO y VERIFICADO por restauración real (requisito previo a cualquier migración).
#
#   cd /var/www/ANFECA_ENSAYO
#   bash scripts/anfeca/respaldo_verificado.sh <base_esperada> <rama_esperada> <commit_esperado>
#
# 1. Comprueba espacio, nombre exacto de la base (.env), rama y commit.
# 2. pg_dump -Fc completo + SHA-256.
# 3. Restaura EFECTIVAMENTE en PostgreSQL 18.6 desechable (pod Podman SIN red, en memoria).
# 4. Compara estructura (pg_dump --schema-only), conteos y huella MD5 por tabla de todas las filas.
# 5. Prueba funcional: Laravel (migrate:status) contra la base restaurada.
# Solo LEE la base de origen. Nunca ejecuta migraciones ni escribe en ella. No imprime contraseñas.
# =============================================================================
set -euo pipefail

BASE_ESPERADA="${1:?base esperada}"; RAMA_ESPERADA="${2:?rama esperada}"; COMMIT_ESPERADO="${3:?commit esperado}"
APP=$(pwd); IMAGEN_PHP=localhost/anfeca-php:8.5.12
source "$(dirname "$0")/pg_herramientas.inc.sh"
valor() { grep -E "^$1=" "$APP/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

DISPONIBLE=$(df -Pm "$HOME" | awk 'NR==2 {print $4}')
[ "$DISPONIBLE" -ge 1024 ] || { echo "DETENIDO: espacio insuficiente (${DISPONIBLE} MB < 1024 MB)" >&2; exit 1; }
[ "$(valor DB_DATABASE)" = "$BASE_ESPERADA" ] || { echo "DETENIDO: la base de .env no es $BASE_ESPERADA" >&2; exit 1; }
[ "$(git -C "$APP" rev-parse --abbrev-ref HEAD)" = "$RAMA_ESPERADA" ] || { echo "DETENIDO: rama distinta de $RAMA_ESPERADA" >&2; exit 1; }
[ "$(git -C "$APP" rev-parse --short=7 HEAD)" = "${COMMIT_ESPERADO:0:7}" ] || { echo "DETENIDO: commit distinto de $COMMIT_ESPERADO" >&2; exit 1; }

export PGHOST="$(valor DB_HOST)" PGPORT="$(valor DB_PORT)" PGUSER="$(valor DB_USERNAME)" PGPASSWORD="$(valor DB_PASSWORD)" PGDATABASE="$BASE_ESPERADA"
anfeca_pg_preparar                     # herramientas cliente de la misma versión mayor que el servidor
IMAGEN_PG=$(anfeca_pg_imagen)          # restauración desechable con la misma versión exacta
DESTINO="${ANFECA_RESPALDOS_DIR:-$HOME/ANFECA_RESPALDOS}/respaldo_verificado_${BASE_ESPERADA}_$(date +%Y%m%d_%H%M%S)"
umask 077; mkdir -p "$DESTINO"
INFORME="$DESTINO/RESULTADO.txt"
registrar() { echo "$*" | tee -a "$INFORME"; }
registrar "Respaldo verificado · base=$BASE_ESPERADA · rama=$RAMA_ESPERADA · commit=$(git -C "$APP" rev-parse --short HEAD) · $(date -Is)"
registrar "Servidor: PostgreSQL ${ANFECA_PG_SERVIDOR} · cliente ${ANFECA_PG_BIN} · imagen ${IMAGEN_PG} · tamaño: $(psql -Atc "select pg_size_pretty(pg_database_size(current_database()))")"

# --- 2. Respaldo completo -------------------------------------------------------------------
pg_dump -Fc -f "$DESTINO/base.dump"
(cd "$DESTINO" && sha256sum base.dump > base.dump.sha256)
registrar "pg_dump -Fc: $(du -h "$DESTINO/base.dump" | cut -f1) · SHA-256 $(cut -c1-16 "$DESTINO/base.dump.sha256")…"

# Huellas de origen: conteo y MD5 de todas las filas por tabla (orden determinista).
HUELLAS_SQL="select string_agg(format('%I.%I', schemaname, relname), ' ' order by schemaname, relname) from pg_stat_user_tables"
huellas() {
    local consulta=$1; shift
    for t in $("$@" -Atc "$HUELLAS_SQL"); do
        "$@" -Atc "select \$q\$$t\$q\$, count(*), md5(coalesce(string_agg(x::text, E'\\n' order by x::text), '')) from $t x"
    done
}
huellas origen psql > "$DESTINO/huellas_origen.txt"
pg_dump --schema-only --no-owner --no-privileges | grep -vE '^(--|\\restrict|\\unrestrict)' > "$DESTINO/estructura_origen.sql"

# --- 3. Restauración real en base desechable ----------------------------------------------
POD="anfeca-restauracion-$$"; CLAVE_PG=$(openssl rand -hex 16)
podman pod create --name "$POD" --network none >/dev/null
trap 'podman pod rm -f "$POD" >/dev/null 2>&1 || true' EXIT
podman run -d --pod "$POD" --name "$POD-pg" --tmpfs /var/lib/postgresql:rw,size=800m -v "$DESTINO:/respaldo:ro,Z" \
    -e POSTGRES_USER=rol_restauracion -e POSTGRES_PASSWORD="$CLAVE_PG" -e POSTGRES_DB=bd_restaurada_desechable "$IMAGEN_PG" >/dev/null
for _ in $(seq 1 30); do podman exec "$POD-pg" pg_isready -q -U rol_restauracion && break; sleep 1; done
podman exec "$POD-pg" pg_restore --no-owner --no-privileges --exit-on-error -U rol_restauracion -d bd_restaurada_desechable /respaldo/base.dump
registrar "pg_restore en PostgreSQL 18.6 desechable (sin red): correcto"

destino_psql() { podman exec "$POD-pg" psql -U rol_restauracion -d bd_restaurada_desechable "$@"; }
huellas destino destino_psql > "$DESTINO/huellas_restaurada.txt"
podman exec "$POD-pg" pg_dump --schema-only --no-owner --no-privileges -U rol_restauracion bd_restaurada_desechable \
    | grep -vE '^(--|\\restrict|\\unrestrict)' > "$DESTINO/estructura_restaurada.sql"

# --- 4. Comparación -------------------------------------------------------------------------
# (a) Inventario EXACTO del catálogo: tablas, columnas y tipos, nulos, restricciones, índices,
#     disparadores, funciones, vistas y secuencias.
INVENTARIO_SQL="
select 'columna', table_schema, table_name, column_name, data_type, coalesce(character_maximum_length::text,''), is_nullable, coalesce(column_default,'')
  from information_schema.columns where table_schema not in ('pg_catalog','information_schema')
union all select 'restriccion', n.nspname, t.relname, c.conname, c.contype::text, '', '', '' from pg_constraint c
  join pg_class t on t.oid = c.conrelid join pg_namespace n on n.oid = t.relnamespace where n.nspname not in ('pg_catalog','information_schema')
union all select 'indice', schemaname, tablename, indexname, '', '', '', '' from pg_indexes where schemaname not in ('pg_catalog','information_schema')
union all select 'disparador', n.nspname, c.relname, tg.tgname, tg.tgenabled::text, '', '', '' from pg_trigger tg join pg_class c on c.oid = tg.tgrelid
  join pg_namespace n on n.oid = c.relnamespace where not tg.tgisinternal
union all select 'funcion', n.nspname, p.proname, pg_get_function_identity_arguments(p.oid), md5(p.prosrc), '', '', '' from pg_proc p
  join pg_namespace n on n.oid = p.pronamespace where n.nspname not in ('pg_catalog','information_schema')
union all select 'relacion', schemaname, relname, '', '', '', '', '' from pg_stat_user_tables
union all select 'vista', schemaname, viewname, '', '', '', '', '' from pg_views where schemaname not in ('pg_catalog','information_schema')
union all select 'secuencia', sequence_schema, sequence_name, '', '', '', '', '' from information_schema.sequences
order by 1,2,3,4"
psql -At -F'|' -c "$INVENTARIO_SQL" > "$DESTINO/inventario_origen.txt"
destino_psql -At -F'|' -c "$INVENTARIO_SQL" > "$DESTINO/inventario_restaurada.txt"
if diff -q "$DESTINO/inventario_origen.txt" "$DESTINO/inventario_restaurada.txt" >/dev/null; then
    registrar "Inventario del catálogo: IDÉNTICO ($(wc -l < "$DESTINO/inventario_origen.txt") elementos: $(cut -d'|' -f1 "$DESTINO/inventario_origen.txt" | sort | uniq -c | awk '{printf "%s %s, ", $1, $2}'))"
else
    registrar "Inventario del catálogo: DIFERENTE"; diff "$DESTINO/inventario_origen.txt" "$DESTINO/inventario_restaurada.txt" | head -20 | tee -a "$INFORME"; exit 1
fi
# (b) DDL completo. PostgreSQL re-escribe al restaurar la colocación de conversiones en expresiones
#     «ANY (ARRAY[...])» sin cambiar su significado; se compara literal y, si difiere, normalizado.
normalizar() { sed -E 's/::(text|character varying)(\[\])?//g; s/[()]//g; s/[[:space:]]+/ /g' "$1"; }
LITERALES=$(diff "$DESTINO/estructura_origen.sql" "$DESTINO/estructura_restaurada.sql" | grep -c '^<' || true)
if diff -q <(normalizar "$DESTINO/estructura_origen.sql") <(normalizar "$DESTINO/estructura_restaurada.sql") >/dev/null; then
    registrar "DDL: idéntico tras normalizar conversiones (${LITERALES} líneas con diferencias solo de forma en ANY(ARRAY[...]); detalle en diferencias_forma.diff)"
    diff "$DESTINO/estructura_origen.sql" "$DESTINO/estructura_restaurada.sql" > "$DESTINO/diferencias_forma.diff" || true
else
    registrar "DDL: DIFERENTE incluso normalizado"; diff <(normalizar "$DESTINO/estructura_origen.sql") <(normalizar "$DESTINO/estructura_restaurada.sql") | head -20 | tee -a "$INFORME"; exit 1
fi
if diff -q "$DESTINO/huellas_origen.txt" "$DESTINO/huellas_restaurada.txt" >/dev/null; then
    registrar "Conteos y huellas MD5 de filas: IDÉNTICOS en $(wc -l < "$DESTINO/huellas_origen.txt") tablas · $(awk -F'|' '{s+=$2} END {print s}' "$DESTINO/huellas_origen.txt") filas"
else
    registrar "Conteos/huellas: DIFERENTES"; diff "$DESTINO/huellas_origen.txt" "$DESTINO/huellas_restaurada.txt" | head -20 | tee -a "$INFORME"; exit 1
fi

# --- 5. Prueba funcional con Laravel sobre la base restaurada --------------------------------
# Copia TEMPORAL del código (nunca se monta el sitio: no se alteran sus etiquetas SELinux).
COPIA="$DESTINO/app_temporal"; mkdir -p "$COPIA"
git -C "$APP" archive HEAD | tar -x -C "$COPIA"; cp -a "$APP/vendor" "$COPIA/vendor"
mkdir -p "$COPIA"/storage/framework/{cache/data,sessions,views} "$COPIA/storage/logs" "$COPIA/bootstrap/cache"
cp "$COPIA/.env.example" "$COPIA/.env"; chmod -R a+rwX "$COPIA/storage" "$COPIA/bootstrap/cache"
podman run --rm --network none -v "$COPIA:/app:Z" -w /app "$IMAGEN_PHP" php artisan key:generate --force -q
ESTADO=$(podman run --rm --pod "$POD" -v "$COPIA:/app:Z" -w /app -e DB_CONNECTION=pgsql -e DB_HOST=127.0.0.1 -e DB_PORT=5432 \
    -e DB_DATABASE=bd_restaurada_desechable -e DB_USERNAME=rol_restauracion -e DB_PASSWORD="$CLAVE_PG" -e ANFECA_PDM_MODO=apagado \
    -e CACHE_STORE=array -e SESSION_DRIVER=array -e LOG_CHANNEL=stderr -e VIEW_COMPILED_PATH=/tmp \
    "$IMAGEN_PHP" php artisan migrate:status --no-ansi 2>&1)
EJECUTADAS=$(grep -c ' Ran' <<< "$ESTADO" || true); PENDIENTES=$(grep -c 'Pending' <<< "$ESTADO" || true)
registrar "Laravel migrate:status sobre la restauración: ${EJECUTADAS} ejecutadas, ${PENDIENTES} pendientes"
FUNCIONAL=$(destino_psql -Atc "select count(*) from users; select count(*) from solicitudes_certificacion")
registrar "Consulta funcional (usuarios, solicitudes): $(echo $FUNCIONAL)"
podman unshare rm -rf -- "$COPIA"
registrar "RESULTADO: respaldo restaurable y equivalente. Archivo: $DESTINO/base.dump"
chmod -R go-rwx "$DESTINO"
