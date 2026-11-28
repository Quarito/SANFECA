#!/bin/bash
# =====================================================================================================================
# ANFECA 8088 · Diagnóstico del ORIGEN de las 15 diferencias del modelo físico. SOLO LECTURA: no modifica PostgreSQL,
# archivos de la aplicación ni configuración; solo escribe su propio registro en $SALIDA.
#   1) diagnostico_diferencias_8088.sql en una transacción de solo lectura.
#   2) Búsqueda de las sentencias que crearon las diferencias en registros de PostgreSQL, historiales de shell/psql,
#      archivos SQL del servidor y el registro de Laravel (errores 42703 de la búsqueda de institución).
# Uso: sudo -E bash diagnostico_origen_8088.sh        (pide la contraseña del rol de la aplicación; o ~/.pgpass)
# =====================================================================================================================
source "$(dirname "$0")/../fase9/comun.inc.sh"
pedir_clave
PATRON='conv_anio|conv_id_certificacion|espr_id_area_certificacion|eval_folio_evaluacion|inst_coord_regional_cert_academica|inst_campus|inst_sede|sodo_url|fk_convocatoria_certificacion|fk_estudio_area_certificacion|ins_coord_regional_cert_academi|tico_tipo_convocatoria'

paso "1. Diferencias y datos existentes (SQL de solo lectura)"
PGOPTIONS='-c default_transaction_read_only=on' "$PG_BIN/psql" -X -U "$ROL_APP" -d "$BD" -v ON_ERROR_STOP=1 \
    -f "$(dirname "$0")/diagnostico_diferencias_8088.sql" | tee "$SALIDA/diagnostico_diferencias.txt" || alto "El diagnóstico SQL falló."

paso "2. Configuración de registro de PostgreSQL (¿quedaron registradas las sentencias DDL?)"
leer "select name || ' = ' || setting from pg_settings where name in ('log_statement','logging_collector','log_directory','log_filename','log_min_duration_statement','track_commit_timestamp')" 2>&1
DATOS_PG=$(sudo -u postgres "$PG_BIN/psql" -X -At -p "$PGPORT" ${PG_SOCKET:+-h "$PG_SOCKET"} -c 'show data_directory' 2>/dev/null)
echo "Directorio de datos: ${DATOS_PG:-no disponible (requiere sudo -u postgres)}"

paso "3. Sentencias en los registros de PostgreSQL"
for d in "${DATOS_PG:+$DATOS_PG/log}" "${DATOS_PG:+$DATOS_PG/pg_log}" /var/lib/pgsql/18/data/log /var/log/postgresql; do
    [ -n "$d" ] && sudo test -d "$d" && sudo grep -IHn -i -E "alter table|add column|rename column|add constraint|alter column" -r "$d" 2>/dev/null \
        | grep -i -E "$PATRON" | cut -c1-300 | tail -40
done | tee "$SALIDA/registros_postgresql.txt"
[ -s "$SALIDA/registros_postgresql.txt" ] || echo "Sin coincidencias (o log_statement no registraba DDL)."

paso "4. Historiales de shell y psql de los usuarios del servidor"
sudo sh -c 'ls -1 /root/.bash_history /root/.psql_history /home/*/.bash_history /home/*/.psql_history /var/lib/pgsql/.bash_history /var/lib/pgsql/.psql_history 2>/dev/null' \
    | while read -r f; do sudo grep -IHn -i -E "$PATRON" "$f" 2>/dev/null | cut -c1-300; done | tee "$SALIDA/historiales.txt"
[ -s "$SALIDA/historiales.txt" ] || echo "Sin coincidencias en historiales."

paso "5. Archivos SQL, PHP o de migración en el servidor que mencionan los atributos"
sudo find /home /var/www /root /opt /srv /tmp -xdev \( -name '*.sql' -o -name '*.php' -o -name '*.sh' -o -name '*.pdm' -o -name '*.txt' \) \
    -size -20M -not -path '*/vendor/*' -not -path '*/node_modules/*' -not -path '*/.git/*' -not -path '*/conciliacion/*' -not -path '*anfeca_fase9*' 2>/dev/null \
    | xargs -r sudo grep -IlE -i "conv_anio|inst_sede|sodo_url|eval_folio_evaluacion|fk_convocatoria_certificacion|fk_estudio_area_certificacion|inst_coord_regional_cert_academica" 2>/dev/null \
    | tee "$SALIDA/archivos_con_menciones.txt"
[ -s "$SALIDA/archivos_con_menciones.txt" ] && xargs -r -a "$SALIDA/archivos_con_menciones.txt" sudo ls -l --time-style=long-iso
[ -s "$SALIDA/archivos_con_menciones.txt" ] || echo "Ningún archivo del servidor menciona los atributos adicionales."

paso "6. Errores de la aplicación relacionados (registro de Laravel)"
sudo sh -c "grep -h -E 'ins_coord_regional_cert_academi|42703|$PATRON' $APP/storage/logs/laravel*.log 2>/dev/null" | cut -c1-250 | tail -20 \
    | tee "$SALIDA/errores_laravel.txt"
[ -s "$SALIDA/errores_laravel.txt" ] || echo "Sin errores relacionados en storage/logs."

paso "7. Código desplegado: ¿alguna referencia a los atributos adicionales?"
grep -rnIE -i "$PATRON" "$APP/app" "$APP/routes" "$APP/config" "$APP/resources" "$APP/database" 2>/dev/null | grep -v '/sql/oficial/' | cut -c1-200 \
    | tee "$SALIDA/referencias_codigo.txt"
echo; echo "Diagnóstico de origen terminado (sin cambios). Resultados en $SALIDA."
