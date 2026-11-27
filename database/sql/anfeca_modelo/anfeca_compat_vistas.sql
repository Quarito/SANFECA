-- Funciones auxiliares de lectura y vistas de la capa anfeca_compat
-- ---------------------------------------------------------------------
-- Fecha y hora locales del PDM (America/Mexico_City) → marca de tiempo en la zona de la aplicación
-- (config app.timezone, guardada en parametro.zona_app; UTC en ANFECA).
create function anfeca_compat.a_utc(p_fecha date, p_hora time) returns timestamp(0) language sql stable as $$
    select (((p_fecha + p_hora) at time zone 'America/Mexico_City')
            at time zone coalesce((select valor from anfeca_compat.parametro where clave = 'zona_app'), 'UTC'))::timestamp(0)
$$;

create function anfeca_compat.evento_utc(p_sol int, p_siglas text) returns timestamp(0) language sql stable as $$
    select anfeca_compat.a_utc(e.esso_fecha, e.esso_hora) from anfeca_modelo.estado_solicitud e
    where e.esso_id_solicitud = p_sol and e.esso_siglas = p_siglas order by e.esso_id__estado_solicitud desc limit 1
$$;

-- Estudio (origen o último grado) de una solicitud: nivel, título (catálogo u «Otro») y texto «Otro».
create function anfeca_compat.estudio(p_sol int, p_tipo text, p_dato text) returns varchar language sql stable as $$
    select case p_dato
        when 'nivel' then n.nipr_nivel_profesional
        when 'titulo' then case when e.espr_estado = 'ACT' then e.espr_nombre else 'Otro' end
        when 'otro' then case when e.espr_estado = 'REV' and e.espr_nombre <> 'Otro' then e.espr_nombre end
        when 'url_titulo' then p.pees_url_titulo
        when 'url_cedula' then p.pees_url_cedula
    end
    from anfeca_modelo.persona_estudio_profesional p
    join anfeca_modelo.estudio_profesional e on e.espr_id_estudio_profesional = p.pees_id_estuido_profesional
    join anfeca_modelo.nivel_profesional n on n.nipr_id_nivel_profesional = e.espr_id_nivel_profesional
    where p.pees_id_solicitud = p_sol and p.pees_tipo = p_tipo limit 1
$$;

-- ---------------------------------------------------------------------
-- Vistas (mismo nombre y orden de columnas que public)
-- ---------------------------------------------------------------------
create view anfeca_compat.datos_solicitantes as
select p.pers_id_persona::bigint as id,
    (select u.usua_id_usuario::bigint from anfeca_modelo.usuario u where u.usua_id_persona = p.pers_id_persona) as user_id,
    p.pers_curp::varchar(18) as curp,
    p.pers_rfc::varchar(13) as rfc,
    p.pers_cedula_profesional::varchar(30) as cedula_profesional,
    p.pers_nombre::varchar(150) as nombres,
    p.pers_apellido_paterno::varchar(100) as apellido_paterno,
    p.pers_apellido_materno::varchar(100) as apellido_materno,
    (select d.domi_calle::varchar(200) from anfeca_modelo.domicilio d where d.domi_id_persona = p.pers_id_persona) as calle,
    (select d.domi_numero_ext::varchar(20) from anfeca_modelo.domicilio d where d.domi_id_persona = p.pers_id_persona) as numero_exterior,
    (select d.domi_numero_int::varchar(20) from anfeca_modelo.domicilio d where d.domi_id_persona = p.pers_id_persona) as numero_interior,
    (select d.domi_codigo_postal::varchar(5) from anfeca_modelo.domicilio d where d.domi_id_persona = p.pers_id_persona) as codigo_postal,
    (select e.enfe_nombre::varchar(100) from anfeca_modelo.domicilio d
        join anfeca_modelo.entidad_federativa e on e.enfe_id_entidad_federativa = d.domi_id_entidad_federativa
        where d.domi_id_persona = p.pers_id_persona) as entidad_federativa,
    (select d.domi_colonia::varchar(150) from anfeca_modelo.domicilio d where d.domi_id_persona = p.pers_id_persona) as colonia,
    (select d.domi_municipio::varchar(150) from anfeca_modelo.domicilio d where d.domi_id_persona = p.pers_id_persona) as municipio,
    anfeca_compat.comunicacion(p.pers_id_persona, 'correo_electronico')::varchar(255) as correo_electronico,
    anfeca_compat.comunicacion(p.pers_id_persona, 'telefono_particular')::varchar(20) as telefono_particular,
    anfeca_compat.comunicacion(p.pers_id_persona, 'telefono_celular')::varchar(20) as telefono_celular,
    p.pers_creado_en::timestamp(0) as created_at,
    p.pers_actualizado_en::timestamp(0) as updated_at,
    p.pers_datos_autorizados as datos_autorizados,
    p.pers_fecha_autorizacion::timestamp(0) as fecha_autorizacion,
    (case p.pers_genero when 'F' then 'Femenino' when 'M' then 'Masculino' end)::varchar(50) as genero
