# Función compartida: conteo y MD5 de TODAS las filas por tabla (orden determinista). Requiere PG* exportadas.
huellas_bd() {
    local t
    for t in $(psql -Atc "select string_agg(format('%I.%I', schemaname, relname), ' ' order by schemaname, relname) from pg_stat_user_tables"); do
        psql -Atc "select \$q\$$t\$q\$, count(*), md5(coalesce(string_agg(x::text, E'\\n' order by x::text), '')) from $t x"
    done
}
