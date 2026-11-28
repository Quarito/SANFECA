-- =====================================================================================================================
-- ANFECA · Diagnóstico de las diferencias del modelo físico frente al SQL oficial (36 tablas, 173 atributos, 35 FK).
-- EXCLUSIVAMENTE DE LECTURA: transacción READ ONLY que termina en ROLLBACK; no crea objetos (ni temporales).
-- No muestra datos personales: de columnas que pueden contenerlos solo reporta conteos y longitudes.
-- Uso (VS Code SSH):
--   PGOPTIONS='-c default_transaction_read_only=on' psql -X -h 127.0.0.1 -p 5488 -U anfeca_8088 -d certificacion_anfeca \
--        -v ON_ERROR_STOP=1 -f diagnostico_diferencias_8088.sql 2>&1 | tee ~/diagnostico_diferencias_8088_$(date +%Y%m%d_%H%M).txt
-- Referencia vigente: database/sql/oficial/anfeca_modelo_36_tablas_vigente.sql = export original de PowerDesigner
--   (SHA-256 5798ce83…8285, sin cambios) + corrección autorizada C-01: institucion.inst_coord_regional_cert_academica
--   (antes ins_coord_regional_cert_academi). Ver database/sql/oficial/CORRECCIONES_AUTORIZADAS.md
-- =====================================================================================================================
\set ON_ERROR_STOP on
\pset footer off
\pset null '(nulo)'
BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY;

\echo '== 0. Identidad de la conexión'
SELECT current_database() AS base, current_setting('port') AS puerto, current_user AS rol, version() AS version,
       pg_postmaster_start_time()::timestamp(0) AS servidor_iniciado, now()::timestamp(0) AS ahora,
       current_setting('track_commit_timestamp') AS track_commit_timestamp;

