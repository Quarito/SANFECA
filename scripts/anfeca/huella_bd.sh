#!/usr/bin/env bash
# =============================================================================
# ANFECA · Huella de SOLO LECTURA de una base PostgreSQL (estructura + datos).
#
#   bash scripts/anfeca/huella_bd.sh <base> > huella.txt
#
# Por cada tabla de public, anfeca_modelo y anfeca_compat: filas y md5 del contenido.
# Además: md5 de columnas, restricciones, índices, vistas, disparadores y funciones.
# Dos huellas iguales = no cambió ni un registro ni una estructura.
# Requiere PGHOST, PGPORT, PGUSER y PGPASSWORD en el entorno (no los guarda).
# Excluye tablas técnicas volátiles de Laravel (sesiones, caché, trabajos).
# =============================================================================
set -euo pipefail
BD="${1:?Indique la base}"
VOLATILES="'sessions','cache','cache_locks','jobs','job_batches','failed_jobs'"

psql -d "$BD" -v ON_ERROR_STOP=1 -X -q -At -F ' ' <<SQL
set default_transaction_read_only = on;
select 'BASE', current_database(), version();
select 'ESTRUCTURA columnas', md5(coalesce(string_agg(table_schema||'.'||table_name||'.'||column_name||':'||data_type||':'||is_nullable||':'||coalesce(column_default,''), '|' order by table_schema, table_name, ordinal_position), ''))
  from information_schema.columns where table_schema in ('public','anfeca_modelo','anfeca_compat');
select 'ESTRUCTURA restricciones', md5(coalesce(string_agg(conrelid::regclass::text||':'||conname||':'||pg_get_constraintdef(oid), '|' order by conrelid::regclass::text, conname), ''))
  from pg_constraint where connamespace in (select oid from pg_namespace where nspname in ('public','anfeca_modelo','anfeca_compat'));
select 'ESTRUCTURA indices', md5(coalesce(string_agg(schemaname||'.'||indexname||':'||indexdef, '|' order by schemaname, indexname), ''))
  from pg_indexes where schemaname in ('public','anfeca_modelo','anfeca_compat');
select 'ESTRUCTURA disparadores', count(*), md5(coalesce(string_agg(tgrelid::regclass::text||':'||tgname, '|' order by tgrelid::regclass::text, tgname), ''))
  from pg_trigger where not tgisinternal;
select 'ESTRUCTURA vistas', count(*) from information_schema.views where table_schema in ('public','anfeca_modelo','anfeca_compat');
select 'ESQUEMAS', string_agg(nspname, ',' order by nspname) from pg_namespace where nspname in ('public','anfeca_modelo','anfeca_compat');
SQL

# Datos tabla por tabla (orden por el texto de la fila: estable sin depender de una PK).
psql -d "$BD" -X -At -c "select schemaname||'.'||tablename from pg_tables
    where schemaname in ('public','anfeca_modelo','anfeca_compat') and tablename not in ($VOLATILES) order by 1" |
while read -r T; do
    psql -d "$BD" -X -q -At -F ' ' -c "set default_transaction_read_only = on;
        select 'DATOS', '$T', count(*), md5(coalesce(string_agg(x::text, '|' order by x::text), '')) from $T x" | grep '^DATOS'
done
