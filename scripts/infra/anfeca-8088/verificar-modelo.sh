#!/usr/bin/env bash
# Usar después de una carga SQL autorizada. Solo lee el candidato y la referencia.
set -euo pipefail
umask 077
unset PGHOSTADDR PGOPTIONS PGSERVICE PGPASSWORD
export PGOPTIONS='-c default_transaction_read_only=on'

if [[ ${1:-} != --referencia || ! ${2:-} =~ ^[a-z_][a-z0-9_]*$ || $2 == certificacion_anfeca || $# != 3 ]]; then
    echo 'Uso: verificar-modelo.sh --referencia NOMBRE_BASE_REFERENCIA DIRECTORIO_EVIDENCIA_NUEVO' >&2
    exit 2
fi

pg=/usr/pgsql-18/bin
socket=/run/anfeca8088-pg
puerto=55488
destino=$3
[[ -s /etc/anfeca8088/cluster ]] || { echo 'No se encontró la identidad registrada de 8088.' >&2; exit 2; }

psql_lectura() {
    runuser -u postgres -- "$pg/psql" -XqAt -w -v ON_ERROR_STOP=1 -h "$socket" -p "$puerto" "$@"
}
identidad=$(psql_lectura -d postgres -c "select current_setting('data_directory'), current_setting('server_version_num'), system_identifier from pg_control_system()")
[[ "$identidad" == "/var/lib/pgsql/anfeca8088|180006|$(cat /etc/anfeca8088/cluster)" ]] || { echo 'Clúster inesperado.' >&2; exit 2; }
mkdir -m 700 -- "$destino"

for par in "referencia:$2" candidato:certificacion_anfeca; do
    tipo=${par%%:*}
    bd=${par#*:}
    psql_lectura -d "$bd" > "$destino/$tipo.inventario" <<'SQL'
select json_build_object(
    'esquemas', (select array_agg(nspname order by nspname) from pg_namespace
        where nspname !~ '^pg_' and nspname <> 'information_schema'),
    'tablas', (select count(*) from pg_class c where c.relnamespace = 'public'::regnamespace and relkind in ('r','p','f')),
    'columnas', (select count(*) from pg_attribute a join pg_class c on c.oid=a.attrelid
        where c.relnamespace = 'public'::regnamespace and relkind in ('r','p','f') and attnum>0 and not attisdropped),
    'pk', (select count(*) from pg_constraint where connamespace='public'::regnamespace and contype='p'),
    'fk', (select count(*) from pg_constraint where connamespace='public'::regnamespace and contype='f'),
    'indices', (select count(*) from pg_indexes where schemaname='public'),
    'secuencias', (select count(*) from pg_sequences where schemaname='public')
);
SQL
    python3 -c 'import json,sys; datos=json.load(sys.stdin); print(datos); assert datos==dict(esquemas=["public"],tablas=36,columnas=173,pk=36,fk=35,indices=96,secuencias=36)' < "$destino/$tipo.inventario"
    runuser -u postgres -- "$pg/pg_dump" -w -h "$socket" -p "$puerto" --schema-only --no-owner --no-privileges "$bd" |
        sed -E '/^\\(un)?restrict[[:space:]]/d' > "$destino/$tipo.sql"
done
diff -u "$destino/referencia.sql" "$destino/candidato.sql" > "$destino/diferencias.patch"
sha256sum "$destino/"{referencia,candidato}.sql
echo 'La estructura coincide. Las pruebas funcionales todavía se verifican por separado.'
