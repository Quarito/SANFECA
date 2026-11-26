#!/usr/bin/env bash
# =============================================================================
# ANFECA · Reversión ESTRUCTURAL (nivel 3) de la migración del catálogo de posgrados.
#
#   ANFECA_AUTORIZO_REVERSION=catalogo_posgrados ANFECA_URL_DOCUMENTOS=http://192.168.7.130:8086 \
#     bash scripts/anfeca/revertir_catalogo_posgrados.sh <base> <commit> <directorio_respaldo_verificado>
#
# Solo con autorización expresa y respaldo verificado reciente. SE NIEGA si alguna formación tiene datos en las
# columnas aditivas (para esos casos se usa la desactivación de la bandera: nunca se pierden títulos).
# Ejecuta down() y retira el registro de la migración en UNA transacción (el DDL de PostgreSQL es transaccional).
# No usa migrate:rollback (que actúa por lotes y podría alcanzar otras migraciones).
# =============================================================================
set -euo pipefail
BASE="${1:?base}"; COMMIT="${2:?commit}"; RESPALDO="${3:?directorio del respaldo verificado}"
source "$(dirname "$0")/huellas_bd.inc.sh"
source "$(dirname "$0")/pg_herramientas.inc.sh"
source "$(dirname "$0")/catalogo_posgrados_comun.inc.sh"

[ "${ANFECA_AUTORIZO_REVERSION:-}" = "$CAT_AUTORIZACION" ] || detener "falta ANFECA_AUTORIZO_REVERSION=$CAT_AUTORIZACION"
cat_precondiciones "$BASE" "$COMMIT" "$RESPALDO"
[ "$(cat_columnas_presentes)" = "$CAT_NUM_COLUMNAS" ] || detener "las columnas no existen: nada que revertir"
CON_DATOS=$(psql -Atc "select count(*) from public.formaciones_academicas_solicitud where num_nonnulls($CAT_IDENTIFICADORES) > 0")
[ "$CON_DATOS" = 0 ] || detener "$CON_DATOS formaciones tienen datos en las columnas aditivas: la reversión estructural se niega (use la bandera)"

SALIDA="$RESPALDO/reversion_catalogo_posgrados_$(date +%Y%m%d_%H%M%S)"; mkdir -p "$SALIDA"; chmod 700 "$SALIDA"
huellas_bd > "$SALIDA/huellas_antes.txt"; cat_huella_formaciones > "$SALIDA/formaciones_antes.txt"
cat_migraciones > "$SALIDA/migraciones_antes.txt"; cat_documentos "$SALIDA/documentos_antes.json"

RUTA="$CAT_RUTA" MIG="$CAT_MIG" php -r 'require "vendor/autoload.php"; $a = require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  $m = require getenv("RUTA");
  Illuminate\Support\Facades\DB::transaction(function () use ($m) {
      $m->down();
      $n = Illuminate\Support\Facades\DB::table("migrations")->where("migration", getenv("MIG"))->delete();
      if ($n !== 1) { throw new RuntimeException("registro de migración inesperado: $n"); }
  });
  echo "down() aplicado y registro retirado\n";' | tee "$SALIDA/reversion.txt"

huellas_bd > "$SALIDA/huellas_despues.txt"; cat_huella_formaciones > "$SALIDA/formaciones_despues.txt"
cat_migraciones > "$SALIDA/migraciones_despues.txt"; cat_documentos "$SALIDA/documentos_despues.json"
[ "$(cat_columnas_presentes)" = 0 ] || detener "las columnas siguen existiendo"
for ck in $CAT_CHECKS; do
    [ "$(psql -Atc "select count(*) from pg_constraint where conname='$ck'")" = 0 ] || detener "el CHECK $ck sigue existiendo"
done
[ "$(diff "$SALIDA/migraciones_antes.txt" "$SALIDA/migraciones_despues.txt" | grep -E '^[<>]')" = "< $CAT_MIG" ] \
    || detener "ALERTA: la lista de migraciones cambió de forma inesperada"
cat_comparar "$SALIDA"
echo "Reversión estructural completada · evidencia: $SALIDA"