\echo '== 1. Resumen: tablas, atributos y llaves foráneas físicas frente a las oficiales (36 / 173 / 35)'
WITH oficial(tabla, columna, tipo, longitud, nulo) AS (VALUES
        ('acta', 'acta_id_acta', 'integer', NULL, false),
        ('acta', 'acta_id_solicitud', 'integer', NULL, true),
        ('acta', 'acta_coordinador', 'character varying', 1024, true),
        ('acta', 'acta_secretario', 'character varying', 1024, true),
        ('acta', 'acta_sede', 'character varying', 1024, true),
        ('alcaldia_municipio', 'almu_id_alcaldia', 'integer', NULL, false),
        ('alcaldia_municipio', 'almu_id_entidad_federativa', 'integer', NULL, false),
        ('alcaldia_municipio', 'almu_nombre', 'character varying', 150, false),
        ('area_certificacion', 'area_id_area_certificacion', 'integer', NULL, false),
        ('area_certificacion', 'area_descripcion', 'character varying', 200, false),
        ('articulo_subindice', 'arce_id_articulo', 'integer', NULL, false),
        ('articulo_subindice', 'arce_subindice', 'character varying', 10, false),
        ('articulo_subindice', 'arce_descripcion', 'character varying', 100, false),
        ('certificado', 'cert_id_certificado', 'integer', NULL, false),
        ('certificado', 'cert_id_evaluacion', 'integer', NULL, false),
        ('certificado', 'cert_folio', 'character varying', 100, false),
        ('certificado', 'cert_anio', 'character varying', 15, false),
        ('certificado', 'cert_estado', 'character varying', 5, true),
        ('colonia', 'colo_id_colonia', 'integer', NULL, false),
        ('colonia', 'colo_id_alcaldia', 'integer', NULL, false),
        ('colonia', 'colo_nombre', 'character varying', 150, false),
        ('colonia', 'colo_codigo_postal', 'character varying', 5, false),
        ('comunicacion', 'comu_id_comunicacion', 'integer', NULL, false),
        ('comunicacion', 'comu_id_persona', 'integer', NULL, false),
        ('comunicacion', 'comu_id_tipo_comunicacion', 'integer', NULL, false),
        ('comunicacion', 'comu_valor', 'character varying', 150, false),
        ('comunicacion', 'comu_visible', 'boolean', NULL, true),
        ('convocatoria', 'conv_id_convocatoria', 'integer', NULL, false),
        ('convocatoria', 'conv_id_tipo_convocatoria', 'integer', NULL, false),
        ('convocatoria', 'conv_nombre', 'character varying', 150, false),
        ('convocatoria', 'conv_monto_recuperacion', 'money', NULL, false),
        ('convocatoria', 'conv_fecha_inicio_registro', 'date', NULL, false),
        ('convocatoria', 'conv_fecha_fin_registro', 'date', NULL, false),
        ('convocatoria', 'conv_fecha_resultado', 'date', NULL, false),
        ('convocatoria', 'conv_fecha_entrega_certificados', 'date', NULL, false),
        ('convocatoria', 'conv_puntuacion_max', 'integer', NULL, false),
        ('convocatoria', 'conv_puntuacion_min', 'integer', NULL, false),
        ('convocatoria', 'conv_lugar_entrega', 'character varying', 300, true),
        ('convocatoria', 'conv_evento_entrega', 'character varying', 300, true),
        ('c_estado_solicitud', 'ceso_id_c_estado_solicitud', 'integer', NULL, false),
        ('c_estado_solicitud', 'ceso_estado_solicitud', 'character varying', 50, false),
        ('dictamen', 'dict_id_dictamen', 'integer', NULL, false),
        ('dictamen', 'dict_id_tipo_dictamen', 'integer', NULL, false),
        ('dictamen', 'dict_id_evaluacion', 'integer', NULL, true),
        ('dictamen', 'dict_encabezado', 'character varying', 9, false),
        ('dictamen', 'dict_folio_dictamen', 'character varying', 9, false),
        ('dictamen', 'dict_fecha_dictamen_creacion', 'date', NULL, false),
        ('dictamen', 'dict_hora_dictamen_creacion', 'time without time zone', NULL, false),
        ('dictamen', 'dict_fecha_dictamen_ultima_impr', 'date', NULL, true),
        ('documento', 'docu_id_documento', 'integer', NULL, false),
        ('documento', 'docu_descripcion', 'character varying', 150, false),
        ('documento', 'docu_estatus', 'character varying', 3, false),
        ('domicilio', 'domi_id_domicilio', 'integer', NULL, false),
        ('domicilio', 'domi_id_persona', 'integer', NULL, false),
        ('domicilio', 'domi_id_entidad_federativa', 'integer', NULL, false),
        ('domicilio', 'domi_calle', 'character varying', 170, false),
        ('domicilio', 'domi_numero_ext', 'character varying', 6, false),
        ('domicilio', 'domi_numero_int', 'character varying', 4, true),
        ('entidad_federativa', 'enfe_id_entidad_federativa', 'integer', NULL, false),
        ('entidad_federativa', 'enfe_nombre', 'character varying', 85, false),
        ('estado_documento', 'esdo_id_estado_documento', 'integer', NULL, false),
        ('estado_documento', 'esdo_estado_documento', 'character varying', 250, false),
        ('estado_documento', 'esdo_estado', 'character varying', 3, false),
        ('estado_solicitud', 'esso_id__estado_solicitud', 'integer', NULL, false),
        ('estado_solicitud', 'esso_id_c_estado_solicitud', 'integer', NULL, true),
        ('estado_solicitud', 'esso_id_solicitud', 'integer', NULL, false),
        ('estado_solicitud', 'esso_descripcion', 'character varying', 55, false),
        ('estado_solicitud', 'esso_siglas', 'character varying', 2, false),
        ('estado_solicitud', 'esso_fecha', 'date', NULL, false),
        ('estado_solicitud', 'esso_hora', 'time without time zone', NULL, false),
        ('estado_solicitud', 'esso_motivo_rechazo', 'character varying', 300, true),
        ('estado_solicitud_documento', 'esdo_id_estado_solicitud_docume', 'integer', NULL, false),
        ('estado_solicitud_documento', 'esdo_id_documentacion', 'integer', NULL, false),
        ('estado_solicitud_documento', 'esdo_id_estado_documento', 'integer', NULL, false),
        ('estado_solicitud_documento', 'esdo_observacion', 'character varying', 300, false),
        ('estado_solicitud_documento', 'esdo_fecha', 'date', NULL, false),
        ('estado_solicitud_documento', 'esdo_hora', 'time without time zone', NULL, false),
        ('estudio_profesional', 'espr_id_estudio_profesional', 'integer', NULL, false),
        ('estudio_profesional', 'espr_id_nivel_profesional', 'integer', NULL, false),
        ('estudio_profesional', 'espr_nombre', 'character varying', 150, false),
        ('estudio_profesional', 'espr_estado', 'character varying', 3, false),
        ('evaluacion', 'eval_id_evaluacion', 'integer', NULL, false),
        ('evaluacion', 'eval_id_solicitud', 'integer', NULL, false),
        ('evaluacion', 'eval_puntuacion', 'integer', NULL, true),
        ('evaluacion', 'eval_fecha_evaluacion', 'date', NULL, false),
        ('evaluacion', 'eval_hora_evaluacion', 'time without time zone', NULL, false),
        ('historico_certificado', 'hice_id_historico_certificado', 'integer', NULL, false),
        ('historico_certificado', 'hice_anio_certificacion', 'character varying', 4, true),
        ('historico_certificado', 'hice_numero_certificacion', 'character varying', 4, true),
        ('historico_certificado', 'hice_tipo', 'character varying', 15, true),
        ('historico_certificado', 'hice_folio_certificado', 'character varying', 7, false),
        ('historico_certificado', 'hice_curp', 'character varying', 18, true),
        ('historico_certificado', 'hice_area_certificacion', 'character varying', 120, true),
        ('institucion', 'inst_id_institucion', 'integer', NULL, false),
        ('institucion', 'inst_numero_afiliacion', 'character varying', 7, false),
        ('institucion', 'inst_nombre', 'character varying', 300, false),
        ('institucion', 'inst_facultad', 'character varying', 500, false),
        ('institucion', 'inst_zona', 'character varying', 80, false),
        ('institucion', 'inst_coord_regional_cert_academica', 'character varying', 200, true),
        ('nivel_profesional', 'nipr_id_nivel_profesional', 'integer', NULL, false),
        ('nivel_profesional', 'nipr_nivel_profesional', 'character varying', 300, false),
        ('pago', 'pago_id_pago', 'integer', NULL, false),
        ('pago', 'pago_id_solicitud', 'integer', NULL, false),
        ('pago', 'pago_url_comprobante', 'character varying', 255, false),
        ('persona', 'pers_id_persona', 'integer', NULL, false),
        ('persona', 'pers_id_institucion', 'integer', NULL, true),
        ('persona', 'pers_nombre', 'character varying', 150, false),
        ('persona', 'pers_apellido_paterno', 'character varying', 60, true),
        ('persona', 'pers_apellido_materno', 'character varying', 60, true),
        ('persona', 'pers_curp', 'character varying', 18, false),
        ('persona', 'pers_rfc', 'character varying', 13, false),
        ('persona', 'pers_genero', 'character varying', 1, true),
        ('persona', 'pers_num_afiliacion_institucion', 'character varying', 10, true),
        ('persona', 'pers_activo', 'boolean', NULL, true),
        ('persona_estudio_profesional', 'pees_id_persona_estudio_profesi', 'integer', NULL, false),
        ('persona_estudio_profesional', 'pees_id_estuido_profesional', 'integer', NULL, false),
        ('persona_estudio_profesional', 'pees_id_persona', 'integer', NULL, false),
        ('persona_estudio_profesional', 'pees_url_titulo', 'character varying', 300, true),
        ('persona_estudio_profesional', 'pees_cedula', 'character varying', 20, true),
        ('persona_estudio_profesional', 'pees_url_cedula', 'character varying', 180, true),
        ('privilegio', 'priv_id_privilegio', 'integer', NULL, false),
        ('privilegio', 'priv_nombre', 'character varying', 150, false),
        ('privilegio', 'priv_descripcion', 'character varying', 150, false),
        ('refrendo_certificado', 'rece_id_refrendo_certificacion', 'integer', NULL, false),
        ('refrendo_certificado', 'rece_id_evaluacion', 'integer', NULL, true),
        ('refrendo_certificado', 'rece_id_certificado', 'integer', NULL, false),
        ('refrendo_certificado', 'rece_numero_refrendo', 'integer', NULL, false),
        ('refrendo_certificado', 'rece_anio_refrendo', 'integer', NULL, false),
        ('refrendo_certificado', 'rece_art14', 'boolean', NULL, true),
        ('refrendo_certificado', 'rece_art13', 'boolean', NULL, false),
        ('rol', 'rol_id_rol', 'integer', NULL, false),
        ('rol', 'rol_nombre', 'character varying', 150, false),
        ('rol_privilegio', 'ropr_id_rol_privilegio', 'integer', NULL, false),
        ('rol_privilegio', 'ropr_id_rol', 'integer', NULL, false),
        ('rol_privilegio', 'ropr_id_privilegio', 'integer', NULL, false),
        ('sede', 'sede_id_sede', 'integer', NULL, false),
        ('sede', 'inst_id_institucion', 'integer', NULL, false),
        ('sede', 'sede_descripcion', 'character varying', 150, false),
        ('solicitud', 'soli_id_solicitud', 'integer', NULL, false),
        ('solicitud', 'soli_id_persona', 'integer', NULL, false),
        ('solicitud', 'soli_id_area_certificacion', 'integer', NULL, false),
        ('solicitud', 'soli_id_tipo_personal_academico', 'integer', NULL, false),
        ('solicitud', 'soli_id_convocatoria', 'integer', NULL, true),
        ('solicitud', 'soli_id_articulo', 'integer', NULL, true),
        ('solicitud', 'soli_numero_certificado', 'character varying', 10, true),
        ('solicitud', 'soli_fecha_registro_solicitud', 'date', NULL, false),
        ('solicitud', 'soli_folio_solicitud', 'integer', NULL, false),
        ('solicitud', 'soli_art13', 'boolean', NULL, false),
        ('solicitud', 'soli_art14', 'boolean', NULL, false),
        ('solicitud_documento', 'socu_id_documentacion', 'integer', NULL, false),
        ('solicitud_documento', 'socu_id_solicitud', 'integer', NULL, false),
        ('solicitud_documento', 'socu_id_documento', 'integer', NULL, false),
        ('solicitud_documento', 'socu_fecha', 'date', NULL, false),
        ('solicitud_documento', 'socu_hora', 'time without time zone', NULL, false),
        ('tipo_comunicacion', 'tico_id_tipo_comunicacion', 'integer', NULL, false),
        ('tipo_comunicacion', 'tico_tipo_comunicacion', 'character varying', 50, false),
        ('tipo_comunicacion', 'tico_estado', 'character varying', 3, false),
        ('tipo_convocatoria', 'tico_id_tipo_convocatoria', 'integer', NULL, false),
        ('tipo_convocatoria', 'tico_tipo_convocatoria', 'character varying', 50, false),
        ('tipo_convocatoria', 'tico_estado', 'character varying', 3, false),
        ('tipo_convocatoria', 'tico_siglas', 'character varying', 65, false),
        ('tipo_dictamen', 'tidi_id_tipo_dictamen', 'integer', NULL, false),
        ('tipo_dictamen', 'tidi_descripcion', 'character varying', 120, false),
        ('tipo_dictamen', 'tidi_tipo_dictamen_estado', 'boolean', NULL, false),
        ('tipo_personal_academico', 'tipa_id_tipo_personal_academico', 'integer', NULL, false),
        ('tipo_personal_academico', 'tipa_descripcion', 'character varying', 150, false),
        ('tipo_personal_academico', 'tipa_estado', 'boolean', NULL, false),
        ('usuario', 'usua_id_usuario', 'integer', NULL, false),
        ('usuario', 'usua_id_persona', 'integer', NULL, false),
        ('usuario', 'usua_id_rol', 'integer', NULL, false),
        ('usuario', 'usua_nombre', 'character varying', 150, false),
        ('usuario', 'usua_contrasenia', 'character varying', 255, false),
        ('usuario', 'usua_estatus', 'character varying', 20, false)),