from anfeca_modelo.persona p;

create view anfeca_compat.selecciones_certificacion as
select s.secc_id_seleccion::bigint as id, s.secc_id_usuario::bigint as user_id,
    s.secc_certificacion::varchar(20) as certificacion, s.secc_tipo_personal::varchar(100) as tipo_personal,
    s.secc_area_certificacion::varchar(150) as area_certificacion,
    s.secc_creado_en::timestamp(0) as created_at, s.secc_actualizado_en::timestamp(0) as updated_at
from anfeca_modelo.seleccion_certificacion s;

create view anfeca_compat.solicitudes_certificacion as
select s.soli_id_solicitud::bigint as id,
    (select u.usua_id_usuario::bigint from anfeca_modelo.usuario u where u.usua_id_persona = s.soli_id_persona) as user_id,
    s.soli_tipo_tramite::varchar(20) as tipo_tramite,
    s.soli_certificacion::varchar(20) as certificacion,
    (case when exists (select 1 from anfeca_modelo.estado_solicitud e where e.esso_id_solicitud = s.soli_id_solicitud and e.esso_siglas = 'EN')
        then 'enviado' else 'borrador' end)::varchar(20) as estado,
    anfeca_compat.evento_utc(s.soli_id_solicitud, 'EN') as fecha_envio,
    s.soli_creado_en::timestamp(0) as created_at,
    s.soli_actualizado_en::timestamp(0) as updated_at,
    s.soli_id_historico_importacion::bigint as certificado_historico_id,
    (select t.tipa_descripcion::varchar(100) from anfeca_modelo.tipo_personal_academico t
        where t.tipa_id_tipo_personal_academico = s.soli_id_tipo_personal_academico) as tipo_personal,
    (select a.area_descripcion::varchar(150) from anfeca_modelo.area_certificacion a
        where a.area_id_area_certificacion = s.soli_id_area_certificacion) as area_certificacion,
    exists (select 1 from anfeca_modelo.estado_solicitud e where e.esso_id_solicitud = s.soli_id_solicitud and e.esso_siglas = 'CA') as compromiso_aceptado,
    anfeca_compat.evento_utc(s.soli_id_solicitud, 'CA') as compromiso_aceptado_en
from anfeca_modelo.solicitud s;

create view anfeca_compat.datos_refrendo as
select r.sore_id_solicitud_refrendo::bigint as id, r.sore_id_solicitud::bigint as solicitud_id,
    r.sore_numero_refrendo_asignado::varchar(50) as numero_refrendo_asignado,
    r.sore_refrendo_actual as refrendo_actual,
    (select s.soli_art14 from anfeca_modelo.solicitud s where s.soli_id_solicitud = r.sore_id_solicitud) as aplica_articulo_14,
    (select a.arce_subindice::varchar(20) from anfeca_modelo.solicitud s
        join anfeca_modelo.articulo_subindice a on a.arce_id_articulo = s.soli_id_articulo
        where s.soli_id_solicitud = r.sore_id_solicitud) as inciso_articulo_14,
    r.sore_creado_en::timestamp(0) as created_at, r.sore_actualizado_en::timestamp(0) as updated_at
