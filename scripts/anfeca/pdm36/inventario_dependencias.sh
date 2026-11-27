#!/usr/bin/env bash
# =============================================================================
# ANFECA · Inventario reproducible de dependencias del código fuera del modelo aprobado de 36 tablas.
#
#   bash scripts/anfeca/pdm36/inventario_dependencias.sh [raíz_del_código] > inventario.txt
#
# Cuenta, por archivo, referencias a: tablas operativas de public, modelos Eloquent sobre public, anfeca_compat y
# tablas/columnas de las extensiones del PDM. Solo lectura. Sirve para medir el avance de la adaptación: el objetivo
# es que, con ANFECA_PDM36 activo, ninguna ruta en uso llegue a estas referencias.
# =============================================================================
set -euo pipefail
R="${1:-.}"; cd "$R"
T='users|datos_solicitantes|solicitudes_certificacion|selecciones_certificacion|formaciones_academicas_solicitud|formaciones_academicas|documentos_probatorios_solicitud|documentos_probatorios|historial_documentos_academicos|certificados_historicos|datos_refrendo|instituciones_afiliadas|zonas|coordinador_zona|roles|privilegios|rol_privilegio|revisiones_documentales|password_reset_tokens|sessions|cache|jobs'
M='User|DatosSolicitante|SolicitudCertificacion|SeleccionCertificacion|FormacionAcademicaSolicitud|FormacionAcademica|DocumentoProbatorioSolicitud|DocumentoProbatorio|CertificadoHistorico|DatoRefrendo|InstitucionAfiliada|RevisionDocumental'
X='historico_certificado_importacion|seleccion_certificacion|formacion_solicitud|documentos_solicitud|solicitud_refrendo|integracion_control|pers_datos_autorizados|pers_fecha_autorizacion|socu_url_archivo|domi_codigo_postal|domi_colonia|domi_municipio|soli_tipo_tramite|soli_certificacion|pees_tipo|pees_id_solicitud|inst_campus|inst_id_zona'
F=(app routes config resources/views database/seeders)
cuenta() { grep -rnE "$1" "${F[@]}" --include=*.php 2>/dev/null | grep -v '\.bak' | grep -v '^app/\(Auth\|Services/Pdm36\|Services/Solicitantes/EstadoSolicitantePdm36\)' || true; }
publicas=$(cuenta "(DB::table|->table|->from|join|leftJoin)\(\s*'(public\.)?($T)\b|protected \\\$table\s*=\s*'($T)'|\bfrom (public\.)?($T)\b|exists:($T)|unique:($T)")
modelos=$(cuenta "Models\\\\($M)\b")
compat=$(cuenta "anfeca_compat")
ext=$(cuenta "\b($X)\b")
for par in "tablas operativas de public:$publicas" "modelos Eloquent de public:$modelos" "anfeca_compat:$compat" "extensiones del PDM:$ext"; do
    nombre=${par%%:*}; datos=${par#*:}
    n=$( [ -n "$datos" ] && echo "$datos" | wc -l || echo 0 ); a=$( [ -n "$datos" ] && echo "$datos" | cut -d: -f1 | sort -u | wc -l || echo 0 )
    echo "== $nombre: $n referencias en $a archivos"
    [ -n "$datos" ] && echo "$datos" | cut -d: -f1 | sort | uniq -c | sort -rn | sed 's/^/   /'
done
