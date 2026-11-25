# Función compartida: conteo y MD5 de TODAS las filas por tabla (orden determinista). Requiere PG* exportadas
# y, antes, anfeca_pg_preparar (pg_herramientas.inc.sh) para usar un psql de la versión del servidor.
huellas_bd() {
    local t
    [ -n "${ANFECA_PG_BIN:-}" ] || { echo "DETENIDO: ejecute anfeca_pg_preparar antes de huellas_bd" >&2; return 1; }
    for t in $(psql -Atc "select string_agg(format('%I.%I', schemaname, relname), ' ' order by schemaname, relname) from pg_stat_user_tables"); do
        psql -Atc "select \$q\$$t\$q\$, count(*), md5(coalesce(string_agg(x::text, E'\\n' order by x::text), '')) from $t x"
    done
}
