#!/usr/bin/env bash
# =============================================================================
# ANFECA · Aplicación CONTROLADA de la migración aditiva del catálogo de posgrados.
#
#   cd /var/www/ANFECA_ENSAYO
#   bash scripts/anfeca/respaldo_verificado.sh bd_anfeca_ensayo_pruebas_pg desarrollo/modulo-coordinacion <commit>
#   ANFECA_AUTORIZO_MIGRACION=catalogo_posgrados ANFECA_URL_DOCUMENTOS=http://192.168.7.130:8086 \
#     bash scripts/anfeca/aplicar_catalogo_posgrados.sh <base> <commit> <directorio_respaldo_verificado>
#
# NO AUTORIZADO TODAVÍA EN 8086. Se detiene si falla cualquier condición previa:
#  autorización expresa · ≥ 5 GB libres · base, rama y commit esperados · código del catálogo idéntico a HEAD ·
#  sin caché de configuración · PDM apagado, revisión documental y catálogo DESACTIVADOS · respaldo verificado
#  (restaurado) de esa base y ese commit, de menos de 2 horas, con SHA-256 correcto · migración pendiente.
# Aplica SOLO esta migración (--path), nunca «migrate» general. La bandera NO se activa aquí.
# Antes y después: huellas de todas las tablas, de los datos anteriores de formaciones y de los documentos.
# =============================================================================
set -euo pipefail
BASE="${1:?base}"; COMMIT="${2:?commit}"; RESPALDO="${3:?directorio del respaldo verificado}"
source "$(dirname "$0")/huellas_bd.inc.sh"
source "$(dirname "$0")/pg_herramientas.inc.sh"
source "$(dirname "$0")/catalogo_posgrados_comun.inc.sh"

[ "${ANFECA_AUTORIZO_MIGRACION:-}" = catalogo_posgrados ] || detener "falta ANFECA_AUTORIZO_MIGRACION=catalogo_posgrados"
echo "Espacio libre antes: $(espacio) MB"
cat_precondiciones "$BASE" "$COMMIT" "$RESPALDO"
[ "$(cat_columnas_presentes)" = 0 ] || detener "las columnas ya existen"
php artisan migrate:status --path="$CAT_RUTA" --no-ansi | grep -q "$CAT_MIG.*Pending" || detener "la migración no está pendiente"
PRETEND=$(php artisan migrate --path="$CAT_RUTA" --pretend --force --no-ansi)
grep -qiE 'escuela_facultad|revisiones_documentales|create table|drop ' <<< "$PRETEND" && detener "el ensayo en seco incluye operaciones no previstas"

SALIDA="$RESPALDO/aplicacion_catalogo_posgrados_$(date +%Y%m%d_%H%M%S)"; mkdir -p "$SALIDA"; chmod 700 "$SALIDA"
echo "$PRETEND" > "$SALIDA/ensayo_en_seco.txt"
huellas_bd > "$SALIDA/huellas_antes.txt"; cat_huella_formaciones > "$SALIDA/formaciones_antes.txt"
cat_migraciones > "$SALIDA/migraciones_antes.txt"; cat_documentos "$SALIDA/documentos_antes.json"

echo "Aplicando $CAT_MIG ($(date -Is))"
php artisan migrate --path="$CAT_RUTA" --force --no-ansi | tee "$SALIDA/migrate.txt"

huellas_bd > "$SALIDA/huellas_despues.txt"; cat_huella_formaciones > "$SALIDA/formaciones_despues.txt"
cat_migraciones > "$SALIDA/migraciones_despues.txt"; cat_documentos "$SALIDA/documentos_despues.json"
[ "$(diff "$SALIDA/migraciones_antes.txt" "$SALIDA/migraciones_despues.txt" | grep -E '^[<>]')" = "> $CAT_MIG" ] \
    || detener "ALERTA: la lista de migraciones cambió de forma inesperada"
cat_comparar "$SALIDA"
bash "$(dirname "$0")/verificar_catalogo_posgrados.sh" | tee "$SALIDA/verificacion.txt"
echo "Espacio libre después: $(espacio) MB · evidencia: $SALIDA"
echo "La bandera ANFECA_CATALOGO_POSGRADOS sigue DESACTIVADA; su activación requiere autorización aparte."
