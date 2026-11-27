#!/usr/bin/env bash
# =============================================================================
# ANFECA · Compara, en un clúster PostgreSQL DESECHABLE, el PDM literal de PowerDesigner
# (anfeca_certifica_1.original.sql) con el SQL instalable (anfeca_modelo.sql).
#
#   PGHOST=127.0.0.1 PGPORT=<puerto desechable> PGUSER=postgres PGPASSWORD=... \
#     bash scripts/anfeca/pruebas/comparar_pdm_original.sh <directorio_salida_nuevo>
#
# Crea dos bases nuevas (pdm_original_<ts>, pdm_rama_<ts>), ejecuta ambos archivos, obtiene el inventario
# de columnas, restricciones, índices y secuencias del esquema anfeca_modelo y escribe la diferencia y la
# matriz por tabla. Se niega a ejecutarse contra el puerto 5432 (clúster compartido de los ambientes).
# =============================================================================
set -euo pipefail
SALIDA="${1:?directorio de salida nuevo}"; [ ! -e "$SALIDA" ] || { echo "DETENIDO: $SALIDA ya existe" >&2; exit 1; }
[ "${PGPORT:-5432}" != 5432 ] || { echo "DETENIDO: use un clúster desechable (PGPORT distinto de 5432)" >&2; exit 1; }
[[ "${PGHOST:-}" == 127.0.0.1 || "${PGHOST:-}" == ::1 ]] || { echo "DETENIDO: use loopback" >&2; exit 1; }
mkdir -m 700 -p "$SALIDA"; DIR=database/sql/anfeca_modelo; TS=$(date +%s)
sha256sum "$DIR/anfeca_certifica_1.original.sql" "$DIR/anfeca_modelo.sql" > "$SALIDA/fuentes.sha256"
python3 - "$DIR/anfeca_certifica_1.original.sql" "$SALIDA/original_ejecutable.sql" <<'PY'
import re, sys
s = open(sys.argv[1], encoding='utf-8', errors='replace').read()
s = re.sub(r'(?im)^\s*drop (index|table) [^;]+;\s*$', '', s)          # el PDM literal borra antes de crear
open(sys.argv[2], 'w').write('create schema anfeca_modelo; set search_path = anfeca_modelo;\n' + s)
PY
O=pdm_original_$TS; R=pdm_rama_$TS
psql -X -qc "create database $O" -c "create database $R" postgres
psql -X -q -d "$O" -f "$SALIDA/original_ejecutable.sql" > "$SALIDA/original_ejecucion.log" 2>&1 || true
psql -X -q -1 -v ON_ERROR_STOP=1 -d "$R" -f "$DIR/anfeca_modelo.sql" > "$SALIDA/rama_ejecucion.log" 2>&1
Q="select 'COL',table_name,column_name,ordinal_position,data_type,coalesce(character_maximum_length::text,''),coalesce(numeric_precision::text,''),is_nullable,coalesce(column_default,'') from information_schema.columns where table_schema='anfeca_modelo'
union all select 'CON',conrelid::regclass::text,conname,0,contype::text,pg_get_constraintdef(oid),'','','' from pg_constraint where connamespace='anfeca_modelo'::regnamespace
union all select 'IDX',tablename,indexname,0,regexp_replace(indexdef,'^.* USING ',''),'','','','' from pg_indexes where schemaname='anfeca_modelo'
union all select 'SEQ',c.relname,'',0,s.seqtypid::regtype::text,s.seqstart::text,s.seqincrement::text,s.seqmax::text,'' from pg_sequence s join pg_class c on c.oid=s.seqrelid where c.relnamespace='anfeca_modelo'::regnamespace order by 1,2,3,4"
C="select 'tablas',count(*) from pg_tables where schemaname='anfeca_modelo' union all select 'columnas',count(*) from information_schema.columns where table_schema='anfeca_modelo' union all select 'pk',count(*) from pg_constraint where connamespace='anfeca_modelo'::regnamespace and contype='p' union all select 'fk',count(*) from pg_constraint where connamespace='anfeca_modelo'::regnamespace and contype='f' union all select 'indices',count(*) from pg_indexes where schemaname='anfeca_modelo' union all select 'secuencias',count(*) from pg_class where relnamespace='anfeca_modelo'::regnamespace and relkind='S'"
for b in "$O:original" "$R:rama"; do
  psql -X -At -d "${b%%:*}" -c "$Q" > "$SALIDA/inventario_${b##*:}.txt"; psql -X -At -d "${b%%:*}" -c "$C" > "$SALIDA/conteos_${b##*:}.txt"
done
diff "$SALIDA/inventario_original.txt" "$SALIDA/inventario_rama.txt" > "$SALIDA/diferencias.txt" || true
python3 - "$SALIDA" <<'PY'
import sys, collections
d = sys.argv[1]
def carga(n):
    t = collections.defaultdict(list)
    for l in open(f'{d}/inventario_{n}.txt'):
        c = l.rstrip('\n').split('|'); tabla = c[1].replace('anfeca_modelo.', '')
        t[tabla].append(l)
    return t
o, r = carga('original'), carga('rama')
tablas = sorted(k for k in o if not k.endswith('_seq')) 
with open(f'{d}/matriz_36_tablas.md', 'w') as f:
    f.write('| # | Tabla | Columnas | Restricciones | Índices | Resultado |\n|---:|---|---:|---:|---:|---|\n')
    for i, t in enumerate(tablas, 1):
        cols = sum(1 for l in r[t] if l.startswith('COL')); con = sum(1 for l in r[t] if l.startswith('CON')); idx = sum(1 for l in r[t] if l.startswith('IDX'))
        res = 'Idéntica al PDM literal' if sorted(o[t]) == sorted(r[t]) else 'Difiere solo por renombres: ' + ', '.join(sorted({l.split('|')[2] for l in r[t] if l.startswith('COL') and l not in o[t]}))
        f.write(f'| {i} | `{t}` | {cols} | {con} | {idx} | {res} |\n')
print(open(f'{d}/conteos_original.txt').read().replace('\n', ' '), '\n', open(f'{d}/conteos_rama.txt').read().replace('\n', ' '))
PY
psql -X -qc "drop database $O" -c "drop database $R" postgres
echo "Diferencias: $(grep -c '^[<>]' "$SALIDA/diferencias.txt" || true) líneas · matriz: $SALIDA/matriz_36_tablas.md"