from anfeca_modelo.solicitud_refrendo r;

create view anfeca_compat.formaciones_academicas_solicitud as
select f.fosc_id_formacion::bigint as id, f.fosc_id_solicitud::bigint as solicitud_id,
    anfeca_compat.estudio(f.fosc_id_solicitud, 'ORIGEN', 'nivel') as nivel_origen,
    anfeca_compat.estudio(f.fosc_id_solicitud, 'ORIGEN', 'titulo') as titulo_origen,
    anfeca_compat.estudio(f.fosc_id_solicitud, 'ULTIMO', 'nivel') as nivel_ultimo_grado,
    anfeca_compat.estudio(f.fosc_id_solicitud, 'ULTIMO', 'titulo') as titulo_ultimo_grado,
    f.fosc_id_institucion::bigint as institucion_afiliada_id,
    anfeca_compat.estudio(f.fosc_id_solicitud, 'ORIGEN', 'url_titulo') as titulo_origen_archivo,
    anfeca_compat.estudio(f.fosc_id_solicitud, 'ORIGEN', 'url_cedula') as cedula_origen_archivo,
    anfeca_compat.estudio(f.fosc_id_solicitud, 'ULTIMO', 'url_titulo') as titulo_ultimo_grado_archivo,
    anfeca_compat.estudio(f.fosc_id_solicitud, 'ULTIMO', 'url_cedula') as cedula_ultimo_grado_archivo,
    f.fosc_creado_en::timestamp(0) as created_at, f.fosc_actualizado_en::timestamp(0) as updated_at,
    anfeca_compat.evento_utc(f.fosc_id_solicitud, 'FA') as formacion_completada_at,
    anfeca_compat.estudio(f.fosc_id_solicitud, 'ORIGEN', 'otro')::varchar(200) as titulo_origen_otro,
    anfeca_compat.estudio(f.fosc_id_solicitud, 'ULTIMO', 'otro')::varchar(200) as titulo_ultimo_grado_otro
from anfeca_modelo.formacion_solicitud f;

create view anfeca_compat.documentos_probatorios_solicitud as
select d.dosc_id_documentos::bigint as id, d.dosc_id_solicitud::bigint as solicitud_id,
    anfeca_compat.documento_vigente(d.dosc_id_solicitud, 'constancia_antiguedad_archivo')::varchar(255) as constancia_antiguedad_archivo,
    anfeca_compat.documento_vigente(d.dosc_id_solicitud, 'comprobante_pago_archivo')::varchar(255) as comprobante_pago_archivo,
    anfeca_compat.documento_vigente(d.dosc_id_solicitud, 'link')::text as link,
    d.dosc_creado_en::timestamp(0) as created_at, d.dosc_actualizado_en::timestamp(0) as updated_at,
    anfeca_compat.documento_vigente(d.dosc_id_solicitud, 'solicitud_articulo_14_archivo')::varchar(255) as solicitud_articulo_14_archivo,
    anfeca_compat.documento_vigente(d.dosc_id_solicitud, 'constancia_articulo_14_archivo')::varchar(255) as constancia_articulo_14_archivo
from anfeca_modelo.documentos_solicitud d;

