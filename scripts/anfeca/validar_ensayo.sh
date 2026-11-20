#!/usr/bin/env bash
# =============================================================================
# ANFECA · Validación PURA de los atributos de la base de ensayo (sin acceso a la base).
# La usa preparar_integracion.sh; la prueban scripts/anfeca/pruebas/probar_validar_ensayo.sh.
#
#   validar_atributos_ensayo <nombre> <dueño> <rol_esperado> <marca> <marca_esperada> \
#       <es_plantilla> <admite_conexion> <codif|collate|ctype|proveedor> <referencia_principal> <conexiones_ajenas>
#
# es_plantilla / admite_conexion admiten t/f, true/false, si/no (PostgreSQL 16 y 18 difieren).
# Devuelve 0 si la base es aceptable; si no, imprime el motivo y devuelve 1.
# =============================================================================
bool_pg() {
    case "${1,,}" in t|true|si|on|1) echo si ;; f|false|no|off|0) echo no ;; *) echo invalido ;; esac
}

validar_atributos_ensayo() {
    local nombre="$1" dueno="$2" rol="$3" marca="$4" marca_esperada="$5" plantilla conexion
    local regional="$8" referencia="$9" ajenas="${10}"
    plantilla="$(bool_pg "$6")"; conexion="$(bool_pg "$7")"

    [[ "$nombre" == "bd_anfeca_ensayo_pruebas_pg" ]] || { echo "Nombre no permitido: '$nombre'."; return 1; }
    [[ -n "$rol" && "$dueno" == "$rol" ]] || { echo "La base pertenece a '$dueno', no a '$rol'."; return 1; }
    [[ -n "$marca_esperada" && "$marca" == "$marca_esperada" ]] || { echo "Marca '$marca' distinta de '$marca_esperada'."; return 1; }
    [[ "$plantilla" == "no" ]] || { echo "La base es plantilla o el valor es inválido (datistemplate='$6')."; return 1; }
    [[ "$conexion" == "si" ]] || { echo "La base no admite conexiones o el valor es inválido (datallowconn='$7')."; return 1; }
    [[ -n "$referencia" && "$regional" == "$referencia" ]] || { echo "Codificación/collation '$regional' distinta de la principal '$referencia'."; return 1; }
    [[ "$ajenas" =~ ^[0-9]+$ && "$ajenas" -eq 0 ]] || { echo "Hay $ajenas conexión(es) de otras sesiones a la base de ensayo."; return 1; }
    return 0
}
