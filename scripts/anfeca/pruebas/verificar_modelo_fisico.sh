#!/usr/bin/env bash
# Compara dos bases previamente instaladas. Solo consulta catálogos y definiciones.
set -euo pipefail
umask 077

referencia="${1:?Indique el servicio libpq de la referencia aislada}"
candidato="${2:?Indique el servicio libpq del candidato}"
salida="${3:?Indique un directorio nuevo de evidencia privada}"
if [[ ! "$referencia" =~ ^[A-Za-z0-9_-]+$ || ! "$candidato" =~ ^[A-Za-z0-9_-]+$ || "$referencia" == "$candidato" ]]; then
    echo 'Se requieren dos nombres de servicio distintos en pg_service.conf.' >&2
    exit 2
fi
mkdir -m 700 -- "$salida"

inventario() {
    local servicio="$1" nombre="$2"
    local conexion="service=$servicio"
    psql -XqAt -v ON_ERROR_STOP=1 --dbname="$conexion" > "$salida/$nombre.tablas" <<'SQL'
SELECT n.nspname, c.relname, count(a.attnum)
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
LEFT JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum > 0 AND NOT a.attisdropped
WHERE c.relkind IN ('r', 'p', 'f')
  AND n.nspname !~ '^pg_' AND n.nspname <> 'information_schema'
GROUP BY n.nspname, c.relname
ORDER BY n.nspname COLLATE "C", c.relname COLLATE "C";
SQL
    pg_dump --schema-only --no-owner --no-privileges --dbname="$conexion" |
        sed -E '/^\\(un)?restrict[[:space:]]/d' > "$salida/$nombre.sql"
}

inventario "$referencia" referencia
if ! awk -F'|' '
    $1 != "anfeca_modelo" { extranas++ }
    { columnas += $3 }
    END { exit !(NR == 36 && columnas == 173 && !extranas) }
' "$salida/referencia.tablas"; then
    echo 'La referencia debe contener únicamente las 36 tablas y 173 atributos aprobados.' >&2
    exit 2
fi

inventario "$candidato" candidato
awk -F'|' '{ tablas[$1]++; columnas[$1]+=$3 } END {
    for (esquema in tablas) printf "%s | %d tablas | %d atributos\n", esquema, tablas[esquema], columnas[esquema]
}' "$salida/candidato.tablas" | LC_ALL=C sort
sha256sum "$salida/"{referencia,candidato}.sql

resultado=0
diff -u "$salida/referencia.tablas" "$salida/candidato.tablas" > "$salida/tablas.diff" || resultado=$?
if (( resultado > 1 )); then exit "$resultado"; fi
diff -u "$salida/referencia.sql" "$salida/candidato.sql" > "$salida/estructura.diff" || resultado=$?
if (( resultado > 1 )); then exit "$resultado"; fi
if (( resultado != 0 )); then
    echo 'FALLÓ: hay diferencias físicas. Revise tablas.diff y estructura.diff.' >&2
    exit 1
fi
echo 'PASÓ: el inventario físico y el DDL descriptivo coinciden.'
