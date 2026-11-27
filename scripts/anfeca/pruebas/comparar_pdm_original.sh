#!/usr/bin/env bash
# =============================================================================
# ANFECA · Compara, en un clúster PostgreSQL DESECHABLE, tres variantes del modelo PowerDesigner:
#   original  PDM literal (anfeca_certifica_1.original.sql), ejecutado tal cual salvo los DROP iniciales;
#   A         original + SOLO las dos correcciones autorizadas (COMU_ID_PERSONA, SOCU_ID_DOCUMENTO);
#   B         SQL instalable del repositorio (anfeca_modelo.sql).
#
#   PGHOST=127.0.0.1 PGPORT=<puerto desechable> PGUSER=postgres PGPASSWORD=... \
#     bash scripts/anfeca/pruebas/comparar_pdm_original.sh <directorio_salida_nuevo>
#
# - Distingue los ERRORES DEL SQL ORIGINAL (registrados en errores_original.txt; solo se admite el índice
#   TIENE_ARTICULO_FK, que apunta a una columna inexistente) de las DIFERENCIAS ESTRUCTURALES.
# - El SQL instalable (B) se ejecuta en una sola transacción y con ON_ERROR_STOP: cualquier error detiene.
# - Limpia SIEMPRE las bases temporales (trap), también ante fallos o interrupciones.
# - Se niega a ejecutarse contra el puerto 5432 (clúster compartido) o fuera de loopback.
# Salida: conteos, inventarios, diferencias original→A, A→B y original→B, y la matriz de 36 tablas.
# =============================================================================
set -euo pipefail
SALIDA="${1:?directorio de salida nuevo}"; [ ! -e "$SALIDA" ] || { echo "DETENIDO: $SALIDA ya existe" >&2; exit 1; }
[ "${PGPORT:-5432}" != 5432 ] || { echo "DETENIDO: use un clúster desechable (PGPORT distinto de 5432)" >&2; exit 1; }
[[ "${PGHOST:-}" == 127.0.0.1 || "${PGHOST:-}" == ::1 ]] || { echo "DETENIDO: use loopback" >&2; exit 1; }
mkdir -m 700 -p "$SALIDA"; DIR=database/sql/anfeca_modelo; TS=$(date +%s)
O=pdm_original_$TS; A=pdm_variante_a_$TS; B=pdm_variante_b_$TS; CREADAS=()
limpiar() { for b in "${CREADAS[@]}"; do psql -X -q -c "drop database if exists $b" postgres >/dev/null 2>&1 || true; done; }
trap limpiar EXIT
sha256sum "$DIR/anfeca_certifica_1.original.sql" "$DIR/anfeca_modelo.sql" > "$SALIDA/fuentes.sha256"
python3 - "$DIR/anfeca_certifica_1.original.sql" "$SALIDA/original_ejecutable.sql" <<'PY'
import re, sys
s = open(sys.argv[1], encoding='utf-8', errors='replace').read()
s = re.sub(r'(?im)^\s*drop (index|table) [^;]+;\s*$', '', s)          # el PDM literal borra antes de crear
open(sys.argv[2], 'w').write('create schema anfeca_modelo; set search_path = anfeca_modelo;\n' + s)
PY
for b in "$O" "$A" "$B"; do psql -X -qc "create database $b" postgres; CREADAS+=("$b"); done

# 1. Original literal: se registran sus errores por sentencia (no se detiene) y se clasifican.
for b in "$O" "$A"; do LC_MESSAGES=C psql -X -q -d "$b" -f "$SALIDA/original_ejecutable.sql" > "$SALIDA/ejecucion_$b.log" 2>&1 || true; done
grep -h 'ERROR' "$SALIDA/ejecucion_$O.log" > "$SALIDA/errores_original.txt" || true
INESPERADOS=$({ grep -v "soli_id_articulo" "$SALIDA/errores_original.txt" || true; } | wc -l)
[ "$INESPERADOS" = 0 ] || { echo "DETENIDO: el SQL original produjo errores no clasificados (ver errores_original.txt)" >&2; exit 1; }
# 2. Variante A: solo las dos correcciones autorizadas, renombrando la columna (arrastra PK, FK e índices).
psql -X -q -v ON_ERROR_STOP=1 -d "$A" -c "alter table anfeca_modelo.comunicacion rename column cumu_id_persona to comu_id_persona" \
    -c "alter table anfeca_modelo.solicitud_documento rename column docu_id_documento to socu_id_documento"
