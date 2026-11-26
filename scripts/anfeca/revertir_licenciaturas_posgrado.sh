#!/usr/bin/env bash
# ANFECA · Reversión ESTRUCTURAL (nivel 3) de la migración del catálogo de licenciaturas y la declaración de posgrado.
#   ANFECA_AUTORIZO_REVERSION=licenciaturas_posgrado ANFECA_URL_DOCUMENTOS=… \
#     bash scripts/anfeca/revertir_licenciaturas_posgrado.sh <base> <commit> <directorio_respaldo_verificado>
# Se NIEGA si alguna formación tiene datos en las 5 columnas aditivas (usar las banderas). Nunca toca las columnas de posgrado.
export CAT_MIG=2026_11_28_000000_add_titulo_origen_especifico_y_declaracion_posgrado
export CAT_RUTA=database/migrations_propuestas/$CAT_MIG.php
export CAT_COLUMNAS="'titulo_origen_especifico','origen_titulo_origen','clave_licenciatura_catalogo','version_catalogo_licenciaturas','cuenta_con_posgrado'"
export CAT_ARCHIVOS="$CAT_RUTA app/Services/Catalogos app/Services/Formacion app/Http/Controllers/FormacionAcademicaController.php app/Models/FormacionAcademicaSolicitud.php config/anfeca_catalogos.php"
export CAT_BANDERAS_APAGADAS="anfeca_catalogos.licenciaturas.activo anfeca_catalogos.declaracion_posgrado.activo"
export CAT_AUTORIZACION=licenciaturas_posgrado
export CAT_CHECKS="fas_titulo_origen_coherente_ck fas_declaracion_posgrado_ck"
exec bash "$(dirname "$0")/revertir_catalogo_posgrados.sh" "$@"
