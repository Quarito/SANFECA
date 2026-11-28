#!/bin/bash
# BLOQUE 4B · DIAGNÓSTICO PREVIO CON SQL (alternativa B), SIN ESCRITURAS: 00_diagnostico_previo.sql en una transacción
# de solo lectura (además se fuerza default_transaction_read_only). No requiere PHP ni Laravel.
# Uso: bash 04b_diagnostico_sql.sh      (PAQUETE_SQL=<directorio SEPOMEX_ANFECA> si difiere de comun.inc.sh)
source "$(dirname "$0")/comun.inc.sh"
pedir_clave
paso "Paquete SQL"; exigir_paquete
paso "Conexión real: base, puerto, versión y usuario"
leer "select concat_ws(' · ', current_database(), 'puerto ' || current_setting('port'), version(), 'rol ' || current_user, pg_size_pretty(pg_database_size(current_database())))"
paso "Estructura oficial de $BD"; verificar_estructura_sql || alto "La base no tiene la estructura oficial de 36 tablas."
paso "Espacio y memoria disponibles"
D=$("$PG_BIN/psql" -X -U "$ROL_APP" -d "$BD" -Atc 'show data_directory' 2>/dev/null); df -h "${D:-/var/lib/pgsql}" 2>/dev/null || df -h /var/lib/pgsql 2>/dev/null || df -h /
echo "Requerido: ~200 MB libres en el volumen de datos (18 MB de tablas + WAL y margen); memoria: ~65 MB en la sesión."
free -m
paso "00_diagnostico_previo.sql (solo lectura)"
(cd "$PAQUETE_SQL/sql" && PGOPTIONS='-c default_transaction_read_only=on' "$PG_BIN/psql" -X -U "$ROL_APP" -d "$BD" -v ON_ERROR_STOP=1 -f 00_diagnostico_previo.sql) \
    | tee "$SALIDA/diagnostico_previo.txt" || alto "El diagnóstico falló."
grep -q "CONFLICTO" "$SALIDA/diagnostico_previo.txt" && alto "Hay CONFLICTOS en la conciliación de entidades: revíselos antes de cargar."
echo; echo "Sin escrituras. Revise las secciones 5–7 (duplicados, conciliación y pendientes) antes del bloque 5B."
