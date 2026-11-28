#!/bin/bash
# =====================================================================================================================
# ANFECA 8090 · FASE 10.2 · RESPALDO DE 8088 Y COPIA INDEPENDIENTE certificacion_anfeca_8090 (restauración controlada).
# Lee 8088 (pg_dump, consultas, archivos) sin modificarlo. Escribe SOLO: el directorio de respaldo, un rol NUEVO
# (anfeca_8090) y una base NUEVA (certificacion_anfeca_8090). Se detiene si la base o el rol ya existen.
# No usa migraciones ni seeders: la copia es la restauración del volcado (36 tablas, datos, llaves, índices, secuencias).
# Uso: CONFIRMAR=si sudo -E bash 10_2_respaldo_copia.sh
# =====================================================================================================================
set -uo pipefail
APP8088="${APP8088:-/var/www/ANFECA_8088}"
PG_BIN="${PG_BIN:-/usr/pgsql-18/bin}"; PGHOST="${PGHOST:-127.0.0.1}"; PGPORT="${PGPORT:-5488}"
BD="${BD:-certificacion_anfeca}"; ROL_APP="${ROL_APP:-anfeca_8088}"
BD_8090="${BD_8090:-certificacion_anfeca_8090}"; ROL_8090="${ROL_8090:-anfeca_8090}"
PG_SOCKET="${PG_SOCKET:-}"
FECHA=$(date +%Y%m%d_%H%M%S)
RESPALDO="${RESPALDO:-/var/backups/anfeca8090/10_2_$FECHA}"
export PGHOST PGPORT
paso() { printf '\n==> %s\n' "$*"; }
alto() { printf '\nDETENIDO: %s\n' "$*" >&2; exit 1; }
[ "${CONFIRMAR:-no}" = si ] || alto "Escribe un respaldo, un rol y una base nuevos: repita con CONFIRMAR=si tras revisar 10.1."
install -d -m 700 "$RESPALDO"; exec > >(tee -a "$RESPALDO/registro.log") 2>&1
echo "Registro: $RESPALDO/registro.log"
admin() { sudo -u postgres env ${PGADMINPASSWORD:+PGPASSWORD="$PGADMINPASSWORD"} "$PG_BIN/psql" -X -p "$PGPORT" ${PG_SOCKET:+-h "$PG_SOCKET"} -v ON_ERROR_STOP=1 "$@"; }
leer() { PGOPTIONS='-c default_transaction_read_only=on' "$PG_BIN/psql" -X -U "$1" -d "$2" -At -F '|' -c "$3"; }
if [ -z "${PGPASSWORD:-}" ]; then read -rsp "Contraseña del rol $ROL_APP (8088): " PGPASSWORD; echo; export PGPASSWORD; fi
CLAVE_8088="$PGPASSWORD"
# Instantánea estructural y de datos de una base (para comparar origen y copia).
instantanea() {   # $1 rol $2 base $3 prefijo de archivos
    PGPASSWORD="$4" leer "$1" "$2" "select table_name||'|'||column_name||'|'||data_type||'|'||coalesce(character_maximum_length::text,'')||'|'||is_nullable||'|'||coalesce(column_default,'')
        from information_schema.columns where table_schema='public' order by table_name, ordinal_position" > "$RESPALDO/$3_columnas.txt"
    PGPASSWORD="$4" leer "$1" "$2" "select conname||'|'||contype::text||'|'||pg_get_constraintdef(oid) from pg_constraint where connamespace='public'::regnamespace order by 1" > "$RESPALDO/$3_restricciones.txt"
    PGPASSWORD="$4" leer "$1" "$2" "select indexname||'|'||indexdef from pg_indexes where schemaname='public' order by 1" > "$RESPALDO/$3_indices.txt"
    PGPASSWORD="$4" leer "$1" "$2" "select sequencename||'|'||coalesce(last_value::text,'') from pg_sequences where schemaname='public' order by 1" > "$RESPALDO/$3_secuencias.txt"
    PGPASSWORD="$4" leer "$1" "$2" "select table_name from information_schema.tables where table_schema='public' and table_type='BASE TABLE' order by 1" \
        | while read -r t; do echo "$t|$(PGPASSWORD="$4" leer "$1" "$2" "select count(*)||'|'||coalesce(md5(string_agg(x::text, E'\n' order by x::text)),'') from public.$t x")"; done > "$RESPALDO/$3_datos.txt"
}

paso "1. Comprobaciones previas: espacio, base y rol destino inexistentes"
TAM=$(leer "$ROL_APP" "$BD" "select pg_database_size(current_database())"); echo "Tamaño de $BD: $((TAM/1048576)) MB"
df -h "$RESPALDO" /var/lib/pgsql 2>/dev/null | sort -u
[ "$(leer "$ROL_APP" "$BD" "select count(*) from pg_database where datname='$BD_8090'")" = 0 ] || alto "La base $BD_8090 ya existe: no se reutiliza ni se sobrescribe."
[ "$(leer "$ROL_APP" "$BD" "select count(*) from pg_roles where rolname='$ROL_8090'")" = 0 ] || alto "El rol $ROL_8090 ya existe: revíselo antes de continuar."

paso "2. Instantánea de 8088 (estructura, restricciones, índices, secuencias, conteo y huella de cada tabla)"
instantanea "$ROL_APP" "$BD" origen "$CLAVE_8088"
for f in columnas restricciones indices secuencias datos; do [ -s "$RESPALDO/origen_$f.txt" ] || alto "La instantánea de $f salió vacía."; done
echo "Tablas $(wc -l < "$RESPALDO/origen_datos.txt") · atributos $(wc -l < "$RESPALDO/origen_columnas.txt") · PK $(grep -c '|p|' "$RESPALDO/origen_restricciones.txt") · FK $(grep -c '|f|' "$RESPALDO/origen_restricciones.txt") · índices $(wc -l < "$RESPALDO/origen_indices.txt") · secuencias $(wc -l < "$RESPALDO/origen_secuencias.txt")"