fis AS (SELECT c.table_name AS tabla, c.column_name AS columna FROM information_schema.columns c
          JOIN information_schema.tables t ON t.table_schema = c.table_schema AND t.table_name = c.table_name
         WHERE c.table_schema = 'public' AND t.table_type = 'BASE TABLE')
SELECT (SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE') AS tablas,
       (SELECT count(*) FROM fis) AS atributos,
       (SELECT count(*) FROM pg_constraint WHERE contype = 'f' AND connamespace = 'public'::regnamespace) AS llaves_foraneas,
       (SELECT count(*) FROM fis f WHERE NOT EXISTS (SELECT 1 FROM oficial o WHERE o.tabla = f.tabla AND o.columna = f.columna)) AS atributos_adicionales,
       (SELECT count(*) FROM oficial o WHERE NOT EXISTS (SELECT 1 FROM fis f WHERE o.tabla = f.tabla AND o.columna = f.columna)) AS atributos_faltantes;

\echo '== 2. Diferencias de atributos (adicional, faltante, tipo, longitud, nulos), con posición física'
\echo '   «posicion» = número físico del atributo; «atributos_oficiales» = cuántos tiene la tabla en el modelo oficial.'
\echo '   Adicional con posición mayor: se agregó después de crear la tabla (ALTER TABLE ADD). Adicional dentro del rango y con'
\echo '   la misma definición que un atributo oficial faltante: posible renombre (ALTER TABLE RENAME COLUMN).'
WITH oficial(tabla, columna, tipo, longitud, nulo) AS (VALUES
        ('acta', 'acta_id_acta', 'integer', NULL, false),
        ('acta', 'acta_id_solicitud', 'integer', NULL, true),
        ('acta', 'acta_coordinador', 'character varying', 1024, true),
        ('acta', 'acta_secretario', 'character varying', 1024, true),
        ('acta', 'acta_sede', 'character varying', 1024, true),
        ('alcaldia_municipio', 'almu_id_alcaldia', 'integer', NULL, false),
        ('alcaldia_municipio', 'almu_id_entidad_federativa', 'integer', NULL, false),
        ('alcaldia_municipio', 'almu_nombre', 'character varying', 150, false),
        ('area_certificacion', 'area_id_area_certificacion', 'integer', NULL, false),
        ('area_certificacion', 'area_descripcion', 'character varying', 200, false),
        ('articulo_subindice', 'arce_id_articulo', 'integer', NULL, false),
        ('articulo_subindice', 'arce_subindice', 'character varying', 10, false),
        ('articulo_subindice', 'arce_descripcion', 'character varying', 100, false),
        ('certificado', 'cert_id_certificado', 'integer', NULL, false),
        ('certificado', 'cert_id_evaluacion', 'integer', NULL, false),
        ('certificado', 'cert_folio', 'character varying', 100, false),
        ('certificado', 'cert_anio', 'character varying', 15, false),
        ('certificado', 'cert_estado', 'character varying', 5, true),
        ('colonia', 'colo_id_colonia', 'integer', NULL, false),
        ('colonia', 'colo_id_alcaldia', 'integer', NULL, false),
        ('colonia', 'colo_nombre', 'character varying', 150, false),
        ('colonia', 'colo_codigo_postal', 'character varying', 5, false),
        ('comunicacion', 'comu_id_comunicacion', 'integer', NULL, false),
        ('comunicacion', 'comu_id_persona', 'integer', NULL, false),
        ('comunicacion', 'comu_id_tipo_comunicacion', 'integer', NULL, false),
        ('comunicacion', 'comu_valor', 'character varying', 150, false),
        ('comunicacion', 'comu_visible', 'boolean', NULL, true),
        ('convocatoria', 'conv_id_convocatoria', 'integer', NULL, false),
        ('convocatoria', 'conv_id_tipo_convocatoria', 'integer', NULL, false),
        ('convocatoria', 'conv_nombre', 'character varying', 150, false),
        ('convocatoria', 'conv_monto_recuperacion', 'money', NULL, false),
        ('convocatoria', 'conv_fecha_inicio_registro', 'date', NULL, false),
        ('convocatoria', 'conv_fecha_fin_registro', 'date', NULL, false),
        ('convocatoria', 'conv_fecha_resultado', 'date', NULL, false),
        ('convocatoria', 'conv_fecha_entrega_certificados', 'date', NULL, false),
        ('convocatoria', 'conv_puntuacion_max', 'integer', NULL, false),
        ('convocatoria', 'conv_puntuacion_min', 'integer', NULL, false),
        ('convocatoria', 'conv_lugar_entrega', 'character varying', 300, true),
        ('convocatoria', 'conv_evento_entrega', 'character varying', 300, true),
        ('c_estado_solicitud', 'ceso_id_c_estado_solicitud', 'integer', NULL, false),
        ('c_estado_solicitud', 'ceso_estado_solicitud', 'character varying', 50, false),
        ('dictamen', 'dict_id_dictamen', 'integer', NULL, false),
        ('dictamen', 'dict_id_tipo_dictamen', 'integer', NULL, false),
        ('dictamen', 'dict_id_evaluacion', 'integer', NULL, true),
        ('dictamen', 'dict_encabezado', 'character varying', 9, false),
        ('dictamen', 'dict_folio_dictamen', 'character varying', 9, false),
        ('dictamen', 'dict_fecha_dictamen_creacion', 'date', NULL, false),
        ('dictamen', 'dict_hora_dictamen_creacion', 'time without time zone', NULL, false),
        ('dictamen', 'dict_fecha_dictamen_ultima_impr', 'date', NULL, true),
        ('documento', 'docu_id_documento', 'integer', NULL, false),
        ('documento', 'docu_descripcion', 'character varying', 150, false),
        ('documento', 'docu_estatus', 'character varying', 3, false),
        ('domicilio', 'domi_id_domicilio', 'integer', NULL, false),
        ('domicilio', 'domi_id_persona', 'integer', NULL, false),
        ('domicilio', 'domi_id_entidad_federativa', 'integer', NULL, false),
        ('domicilio', 'domi_calle', 'character varying', 170, false),
        ('domicilio', 'domi_numero_ext', 'character varying', 6, false),
        ('domicilio', 'domi_numero_int', 'character varying', 4, true),
        ('entidad_federativa', 'enfe_id_entidad_federativa', 'integer', NULL, false),
        ('entidad_federativa', 'enfe_nombre', 'character varying', 85, false),
        ('estado_documento', 'esdo_id_estado_documento', 'integer', NULL, false),
        ('estado_documento', 'esdo_estado_documento', 'character varying', 250, false),
        ('estado_documento', 'esdo_estado', 'character varying', 3, false),
        ('estado_solicitud', 'esso_id__estado_solicitud', 'integer', NULL, false),
        ('estado_solicitud', 'esso_id_c_estado_solicitud', 'integer', NULL, true),
        ('estado_solicitud', 'esso_id_solicitud', 'integer', NULL, false),
        ('estado_solicitud', 'esso_descripcion', 'character varying', 55, false),
        ('estado_solicitud', 'esso_siglas', 'character varying', 2, false),
        ('estado_solicitud', 'esso_fecha', 'date', NULL, false),
        ('estado_solicitud', 'esso_hora', 'time without time zone', NULL, false),
        ('estado_solicitud', 'esso_motivo_rechazo', 'character varying', 300, true),
        ('estado_solicitud_documento', 'esdo_id_estado_solicitud_docume', 'integer', NULL, false),
        ('estado_solicitud_documento', 'esdo_id_documentacion', 'integer', NULL, false),
        ('estado_solicitud_documento', 'esdo_id_estado_documento', 'integer', NULL, false),
        ('estado_solicitud_documento', 'esdo_observacion', 'character varying', 300, false),
        ('estado_solicitud_documento', 'esdo_fecha', 'date', NULL, false),
        ('estado_solicitud_documento', 'esdo_hora', 'time without time zone', NULL, false),
        ('estudio_profesional', 'espr_id_estudio_profesional', 'integer', NULL, false),
        ('estudio_profesional', 'espr_id_nivel_profesional', 'integer', NULL, false),
        ('estudio_profesional', 'espr_nombre', 'character varying', 150, false),
        ('estudio_profesional', 'espr_estado', 'character varying', 3, false),
        ('evaluacion', 'eval_id_evaluacion', 'integer', NULL, false),
        ('evaluacion', 'eval_id_solicitud', 'integer', NULL, false),
        ('evaluacion', 'eval_puntuacion', 'integer', NULL, true),
        ('evaluacion', 'eval_fecha_evaluacion', 'date', NULL, false),
        ('evaluacion', 'eval_hora_evaluacion', 'time without time zone', NULL, false),
        ('historico_certificado', 'hice_id_historico_certificado', 'integer', NULL, false),
        ('historico_certificado', 'hice_anio_certificacion', 'character varying', 4, true),
        ('historico_certificado', 'hice_numero_certificacion', 'character varying', 4, true),
        ('historico_certificado', 'hice_tipo', 'character varying', 15, true),
        ('historico_certificado', 'hice_folio_certificado', 'character varying', 7, false),
        ('historico_certificado', 'hice_curp', 'character varying', 18, true),
        ('historico_certificado', 'hice_area_certificacion', 'character varying', 120, true),
        ('institucion', 'inst_id_institucion', 'integer', NULL, false),
        ('institucion', 'inst_numero_afiliacion', 'character varying', 7, false),
        ('institucion', 'inst_nombre', 'character varying', 300, false),
        ('institucion', 'inst_facultad', 'character varying', 500, false),
        ('institucion', 'inst_zona', 'character varying', 80, false),
        ('institucion', 'inst_coord_regional_cert_academica', 'character varying', 200, true),
        ('nivel_profesional', 'nipr_id_nivel_profesional', 'integer', NULL, false),
        ('nivel_profesional', 'nipr_nivel_profesional', 'character varying', 300, false),
        ('pago', 'pago_id_pago', 'integer', NULL, false),
        ('pago', 'pago_id_solicitud', 'integer', NULL, false),
        ('pago', 'pago_url_comprobante', 'character varying', 255, false),
        ('persona', 'pers_id_persona', 'integer', NULL, false),
        ('persona', 'pers_id_institucion', 'integer', NULL, true),
        ('persona', 'pers_nombre', 'character varying', 150, false),
        ('persona', 'pers_apellido_paterno', 'character varying', 60, true),
        ('persona', 'pers_apellido_materno', 'character varying', 60, true),
        ('persona', 'pers_curp', 'character varying', 18, false),
        ('persona', 'pers_rfc', 'character varying', 13, false),
        ('persona', 'pers_genero', 'character varying', 1, true),
        ('persona', 'pers_num_afiliacion_institucion', 'character varying', 10, true),
        ('persona', 'pers_activo', 'boolean', NULL, true),
        ('persona_estudio_profesional', 'pees_id_persona_estudio_profesi', 'integer', NULL, false),
        ('persona_estudio_profesional', 'pees_id_estuido_profesional', 'integer', NULL, false),
        ('persona_estudio_profesional', 'pees_id_persona', 'integer', NULL, false),
        ('persona_estudio_profesional', 'pees_url_titulo', 'character varying', 300, true),
        ('persona_estudio_profesional', 'pees_cedula', 'character varying', 20, true),
        ('persona_estudio_profesional', 'pees_url_cedula', 'character varying', 180, true),
        ('privilegio', 'priv_id_privilegio', 'integer', NULL, false),
        ('privilegio', 'priv_nombre', 'character varying', 150, false),
        ('privilegio', 'priv_descripcion', 'character varying', 150, false),
        ('refrendo_certificado', 'rece_id_refrendo_certificacion', 'integer', NULL, false),
        ('refrendo_certificado', 'rece_id_evaluacion', 'integer', NULL, true),
        ('refrendo_certificado', 'rece_id_certificado', 'integer', NULL, false),
        ('refrendo_certificado', 'rece_numero_refrendo', 'integer', NULL, false),
        ('refrendo_certificado', 'rece_anio_refrendo', 'integer', NULL, false),
        ('refrendo_certificado', 'rece_art14', 'boolean', NULL, true),
        ('refrendo_certificado', 'rece_art13', 'boolean', NULL, false),
        ('rol', 'rol_id_rol', 'integer', NULL, false),
        ('rol', 'rol_nombre', 'character varying', 150, false),
        ('rol_privilegio', 'ropr_id_rol_privilegio', 'integer', NULL, false),
        ('rol_privilegio', 'ropr_id_rol', 'integer', NULL, false),
        ('rol_privilegio', 'ropr_id_privilegio', 'integer', NULL, false),
        ('sede', 'sede_id_sede', 'integer', NULL, false),
        ('sede', 'inst_id_institucion', 'integer', NULL, false),
        ('sede', 'sede_descripcion', 'character varying', 150, false),
        ('solicitud', 'soli_id_solicitud', 'integer', NULL, false),
        ('solicitud', 'soli_id_persona', 'integer', NULL, false),
        ('solicitud', 'soli_id_area_certificacion', 'integer', NULL, false),
        ('solicitud', 'soli_id_tipo_personal_academico', 'integer', NULL, false),
        ('solicitud', 'soli_id_convocatoria', 'integer', NULL, true),
        ('solicitud', 'soli_id_articulo', 'integer', NULL, true),
        ('solicitud', 'soli_numero_certificado', 'character varying', 10, true),
        ('solicitud', 'soli_fecha_registro_solicitud', 'date', NULL, false),
        ('solicitud', 'soli_folio_solicitud', 'integer', NULL, false),
        ('solicitud', 'soli_art13', 'boolean', NULL, false),
        ('solicitud', 'soli_art14', 'boolean', NULL, false),
        ('solicitud_documento', 'socu_id_documentacion', 'integer', NULL, false),
        ('solicitud_documento', 'socu_id_solicitud', 'integer', NULL, false),
        ('solicitud_documento', 'socu_id_documento', 'integer', NULL, false),
        ('solicitud_documento', 'socu_fecha', 'date', NULL, false),
        ('solicitud_documento', 'socu_hora', 'time without time zone', NULL, false),
        ('tipo_comunicacion', 'tico_id_tipo_comunicacion', 'integer', NULL, false),
        ('tipo_comunicacion', 'tico_tipo_comunicacion', 'character varying', 50, false),
        ('tipo_comunicacion', 'tico_estado', 'character varying', 3, false),
        ('tipo_convocatoria', 'tico_id_tipo_convocatoria', 'integer', NULL, false),
        ('tipo_convocatoria', 'tico_tipo_convocatoria', 'character varying', 50, false),
        ('tipo_convocatoria', 'tico_estado', 'character varying', 3, false),
        ('tipo_convocatoria', 'tico_siglas', 'character varying', 65, false),
        ('tipo_dictamen', 'tidi_id_tipo_dictamen', 'integer', NULL, false),
        ('tipo_dictamen', 'tidi_descripcion', 'character varying', 120, false),
        ('tipo_dictamen', 'tidi_tipo_dictamen_estado', 'boolean', NULL, false),
        ('tipo_personal_academico', 'tipa_id_tipo_personal_academico', 'integer', NULL, false),
        ('tipo_personal_academico', 'tipa_descripcion', 'character varying', 150, false),
        ('tipo_personal_academico', 'tipa_estado', 'boolean', NULL, false),
        ('usuario', 'usua_id_usuario', 'integer', NULL, false),
        ('usuario', 'usua_id_persona', 'integer', NULL, false),
        ('usuario', 'usua_id_rol', 'integer', NULL, false),
        ('usuario', 'usua_nombre', 'character varying', 150, false),
        ('usuario', 'usua_contrasenia', 'character varying', 255, false),
        ('usuario', 'usua_estatus', 'character varying', 20, false)),
fis AS (SELECT c.table_name AS tabla, c.column_name AS columna, c.data_type AS tipo, c.character_maximum_length AS longitud,
               c.is_nullable = 'YES' AS nulo, c.column_default AS defecto, c.ordinal_position AS posicion
          FROM information_schema.columns c JOIN information_schema.tables t ON t.table_schema = c.table_schema AND t.table_name = c.table_name
         WHERE c.table_schema = 'public' AND t.table_type = 'BASE TABLE'),
ult AS (SELECT tabla, count(*) AS ultimo_original FROM oficial GROUP BY tabla),
d AS (
    SELECT f.tabla, f.columna, 'adicional' AS diferencia, f.tipo || coalesce('(' || f.longitud || ')', '') || CASE WHEN f.nulo THEN ' null' ELSE ' not null' END AS fisico,
           '' AS oficial, f.posicion, f.defecto FROM fis f WHERE NOT EXISTS (SELECT 1 FROM oficial o WHERE o.tabla = f.tabla AND o.columna = f.columna)
    UNION ALL
    SELECT o.tabla, o.columna, 'faltante', '', o.tipo || coalesce('(' || o.longitud || ')', '') || CASE WHEN o.nulo THEN ' null' ELSE ' not null' END, NULL, NULL
      FROM oficial o WHERE NOT EXISTS (SELECT 1 FROM fis f WHERE o.tabla = f.tabla AND o.columna = f.columna)
    UNION ALL
    SELECT f.tabla, f.columna, concat_ws(' y ', CASE WHEN f.tipo <> o.tipo THEN 'tipo' END, CASE WHEN f.longitud IS DISTINCT FROM o.longitud THEN 'longitud' END,
                                         CASE WHEN f.nulo <> o.nulo THEN 'nulos' END),
           f.tipo || coalesce('(' || f.longitud || ')', '') || CASE WHEN f.nulo THEN ' null' ELSE ' not null' END,
           o.tipo || coalesce('(' || o.longitud || ')', '') || CASE WHEN o.nulo THEN ' null' ELSE ' not null' END, f.posicion, f.defecto
      FROM fis f JOIN oficial o USING (tabla, columna) WHERE f.tipo <> o.tipo OR f.longitud IS DISTINCT FROM o.longitud OR f.nulo <> o.nulo)
SELECT d.tabla, d.columna, d.diferencia, d.fisico, d.oficial, d.posicion, u.ultimo_original AS atributos_oficiales,
       CASE WHEN d.diferencia = 'adicional' AND EXISTS (SELECT 1 FROM d x WHERE x.tabla = d.tabla AND x.diferencia = 'faltante' AND x.oficial = d.fisico)
                 AND d.posicion <= u.ultimo_original
                 THEN 'posible renombre de ' || (SELECT string_agg(x.columna, ', ') FROM d x WHERE x.tabla = d.tabla AND x.diferencia = 'faltante' AND x.oficial = d.fisico)
            WHEN d.diferencia = 'adicional' AND d.posicion > u.ultimo_original THEN 'agregada después (ALTER TABLE ADD)'
            WHEN d.diferencia = 'adicional' THEN 'presente al crear la tabla' ELSE '' END AS indicio,
       d.defecto, col_description(('public.' || d.tabla)::regclass, d.posicion) AS comentario
  FROM d LEFT JOIN ult u USING (tabla) ORDER BY 1, 3, 2;

\echo '== 3. Huecos de columnas eliminadas (attisdropped): rastro de ALTER TABLE previos en las tablas afectadas'
SELECT c.relname AS tabla, count(*) FILTER (WHERE a.attisdropped) AS columnas_eliminadas_historicas, max(a.attnum) AS atributos_fisicos_con_huecos,
       c.relfilenode <> c.oid AS tabla_reescrita
  FROM pg_class c JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum > 0
 WHERE c.relnamespace = 'public'::regnamespace AND c.relkind = 'r'
   AND c.relname IN ('convocatoria', 'estudio_profesional', 'evaluacion', 'institucion', 'solicitud_documento', 'tipo_convocatoria')
 GROUP BY c.relname, c.relfilenode, c.oid ORDER BY 1;

\echo '== 4. Llaves foráneas adicionales o faltantes, con su definición completa'
WITH oficial(nombre, tabla, columna, referencia) AS (VALUES
        ('fk_acta_proporcio_solicitu', 'acta', 'acta_id_solicitud', 'solicitud'),
        ('fk_alcaldia_contiene_entidad_', 'alcaldia_municipio', 'almu_id_entidad_federativa', 'entidad_federativa'),
        ('fk_certific_relations_evaluaci', 'certificado', 'cert_id_evaluacion', 'evaluacion'),
        ('fk_colonia_se_compon_alcaldia', 'colonia', 'colo_id_alcaldia', 'alcaldia_municipio'),
        ('fk_comunica_clasifica_tipo_com', 'comunicacion', 'comu_id_tipo_comunicacion', 'tipo_comunicacion'),
        ('fk_comunica_tiene_con_persona', 'comunicacion', 'comu_id_persona', 'persona'),
        ('fk_convocat_relations_tipo_con', 'convocatoria', 'conv_id_tipo_convocatoria', 'tipo_convocatoria'),
        ('fk_dictamen_reference_tipo_dic', 'dictamen', 'dict_id_tipo_dictamen', 'tipo_dictamen'),
        ('fk_dictamen_reference_evaluaci', 'dictamen', 'dict_id_evaluacion', 'evaluacion'),
        ('fk_domicili_se_encuen_entidad_', 'domicilio', 'domi_id_entidad_federativa', 'entidad_federativa'),
        ('fk_domicili_tiene_dom_persona', 'domicilio', 'domi_id_persona', 'persona'),
        ('fk_estado_s_reference_solicitu', 'estado_solicitud', 'esso_id_solicitud', 'solicitud'),
        ('fk_estado_s_relations_c_estado', 'estado_solicitud', 'esso_id_c_estado_solicitud', 'c_estado_solicitud'),
        ('fk_estado_s_relations_solicitu', 'estado_solicitud_documento', 'esdo_id_documentacion', 'solicitud_documento'),
        ('fk_estado_s_relations_estado_d', 'estado_solicitud_documento', 'esdo_id_estado_documento', 'estado_documento'),
        ('fk_estudio__reference_nivel_pr', 'estudio_profesional', 'espr_id_nivel_profesional', 'nivel_profesional'),
        ('fk_evaluaci_relations_solicitu', 'evaluacion', 'eval_id_solicitud', 'solicitud'),
        ('fk_pago_reference_solicitu', 'pago', 'pago_id_solicitud', 'solicitud'),
        ('fk_persona_pertenece_instituc', 'persona', 'pers_id_institucion', 'institucion'),
        ('fk_persona__relations_estudio_', 'persona_estudio_profesional', 'pees_id_estuido_profesional', 'estudio_profesional'),
        ('fk_persona__relations_persona', 'persona_estudio_profesional', 'pees_id_persona', 'persona'),
        ('fk_refrendo_relations_evaluaci', 'refrendo_certificado', 'rece_id_evaluacion', 'evaluacion'),
        ('fk_refrendo_relations_certific', 'refrendo_certificado', 'rece_id_certificado', 'certificado'),
        ('fk_rol_priv_rol_privi_rol', 'rol_privilegio', 'ropr_id_rol', 'rol'),
        ('fk_rol_priv_rol_privi_privileg', 'rol_privilegio', 'ropr_id_privilegio', 'privilegio'),
        ('fk_sede_tiene_sed_instituc', 'sede', 'inst_id_institucion', 'institucion'),
        ('fk_solicitu_esta_asoc_tipo_per', 'solicitud', 'soli_id_tipo_personal_academico', 'tipo_personal_academico'),
        ('fk_solicitu_pertenece_area_cer', 'solicitud', 'soli_id_area_certificacion', 'area_certificacion'),
        ('fk_solicitu_puede_hac_persona', 'solicitud', 'soli_id_persona', 'persona'),
        ('fk_solicitu_relations_convocat', 'solicitud', 'soli_id_convocatoria', 'convocatoria'),
        ('fk_solicitu_tiene_art_articulo', 'solicitud', 'soli_id_articulo', 'articulo_subindice'),
        ('fk_solicitu_relations_document', 'solicitud_documento', 'socu_id_documento', 'documento'),
        ('fk_solicitu_tiene_as_solicitu', 'solicitud_documento', 'socu_id_solicitud', 'solicitud'),
        ('fk_usuario_tiene_rol_rol', 'usuario', 'usua_id_rol', 'rol'),
        ('fk_usuario_tiene_usu_persona', 'usuario', 'usua_id_persona', 'persona'))
SELECT 'adicional' AS diferencia, c.conname AS nombre, cl.relname AS tabla, pg_get_constraintdef(c.oid) AS definicion, c.convalidated AS validada
  FROM pg_constraint c JOIN pg_class cl ON cl.oid = c.conrelid
 WHERE c.contype = 'f' AND c.connamespace = 'public'::regnamespace AND c.conname NOT IN (SELECT nombre FROM oficial)
UNION ALL
SELECT 'faltante', o.nombre, o.tabla, o.columna || ' → ' || o.referencia, NULL FROM oficial o
 WHERE NOT EXISTS (SELECT 1 FROM pg_constraint c WHERE c.contype = 'f' AND c.connamespace = 'public'::regnamespace AND c.conname = o.nombre)
ORDER BY 1, 2;

\echo '== 5. Objetos que dependen de los atributos adicionales (índices, vistas, disparadores, otras restricciones)'
WITH adicionales(tabla, columna) AS (VALUES ('convocatoria', 'conv_anio'), ('convocatoria', 'conv_id_certificacion'),
        ('estudio_profesional', 'espr_id_area_certificacion'), ('evaluacion', 'eval_folio_evaluacion'),
        ('institucion', 'inst_coord_regional_cert_academica'), ('institucion', 'inst_campus'), ('institucion', 'inst_sede'),
        ('solicitud_documento', 'sodo_url')),
a AS (SELECT ad.*, att.attnum FROM adicionales ad JOIN pg_attribute att ON att.attrelid = ('public.' || ad.tabla)::regclass AND att.attname = ad.columna)
SELECT a.tabla, a.columna, 'índice' AS objeto, i.indexrelid::regclass::text AS nombre, pg_get_indexdef(i.indexrelid) AS definicion
  FROM a JOIN pg_index i ON i.indrelid = ('public.' || a.tabla)::regclass AND a.attnum = ANY (i.indkey)
UNION ALL
SELECT a.tabla, a.columna, 'restricción ' || c.contype::text, c.conname, pg_get_constraintdef(c.oid)
  FROM a JOIN pg_constraint c ON c.conrelid = ('public.' || a.tabla)::regclass AND a.attnum = ANY (c.conkey)
UNION ALL
SELECT a.tabla, a.columna, 'vista o regla', v.ev_class::regclass::text, NULL
  FROM a JOIN pg_depend d ON d.refobjid = ('public.' || a.tabla)::regclass AND d.refobjsubid = a.attnum AND d.classid = 'pg_rewrite'::regclass
  JOIN pg_rewrite v ON v.oid = d.objid
UNION ALL
SELECT c.relname, '(tabla)', 'disparador', t.tgname, pg_get_triggerdef(t.oid)
  FROM pg_trigger t JOIN pg_class c ON c.oid = t.tgrelid
 WHERE NOT t.tgisinternal AND c.relname IN (SELECT tabla FROM adicionales)
ORDER BY 1, 2, 3;
SELECT count(*) AS vistas_en_public, (SELECT count(*) FROM pg_proc WHERE pronamespace = 'public'::regnamespace) AS funciones_en_public,
       (SELECT count(*) FROM pg_event_trigger) AS disparadores_de_eventos FROM pg_views WHERE schemaname = 'public';

SELECT count(*) = 8 AS hay_adicionales FROM information_schema.columns WHERE table_schema = 'public' AND (table_name, column_name) IN (
       ('convocatoria', 'conv_anio'), ('convocatoria', 'conv_id_certificacion'), ('estudio_profesional', 'espr_id_area_certificacion'),
       ('evaluacion', 'eval_folio_evaluacion'), ('institucion', 'inst_coord_regional_cert_academica'), ('institucion', 'inst_campus'),
       ('institucion', 'inst_sede'), ('solicitud_documento', 'sodo_url')) \gset
\if :hay_adicionales
\echo '== 6. Información existente en los atributos adicionales (conteos; muestras solo de catálogos)'
SELECT 'convocatoria.conv_anio' AS atributo, count(*) AS filas, count(conv_anio) AS con_valor, count(DISTINCT conv_anio) AS distintos,
       min(conv_anio)::text AS minimo, max(conv_anio)::text AS maximo FROM convocatoria
UNION ALL SELECT 'convocatoria.conv_id_certificacion', count(*), count(conv_id_certificacion), count(DISTINCT conv_id_certificacion),
       min(conv_id_certificacion)::text, max(conv_id_certificacion)::text FROM convocatoria
UNION ALL SELECT 'estudio_profesional.espr_id_area_certificacion', count(*), count(espr_id_area_certificacion), count(DISTINCT espr_id_area_certificacion),
       min(espr_id_area_certificacion)::text, max(espr_id_area_certificacion)::text FROM estudio_profesional
UNION ALL SELECT 'evaluacion.eval_folio_evaluacion', count(*), count(eval_folio_evaluacion), count(DISTINCT eval_folio_evaluacion),
       min(length(eval_folio_evaluacion::text))::text || ' car.', max(length(eval_folio_evaluacion::text))::text || ' car.' FROM evaluacion
UNION ALL SELECT 'institucion.inst_coord_regional_cert_academica', count(*), count(inst_coord_regional_cert_academica), count(DISTINCT inst_coord_regional_cert_academica),
       min(length(inst_coord_regional_cert_academica::text))::text || ' car.', max(length(inst_coord_regional_cert_academica::text))::text || ' car.' FROM institucion
UNION ALL SELECT 'institucion.inst_campus', count(*), count(inst_campus), count(DISTINCT inst_campus),
       min(length(inst_campus::text))::text || ' car.', max(length(inst_campus::text))::text || ' car.' FROM institucion
UNION ALL SELECT 'institucion.inst_sede', count(*), count(inst_sede), count(DISTINCT inst_sede),
       min(length(inst_sede::text))::text || ' car.', max(length(inst_sede::text))::text || ' car.' FROM institucion
UNION ALL SELECT 'solicitud_documento.sodo_url', count(*), count(sodo_url), count(DISTINCT sodo_url),
       min(length(sodo_url::text))::text || ' car.', max(length(sodo_url::text))::text || ' car.' FROM solicitud_documento;

\echo '   Referencias de las FK adicionales (valores sin correspondencia deben ser 0)'
SELECT 'conv_id_certificacion' AS atributo, c.conv_id_certificacion AS valor, count(*) AS convocatorias FROM convocatoria c
 WHERE c.conv_id_certificacion IS NOT NULL GROUP BY 2
UNION ALL SELECT 'espr_id_area_certificacion', e.espr_id_area_certificacion, count(*) FROM estudio_profesional e
 WHERE e.espr_id_area_certificacion IS NOT NULL GROUP BY 2 ORDER BY 1, 2;
SELECT area_id_area_certificacion AS id, area_descripcion FROM area_certificacion ORDER BY 1;

\echo '   Muestras de catálogo (sin datos personales): convocatorias, instituciones y tipos de convocatoria'
SELECT conv_id_convocatoria AS id, conv_nombre, conv_anio, conv_id_certificacion, conv_id_tipo_convocatoria FROM convocatoria ORDER BY 1 LIMIT 20;
SELECT inst_id_institucion AS id, inst_numero_afiliacion, left(inst_nombre, 50) AS nombre, left(inst_facultad, 40) AS facultad, inst_zona,
       left(inst_campus, 30) AS campus, left(inst_sede, 30) AS sede, left(inst_coord_regional_cert_academica, 40) AS coord_regional
  FROM institucion ORDER BY 1 LIMIT 25;
SELECT tico_id_tipo_convocatoria AS id, tico_tipo_convocatoria, length(tico_tipo_convocatoria) AS longitud, tico_siglas, tico_estado
  FROM tipo_convocatoria ORDER BY 1;
\echo '   Prefijos de sodo_url (sin el resto de la ruta) y relación con los archivos de expediente'
SELECT left(sodo_url, 25) AS prefijo, count(*) AS documentos FROM solicitud_documento WHERE sodo_url IS NOT NULL GROUP BY 1 ORDER BY 2 DESC LIMIT 10;

\echo '== 7. Viabilidad de volver al modelo oficial sin perder información'
SELECT 'institucion: filas con inst_numero_afiliacion nulo (impiden NOT NULL)' AS revision, count(*) AS casos FROM institucion WHERE inst_numero_afiliacion IS NULL
UNION ALL SELECT 'institucion: filas con inst_facultad nula (impiden NOT NULL)', count(*) FROM institucion WHERE inst_facultad IS NULL
UNION ALL SELECT 'institucion: filas con inst_zona nula (impiden NOT NULL)', count(*) FROM institucion WHERE inst_zona IS NULL
UNION ALL SELECT 'institucion: inst_numero_afiliacion con más de 7 caracteres', count(*) FROM institucion WHERE length(inst_numero_afiliacion) > 7
UNION ALL SELECT 'tipo_convocatoria: descripciones de más de 50 caracteres (impiden VARCHAR(50))', count(*) FROM tipo_convocatoria WHERE length(tico_tipo_convocatoria) > 50
UNION ALL SELECT 'institucion: inst_coord_regional_cert_academica de más de 200 caracteres', count(*) FROM institucion WHERE length(inst_coord_regional_cert_academica::text) > 200
UNION ALL SELECT 'institucion: existe el nombre ANTERIOR ins_coord_regional_cert_academi (debe ser 0; C-01)', count(*) FROM information_schema.columns
       WHERE table_schema = 'public' AND table_name = 'institucion' AND column_name = 'ins_coord_regional_cert_academi'
UNION ALL SELECT 'convocatoria: valores en atributos adicionales (se perderían al retirarlos)', count(*) FROM convocatoria WHERE conv_anio IS NOT NULL OR conv_id_certificacion IS NOT NULL
UNION ALL SELECT 'estudio_profesional: valores en espr_id_area_certificacion', count(*) FROM estudio_profesional WHERE espr_id_area_certificacion IS NOT NULL
UNION ALL SELECT 'evaluacion: valores en eval_folio_evaluacion', count(*) FROM evaluacion WHERE eval_folio_evaluacion IS NOT NULL
UNION ALL SELECT 'institucion: valores en inst_campus o inst_sede', count(*) FROM institucion WHERE inst_campus IS NOT NULL OR inst_sede IS NOT NULL
UNION ALL SELECT 'solicitud_documento: valores en sodo_url', count(*) FROM solicitud_documento WHERE sodo_url IS NOT NULL;

\else
\echo '== 6 y 7 omitidas: no existen los 8 atributos adicionales reportados (ver la sección 2 para las diferencias reales).'
\endif

\echo '== 8. Impacto en la aplicación de la rama integracion/fase9-8088'
\echo '   C-01 (autorizada): la aplicación consulta institucion.inst_coord_regional_cert_academica (Instituciones.php).'
\echo '   Si ese nombre no existe, la búsqueda de institución falla con SQLSTATE 42703 (columna inexistente).'
SELECT CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'institucion'
                          AND column_name = 'inst_coord_regional_cert_academica') THEN 'funciona (C-01 presente)' ELSE 'FALLA (42703)' END AS busqueda_de_institucion;

\echo '== 9. Propiedad, permisos y actividad de las tablas afectadas'
SELECT c.relname AS tabla, pg_get_userbyid(c.relowner) AS propietario, c.relacl AS permisos, s.n_tup_ins AS insertadas, s.n_tup_upd AS actualizadas,
       s.n_tup_del AS eliminadas, s.n_live_tup AS filas_vivas, s.last_autoanalyze::timestamp(0) AS ultimo_autoanalyze
  FROM pg_class c LEFT JOIN pg_stat_user_tables s ON s.relid = c.oid
 WHERE c.relnamespace = 'public'::regnamespace AND c.relname IN ('convocatoria', 'estudio_profesional', 'evaluacion', 'institucion',
       'solicitud_documento', 'tipo_convocatoria', 'area_certificacion') ORDER BY 1;

ROLLBACK;
\echo 'Diagnóstico terminado: transacción de solo lectura revertida; no se modificó nada.'