-- Versiones anteriores de documentos académicos (el PDM conserva también las de origen).
create view anfeca_compat.historial_documentos_academicos as
select d.socu_id_documentacion::bigint as id, d.socu_id_solicitud::bigint as solicitud_id,
    (select m.campo::varchar(80) from anfeca_modelo.documento x join anfeca_compat.mapa_documento m on m.descripcion = x.docu_descripcion
        where x.docu_id_documento = d.socu_id_documento) as tipo_documento,
    d.socu_url_archivo::text as ruta_archivo,
    (select anfeca_compat.a_utc(e.esdo_fecha, e.esdo_hora) from anfeca_modelo.estado_solicitud_documento e
        where e.esdo_id_documentacion = d.socu_id_documentacion limit 1) as created_at,
    (select anfeca_compat.a_utc(e.esdo_fecha, e.esdo_hora) from anfeca_modelo.estado_solicitud_documento e
        where e.esdo_id_documentacion = d.socu_id_documentacion limit 1) as updated_at
from anfeca_modelo.solicitud_documento d
where exists (select 1 from anfeca_modelo.estado_solicitud_documento e
        join anfeca_modelo.estado_documento s on s.esdo_id_estado_documento = e.esdo_id_estado_documento
        where e.esdo_id_documentacion = d.socu_id_documentacion and s.esdo_estado_documento = 'Sustituido')
  and exists (select 1 from anfeca_modelo.documento x join anfeca_compat.mapa_documento m on m.descripcion = x.docu_descripcion
        where x.docu_id_documento = d.socu_id_documento
          and m.campo in ('titulo_origen_archivo', 'cedula_origen_archivo', 'titulo_ultimo_grado_archivo', 'cedula_ultimo_grado_archivo'));

create view anfeca_compat.certificados_historicos as
select h.hcim_id_importacion::bigint as id, h.hcim_zona as zona, h.hcim_nombre_historico as nombre_historico,
    h.hcim_correo_historico as correo_historico, h.hcim_apellido_paterno as apellido_paterno,
    h.hcim_apellido_materno as apellido_materno, h.hcim_nombres as nombres, h.hcim_curp_historica as curp_historica,
    h.hcim_institucion_historica as institucion_historica, h.hcim_certificacion as certificacion,
    h.hcim_primera_vez as primera_vez, h.hcim_refrendo_1 as refrendo_1, h.hcim_refrendo_2 as refrendo_2,
    h.hcim_refrendo_3 as refrendo_3, h.hcim_refrendo_4 as refrendo_4, h.hcim_refrendo_5 as refrendo_5,
    h.hcim_refrendo_6 as refrendo_6, h.hcim_refrendo_7 as refrendo_7,
    h.hcim_columna_sin_encabezado as columna_sin_encabezado, h.hcim_refrendo_correspondiente as refrendo_correspondiente,
    h.hcim_numero_certificado as numero_certificado, h.hcim_lote_importacion as lote_importacion,
    h.hcim_fila_origen as fila_origen, h.hcim_id_usuario::bigint as user_id,
    h.hcim_estado_vinculacion as estado_vinculacion,
    h.hcim_creado_en::timestamp(0) as created_at, h.hcim_actualizado_en::timestamp(0) as updated_at
from anfeca_modelo.historico_certificado_importacion h;

create view anfeca_compat.instituciones_afiliadas as
select i.inst_id_institucion::bigint as id, null::timestamp(0) as created_at, null::timestamp(0) as updated_at,
    i.inst_numero_afiliacion::varchar(255) as numero_afiliacion, i.inst_nombre::varchar(255) as nombre,
    (select s.sede_descripcion::varchar(255) from anfeca_modelo.sede s where s.inst_id_institucion = i.inst_id_institucion
        order by s.sede_id_sede limit 1) as sede,
    i.inst_zona::varchar(255) as zona_anfeca, i.inst_id_zona::bigint as zona_id, i.inst_campus::varchar(200) as campus,
    -- escuela_facultad ← INST_FACULTAD (varchar 500 en el PDM): sin truncar a 255.
    i.inst_facultad::varchar(500) as escuela_facultad
from anfeca_modelo.institucion i;
