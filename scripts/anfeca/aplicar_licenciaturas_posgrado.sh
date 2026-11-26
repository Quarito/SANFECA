#!/usr/bin/env bash
# =============================================================================
# ANFECA · Aplicación CONTROLADA de la migración aditiva del catálogo de licenciaturas y la declaración de posgrado.
#
#   bash scripts/anfeca/respaldo_verificado.sh bd_anfeca_ensayo_pruebas_pg desarrollo/modulo-coordinacion <commit>
#   ANFECA_AUTORIZO_MIGRACION=licenciaturas_posgrado ANFECA_URL_DOCUMENTOS=http://192.168.7.130:8086 \
#     bash scripts/anfeca/aplicar_licenciaturas_posgrado.sh <base> <commit> <directorio_respaldo_verificado>
#
# NO AUTORIZADO TODAVÍA EN 8086. Mismas condiciones y comparaciones que aplicar_catalogo_posgrados.sh (respaldo
# verificado < 2 h, código igual a HEAD, banderas de licenciaturas y declaración DESACTIVADAS, huellas de tablas,
# datos anteriores y documentos). El catálogo de posgrados puede seguir ACTIVO.
# =============================================================================
export CAT_MIG=2026_11_28_000000_add_titulo_origen_especifico_y_declaracion_posgrado
export CAT_RUTA=database/migrations_propuestas/$CAT_MIG.php
export CAT_COLUMNAS="'titulo_origen_especifico','origen_titulo_origen','clave_licenciatura_catalogo','version_catalogo_licenciaturas','cuenta_con_posgrado'"
export CAT_ARCHIVOS="$CAT_RUTA app/Services/Catalogos app/Services/Formacion app/Http/Controllers/FormacionAcademicaController.php app/Models/FormacionAcademicaSolicitud.php config/anfeca_catalogos.php"
export CAT_BANDERAS_APAGADAS="anfeca_catalogos.licenciaturas.activo anfeca_catalogos.declaracion_posgrado.activo"
export CAT_VERIFICAR=verificar_licenciaturas_posgrado.sh
export CAT_AUTORIZACION=licenciaturas_posgrado
export CAT_CHECKS="fas_titulo_origen_coherente_ck fas_declaracion_posgrado_ck"
export CAT_MENSAJE_FINAL="Las banderas ANFECA_CATALOGO_LICENCIATURAS y ANFECA_DECLARACION_POSGRADO siguen DESACTIVADAS; su activación requiere autorización aparte."
exec bash "$(dirname "$0")/aplicar_catalogo_posgrados.sh" "$@"