paso "3. Respaldo completo de $BD (pg_dump -Fc) y verificación de integridad"
"$PG_BIN/pg_dump" -U "$ROL_APP" -d "$BD" -Fc -f "$RESPALDO/$BD.dump" || alto "Falló pg_dump."
"$PG_BIN/pg_restore" -l "$RESPALDO/$BD.dump" > "$RESPALDO/contenido_volcado.txt" || alto "El volcado no es legible."
echo "Objetos en el volcado: $(grep -vc '^;' "$RESPALDO/contenido_volcado.txt") · TABLE DATA: $(grep -c 'TABLE DATA' "$RESPALDO/contenido_volcado.txt") · SEQUENCE SET: $(grep -c 'SEQUENCE SET' "$RESPALDO/contenido_volcado.txt")"

paso "4. Expedientes de 8088: manifiesto de archivos privados con huellas (solo lectura)"
P="$APP8088/storage/app/private"
(cd "$P" 2>/dev/null && find . -type f -print0 | sort -z | xargs -0 -r sha256sum) > "$RESPALDO/origen_archivos_privados.sha256"
echo "Archivos privados: $(wc -l < "$RESPALDO/origen_archivos_privados.sha256") · $(du -sh "$P" 2>/dev/null | cut -f1)"

paso "5. Rol $ROL_8090 (sin superusuario, sin crear bases ni roles) y base $BD_8090"
LOC=$(leer "$ROL_APP" "$BD" "select datcollate||'|'||datctype||'|'||pg_encoding_to_char(encoding) from pg_database where datname=current_database()")
IFS='|' read -r COLL CTYPE ENC <<< "$LOC"; echo "Configuración regional de $BD: $COLL / $CTYPE / $ENC"
admin -c "create role $ROL_8090 login nosuperuser nocreatedb nocreaterole noinherit noreplication connection limit 20" || alto "No se creó el rol."
if [ -n "${CLAVE_8090:-}" ]; then   # ensayo no interactivo (la clave nunca se escribe en el registro)
    echo "alter role $ROL_8090 password :'clave';" | admin -q -v clave="$CLAVE_8090" || alto "No se definió la contraseña."
else
    echo "Defina la contraseña del rol $ROL_8090 (no se muestra; guárdela solo en el .env de 8090):"
    admin -c "\\password $ROL_8090" || alto "No se definió la contraseña."
fi
admin -c "create database $BD_8090 owner $ROL_8090 template template0 encoding '$ENC' lc_collate '$COLL' lc_ctype '$CTYPE'" || alto "No se creó la base."
admin -d "$BD_8090" -c "revoke all on database $BD_8090 from public; revoke create on schema public from public; alter schema public owner to $ROL_8090"

paso "6. Restauración del volcado en $BD_8090 (propietario $ROL_8090; sin permisos heredados de 8088)"
[ -n "${CLAVE_8090:-}" ] || { read -rsp "Contraseña del rol $ROL_8090 (la recién definida): " CLAVE_8090; echo; }
PGPASSWORD="$CLAVE_8090" "$PG_BIN/pg_restore" -U "$ROL_8090" -d "$BD_8090" --no-owner --no-privileges --exit-on-error "$RESPALDO/$BD.dump" \
    || alto "La restauración falló: la base $BD_8090 queda para revisión (no se borra)."

paso "7. Comprobación: la copia es idéntica al origen"
instantanea "$ROL_8090" "$BD_8090" copia "$CLAVE_8090"
ok=1
for f in columnas restricciones indices secuencias datos; do
    if [ -s "$RESPALDO/origen_$f.txt" ] && diff -q "$RESPALDO/origen_$f.txt" "$RESPALDO/copia_$f.txt" >/dev/null; then echo "  idéntico  $f"; else echo "  DISTINTO  $f"; diff "$RESPALDO/origen_$f.txt" "$RESPALDO/copia_$f.txt" | head -5; ok=0; fi
done
[ "$ok" = 1 ] || alto "La copia difiere del origen: revisar antes de continuar."

paso "8. Aislamiento: $ROL_8090 no puede escribir en $BD ni $ROL_APP en $BD_8090"
leer "$ROL_APP" "$BD" "select 'privilegios de $ROL_8090 sobre tablas de $BD: '||count(*) filter (where has_table_privilege('$ROL_8090', c.oid, 'INSERT,UPDATE,DELETE,TRUNCATE')) from pg_class c where c.relnamespace='public'::regnamespace and c.relkind='r'"
leer "$ROL_APP" "$BD" "select '$ROL_APP puede conectarse a $BD_8090: '||has_database_privilege('$ROL_APP', '$BD_8090', 'CONNECT')"

paso "9. Huellas del respaldo"
(cd "$RESPALDO" && sha256sum ./*.dump ./*.txt ./*.sha256 > SHA256SUMS 2>/dev/null; sha256sum -c --quiet SHA256SUMS) && echo "Huellas verificadas: $RESPALDO/SHA256SUMS"
chmod -R go-rwx "$RESPALDO"
echo; echo "10.2 terminado: respaldo en $RESPALDO; copia $BD_8090 idéntica a $BD (propietario $ROL_8090). 8088 no se modificó."