# 3. Variante B: SQL instalable, transacción única y detención ante cualquier error.
psql -X -q -1 -v ON_ERROR_STOP=1 -d "$B" -f "$DIR/anfeca_modelo.sql" > "$SALIDA/ejecucion_$B.log" 2>&1

Q="select 'COL',table_name,column_name,ordinal_position,data_type,coalesce(character_maximum_length::text,''),coalesce(numeric_precision::text,''),is_nullable,coalesce(column_default,'') from information_schema.columns where table_schema='anfeca_modelo'
union all select 'CON',replace(conrelid::regclass::text,'anfeca_modelo.',''),conname,0,contype::text,pg_get_constraintdef(oid),'','','' from pg_constraint where connamespace='anfeca_modelo'::regnamespace
union all select 'IDX',tablename,indexname,0,regexp_replace(indexdef,'^.* USING ',''),'','','','' from pg_indexes where schemaname='anfeca_modelo'
union all select 'SEQ',c.relname,'',0,s.seqtypid::regtype::text,s.seqstart::text,s.seqincrement::text,s.seqmax::text,'' from pg_sequence s join pg_class c on c.oid=s.seqrelid where c.relnamespace='anfeca_modelo'::regnamespace order by 1,2,3,4"
C="select 'tablas',count(*) from pg_tables where schemaname='anfeca_modelo' union all select 'columnas',count(*) from information_schema.columns where table_schema='anfeca_modelo' union all select 'pk',count(*) from pg_constraint where connamespace='anfeca_modelo'::regnamespace and contype='p' union all select 'fk',count(*) from pg_constraint where connamespace='anfeca_modelo'::regnamespace and contype='f' union all select 'indices',count(*) from pg_indexes where schemaname='anfeca_modelo' union all select 'secuencias',count(*) from pg_class where relnamespace='anfeca_modelo'::regnamespace and relkind='S'"
for par in "$O:original" "$A:a" "$B:b"; do
  psql -X -At -d "${par%%:*}" -c "$Q" > "$SALIDA/inventario_${par##*:}.txt"
  psql -X -At -d "${par%%:*}" -c "$C" | tr '\n' ' ' > "$SALIDA/conteos_${par##*:}.txt"
done
diff "$SALIDA/inventario_original.txt" "$SALIDA/inventario_a.txt" > "$SALIDA/diferencias_original_a.txt" || true
diff "$SALIDA/inventario_a.txt" "$SALIDA/inventario_b.txt" > "$SALIDA/diferencias_a_b.txt" || true
diff "$SALIDA/inventario_original.txt" "$SALIDA/inventario_b.txt" > "$SALIDA/diferencias_original_b.txt" || true
python3 - "$SALIDA" <<'PY'
import sys, collections
d = sys.argv[1]
def carga(n):
    t = collections.defaultdict(set)
    for l in open(f'{d}/inventario_{n}.txt'):
        c = l.rstrip('\n').split('|')
        if c[0] != 'SEQ': t[c[1]].add(l)
    return t
o, a, b = carga('original'), carga('a'), carga('b')
def cols(x, t): return {l.split('|')[2] for l in x[t] if l.startswith('COL|')}
with open(f'{d}/matriz_36_tablas.md', 'w') as f:
    f.write('| # | Tabla | Columnas | Restricciones | Índices | Variante A (autorizada) | Variante B (instalable) |\n|---:|---|---:|---:|---:|---|---|\n')
    for i, t in enumerate(sorted(o), 1):
        n = lambda p: sum(1 for l in b[t] if l.startswith(p))
        ra = 'Idéntica al PDM' if a[t] == o[t] else 'Renombra ' + ', '.join(sorted(cols(a, t) - cols(o, t)))
        extra = sorted(cols(b, t) - cols(a, t))
        rb = 'Igual a A' if b[t] == a[t] else ('Además renombra ' + ', '.join(extra) if extra else 'Igual a A salvo el nombre generado de la restricción NOT NULL')
        f.write(f'| {i} | `{t}` | {n("COL|")} | {n("CON|")} | {n("IDX|")} | {ra} | {rb} |\n')
PY
for v in original a b; do echo "$v: $(cat "$SALIDA/conteos_$v.txt")"; done
echo "Errores del SQL original (clasificados): $(wc -l < "$SALIDA/errores_original.txt") · TIENE_ARTICULO_FK sobre una columna inexistente"
echo "Diferencias original→A: $(grep -c '^[<>]' "$SALIDA/diferencias_original_a.txt" || true) · A→B: $(grep -c '^[<>]' "$SALIDA/diferencias_a_b.txt" || true) líneas"
echo "Matriz: $SALIDA/matriz_36_tablas.md"
