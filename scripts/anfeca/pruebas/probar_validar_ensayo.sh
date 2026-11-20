#!/usr/bin/env bash
# Pruebas de regresión de validar_ensayo.sh (no usan PostgreSQL; seguras en cualquier equipo).
#   bash scripts/anfeca/pruebas/probar_validar_ensayo.sh
set -uo pipefail
source "$(dirname "$0")/../validar_ensayo.sh"
N="bd_anfeca_ensayo_pruebas_pg"; ROL="rol_anfecalaravel"; M="ANFECA_ENSAYO_AISLADO:$N"; REF="UTF8|es_MX.UTF-8|es_MX.UTF-8|c"
fallas=0; total=0
caso() { # esperado descripcion args...
    local esperado="$1" desc="$2"; shift 2; total=$((total + 1))
    local salida; salida="$(validar_atributos_ensayo "$@" 2>&1)"; local r=$?
    if [[ ( "$esperado" == ok && $r -eq 0 ) || ( "$esperado" == rechazo && $r -ne 0 ) ]]; then echo "ok    $desc"
    else echo "FALLA $desc → código $r: $salida"; fallas=$((fallas + 1)); fi
}
caso ok      "PG18: datistemplate=f, datallowconn=t"     "$N" "$ROL" "$ROL" "$M" "$M" f t "$REF" "$REF" 0
caso ok      "PG16: false/true"                          "$N" "$ROL" "$ROL" "$M" "$M" false true "$REF" "$REF" 0
caso ok      "normalizado en SQL: no/si"                 "$N" "$ROL" "$ROL" "$M" "$M" no si "$REF" "$REF" 0
caso rechazo "es plantilla (t)"                          "$N" "$ROL" "$ROL" "$M" "$M" t t "$REF" "$REF" 0
caso rechazo "no admite conexiones (f)"                  "$N" "$ROL" "$ROL" "$M" "$M" f f "$REF" "$REF" 0
caso rechazo "booleano ilegible"                         "$N" "$ROL" "$ROL" "$M" "$M" x t "$REF" "$REF" 0
caso rechazo "booleano vacío"                            "$N" "$ROL" "$ROL" "$M" "$M" "" t "$REF" "$REF" 0
caso rechazo "base principal"                            "bd_laravelanfeca_pruebas" "$ROL" "$ROL" "$M" "$M" f t "$REF" "$REF" 0
caso rechazo "copia migrada"                             "bd_anfeca_copia_pruebas_pg" "$ROL" "$ROL" "$M" "$M" f t "$REF" "$REF" 0
caso rechazo "dueño postgres"                            "$N" postgres "$ROL" "$M" "$M" f t "$REF" "$REF" 0
caso rechazo "rol esperado vacío"                        "$N" "" "" "$M" "$M" f t "$REF" "$REF" 0
caso rechazo "sin marca"                                 "$N" "$ROL" "$ROL" "" "$M" f t "$REF" "$REF" 0
caso rechazo "marca de otra base"                        "$N" "$ROL" "$ROL" "ANFECA_ENSAYO_AISLADO:otra" "$M" f t "$REF" "$REF" 0
caso rechazo "collation C"                               "$N" "$ROL" "$ROL" "$M" "$M" f t "UTF8|C|C|c" "$REF" 0
caso rechazo "proveedor ICU"                             "$N" "$ROL" "$ROL" "$M" "$M" f t "UTF8|es_MX.UTF-8|es_MX.UTF-8|i" "$REF" 0
caso rechazo "referencia vacía"                          "$N" "$ROL" "$ROL" "$M" "$M" f t "" "" 0
caso rechazo "conexión ajena activa"                     "$N" "$ROL" "$ROL" "$M" "$M" f t "$REF" "$REF" 1
caso rechazo "conteo de conexiones ilegible"             "$N" "$ROL" "$ROL" "$M" "$M" f t "$REF" "$REF" ""
echo "Resultado: $((total - fallas))/$total"; exit $(( fallas > 0 ))
