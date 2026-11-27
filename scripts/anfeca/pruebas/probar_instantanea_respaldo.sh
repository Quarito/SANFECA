#!/usr/bin/env bash
# Ejecutar con credenciales de un clúster exclusivo de pruebas.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/../huellas_bd.inc.sh"
case "${PGHOST:-}" in 127.0.0.1|::1|/*) ;; *) echo 'Se requiere un clúster local desechable.' >&2; exit 1 ;; esac
: "${PGPORT:?Indique el puerto del clúster de pruebas}"
unset PGSERVICE PGHOSTADDR PGOPTIONS ANFECA_PG_INSTANTANEA
umask 077
salida=$(mktemp -d)
origen="anfeca_snapshot_${salida##*.}"
origen="${origen,,}"
copia="${origen}_restaurada"
# Crear nombres nuevos. Si una base ya existe, el script se detiene antes de escribir.
createdb -T template0 "$origen"
createdb -T template0 "$copia"
export PGDATABASE="$origen"
psql -Xq -v ON_ERROR_STOP=1 <<'SQL'
create table public.datos (id integer primary key);
insert into public.datos values (1);
SQL

respaldar_con_cambio_concurrente() {
    pg_dump --snapshot="$ANFECA_PG_INSTANTANEA" -Fc -f "$salida/base.dump"
    psql -Xq -v ON_ERROR_STOP=1 -c 'insert into public.datos values (2)'
    huellas_bd_instantanea psql > "$salida/origen_snapshot.txt"
}

anfeca_con_instantanea respaldar_con_cambio_concurrente
pg_restore --exit-on-error --single-transaction -d "$copia" "$salida/base.dump"
huellas_bd_instantanea psql -d "$copia" > "$salida/copia.txt"
huellas_bd_instantanea psql > "$salida/origen_posterior.txt"
cmp "$salida/origen_snapshot.txt" "$salida/copia.txt"
grep -q '^public.datos|1|' "$salida/copia.txt"
grep -q '^public.datos|2|' "$salida/origen_posterior.txt"
if (anfeca_con_instantanea bash -c 'exit 23'); then
    echo 'El callback fallido no detuvo el respaldo.' >&2
    exit 1
else
    [[ "$?" -eq 23 ]]
fi
echo "PASÓ: copia y huellas de una fila; origen posterior de dos. Evidencia: $salida"
