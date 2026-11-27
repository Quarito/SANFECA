# Función compartida: conteo y MD5 de TODAS las filas por tabla (orden determinista). Requiere PG* exportadas
# y, antes, anfeca_pg_preparar (pg_herramientas.inc.sh) para usar un psql de la versión del servidor.
huellas_bd() {
    local t
    [ -n "${ANFECA_PG_BIN:-}" ] || { echo "DETENIDO: ejecute anfeca_pg_preparar antes de huellas_bd" >&2; return 1; }
    for t in $(psql -Atc "select string_agg(format('%I.%I', schemaname, relname), ' ' order by schemaname, relname) from pg_stat_user_tables"); do
        psql -Atc "select \$q\$$t\$q\$, count(*), md5(coalesce(string_agg(x::text, E'\\n' order by x::text), '')) from $t x"
    done
}

# Genera el mismo formato que huellas_origen.txt, con una única instantánea de lectura.
# Uso: huellas_bd_instantanea psql [opciones de conexión]
huellas_bd_instantanea() {
    "$@" -XqAt -v ON_ERROR_STOP=1 -v instantanea="${ANFECA_PG_INSTANTANEA:-}" <<'SQL'
select length(:'instantanea') > 0 as importar \gset
begin isolation level repeatable read read only;
\if :importar
set transaction snapshot :'instantanea';
\endif
select format(
    'select %L, count(*), md5(coalesce(string_agg(x::text, chr(10) order by x::text), '''')) from %I.%I x',
    format('%I.%I', schemaname, relname), schemaname, relname
)
from pg_stat_user_tables order by schemaname, relname
\gexec
commit;
SQL
}

# Mantiene abierta la transacción exportadora mientras el callback consulta el origen.
# La variable ANFECA_PG_INSTANTANEA llega al callback y a sus procesos hijos.
anfeca_con_instantanea() (
    set -e
    local instantanea proceso
    coproc ANFECA_FUENTE { psql -XqAt -v ON_ERROR_STOP=1; }
    proceso=$ANFECA_FUENTE_PID
    # Los descriptores originales del coproc pueden cerrarse al terminar psql.
    exec 3>&"${ANFECA_FUENTE[1]}" 4<&"${ANFECA_FUENTE[0]}"
    trap 'exec 3>&- 4<&-; kill "$proceso" 2>/dev/null || true; wait "$proceso" 2>/dev/null || true' EXIT

    printf '%s\n' 'begin isolation level repeatable read read only;' 'select pg_export_snapshot();' >&3
    if ! IFS= read -r -t 30 instantanea <&4 || [[ ! "$instantanea" =~ ^[[:xdigit:]]+-[[:xdigit:]]+-[0-9]+$ ]]; then
        echo "DETENIDO: no se obtuvo una instantánea PostgreSQL del origen." >&2
        exit 1
    fi

    export ANFECA_PG_INSTANTANEA="$instantanea"
    "$@" || exit "$?"
    printf '%s\n' 'rollback;' '\q' >&3 || exit 1
    wait "$proceso" || exit 1
    trap - EXIT
)
