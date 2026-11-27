-- Funciones compartidas por la instalación y la actualización de nombres del PDM.
-- CREATE OR REPLACE conserva las dependencias de las vistas y los triggers existentes.

create or replace function anfeca_compat.comunicacion(p_persona int, p_campo text) returns varchar language sql stable as $$
    select c.comu_valor from anfeca_modelo.comunicacion c
    join anfeca_modelo.tipo_comunicacion t on t.tico_id_tipo_comunicacion = c.comu_id_tipo_comunicacion
    join anfeca_compat.mapa_comunicacion m on m.descripcion = t.tico_tipo_comunicacion
    where c.comu_id_persona = p_persona and m.campo = p_campo limit 1
$$;

create or replace function anfeca_compat.documento_vigente(p_sol int, p_campo text) returns varchar language sql stable as $$
    select d.socu_url_archivo from anfeca_modelo.solicitud_documento d
    join anfeca_modelo.documento x on x.docu_id_documento = d.socu_id_documento
    join anfeca_compat.mapa_documento m on m.descripcion = x.docu_descripcion and m.campo = p_campo
    join anfeca_modelo.estado_solicitud_documento e on e.esdo_id_documentacion = d.socu_id_documentacion
    join anfeca_modelo.estado_documento s on s.esdo_id_estado_documento = e.esdo_id_estado_documento and s.esdo_estado_documento = 'Cargado'
    where d.socu_id_solicitud = p_sol order by d.socu_id_documentacion desc limit 1
$$;

create or replace function anfeca_compat.comunicacion_set(p_persona int, p_campo text, p_valor text) returns void language plpgsql as $$
declare v_tipo int;
begin
    select t.tico_id_tipo_comunicacion into v_tipo from anfeca_modelo.tipo_comunicacion t
    join anfeca_compat.mapa_comunicacion m on m.descripcion = t.tico_tipo_comunicacion where m.campo = p_campo;
    if anfeca_compat.vacio(p_valor) then
        delete from anfeca_modelo.comunicacion where comu_id_persona = p_persona and comu_id_tipo_comunicacion = v_tipo;
        return;
    end if;
    perform anfeca_compat.largo(p_valor, 150, p_campo);
    insert into anfeca_modelo.comunicacion (comu_id_persona, comu_id_tipo_comunicacion, comu_valor, comu_visible)
    values (p_persona, v_tipo, p_valor, null)
    on conflict (comu_id_persona, comu_id_tipo_comunicacion) do update set comu_valor = excluded.comu_valor;
end $$;

create or replace function anfeca_compat.documento_set(p_sol int, p_campo text, p_url text, p_marca timestamp) returns void language plpgsql as $$
declare
    v_doc int := anfeca_compat.documento_id(p_campo);
    v_cargado int := anfeca_compat.estado_documento_id('Cargado');
    v_sustituido int := anfeca_compat.estado_documento_id('Sustituido');
    v_local timestamp(0) := anfeca_compat.a_local(coalesce(p_marca, anfeca_compat.ahora_app()));
    v_id int;
begin
    if not anfeca_compat.vacio(p_url) and exists (
        select 1 from anfeca_modelo.solicitud_documento d
        join anfeca_modelo.estado_solicitud_documento e on e.esdo_id_documentacion = d.socu_id_documentacion
        where d.socu_id_solicitud = p_sol and d.socu_id_documento = v_doc and d.socu_url_archivo = p_url
          and e.esdo_id_estado_documento = v_cargado) then
        return; -- sin cambio
    end if;

    -- La versión vigente anterior (si la hay) se conserva como Sustituido.
    update anfeca_modelo.estado_solicitud_documento e
       set esdo_id_estado_documento = v_sustituido, esdo_observacion = 'Versión anterior conservada (historial)',
           esdo_fecha = v_local::date, esdo_hora = v_local::time(0)
     where e.esdo_id_estado_documento = v_cargado
       and e.esdo_id_documentacion in (select d.socu_id_documentacion from anfeca_modelo.solicitud_documento d
                                       where d.socu_id_solicitud = p_sol and d.socu_id_documento = v_doc);

    if anfeca_compat.vacio(p_url) then
        return;
    end if;
    perform anfeca_compat.largo(p_url, 500, p_campo);

    -- Misma ruta ya registrada como sustituida: se reactiva.
    select d.socu_id_documentacion into v_id from anfeca_modelo.solicitud_documento d
    where d.socu_id_solicitud = p_sol and d.socu_id_documento = v_doc and d.socu_url_archivo = p_url limit 1;
    if v_id is not null then
        update anfeca_modelo.estado_solicitud_documento set esdo_id_estado_documento = v_cargado, esdo_observacion = 'Archivo vigente'
        where esdo_id_documentacion = v_id;
        return;
    end if;

    insert into anfeca_modelo.solicitud_documento (socu_id_solicitud, socu_id_documento, socu_fecha, socu_hora, socu_url_archivo)
    values (p_sol, v_doc, v_local::date, v_local::time(0), p_url) returning socu_id_documentacion into v_id;
    insert into anfeca_modelo.estado_solicitud_documento (esdo_id_documentacion, esdo_id_estado_documento, esdo_observacion, esdo_fecha, esdo_hora)
    values (v_id, v_cargado, 'Archivo vigente', v_local::date, v_local::time(0));
end $$;

create or replace function anfeca_compat.tg_solicitudes_certificacion() returns trigger language plpgsql as $$
declare
    v_id bigint;
    v_persona int;
    v_tipo text := nullif(trim(new.tipo_personal), '');
    v_area text := nullif(trim(new.area_certificacion), '');
    v_seleccion record;
    v_numero text;
    v_creado timestamp(0) := coalesce(new.created_at, anfeca_compat.ahora_app());
begin
    if tg_op = 'DELETE' then
        perform anfeca_compat.error('pdm', 'No se permite eliminar solicitudes.');
    end if;
    if new.estado not in ('borrador', 'enviado') then
        perform anfeca_compat.error('solicitud', 'Estado de solicitud no válido.');
    end if;

    select u.usua_id_persona into v_persona from anfeca_modelo.usuario u where u.usua_id_usuario = new.user_id;
    if v_persona is null then
        perform anfeca_compat.error('certificacion', 'Primero registre y autorice sus datos personales.');
    end if;

    -- N-6: área y tipo de personal desde la selección del mismo usuario y certificación.
    if v_tipo is null or v_area is null then
        select * into v_seleccion from anfeca_modelo.seleccion_certificacion s
        where s.secc_id_usuario = new.user_id and s.secc_certificacion = new.certificacion;
        v_tipo := coalesce(v_tipo, v_seleccion.secc_tipo_personal);
        v_area := coalesce(v_area, v_seleccion.secc_area_certificacion);
    end if;

    select trim(h.hcim_numero_certificado) into v_numero from anfeca_modelo.historico_certificado_importacion h
    where h.hcim_id_importacion = new.certificado_historico_id and trim(h.hcim_numero_certificado) ~ '^[0-9]{1,7}$';

    if tg_op = 'INSERT' then
        v_id := coalesce(new.id, nextval(pg_get_serial_sequence('anfeca_modelo.solicitud', 'soli_id_solicitud')));
        insert into anfeca_modelo.solicitud (soli_id_solicitud, soli_id_persona, soli_id_area_certificacion, soli_id_tipo_personal_academico,
            soli_id_convocatoria, soli_id_articulo, soli_numero_certificado, soli_fecha_registro_solicitud, soli_folio_solicitud,
            soli_art13, soli_art14, soli_tipo_tramite, soli_certificacion, soli_id_historico_importacion, soli_creado_en, soli_actualizado_en)
        values (v_id, v_persona, anfeca_compat.area_id(v_area), anfeca_compat.tipo_personal_id(v_tipo), null, null, v_numero,
            anfeca_compat.a_local(v_creado)::date, v_id, false, false, new.tipo_tramite, new.certificacion,
            new.certificado_historico_id, new.created_at, new.updated_at);
        perform anfeca_compat.alinear_secuencia('anfeca_modelo.solicitud', 'soli_id_solicitud', v_id);
    else
        v_id := old.id;
        update anfeca_modelo.solicitud set soli_id_persona = v_persona,
            soli_id_area_certificacion = anfeca_compat.area_id(v_area), soli_id_tipo_personal_academico = anfeca_compat.tipo_personal_id(v_tipo),
            soli_numero_certificado = v_numero, soli_tipo_tramite = new.tipo_tramite, soli_certificacion = new.certificacion,
            soli_id_historico_importacion = new.certificado_historico_id, soli_creado_en = new.created_at, soli_actualizado_en = new.updated_at
        where soli_id_solicitud = v_id;
    end if;

    perform anfeca_compat.evento(v_id::int, 'BO', v_creado);
    if coalesce(new.compromiso_aceptado, false) then
        perform anfeca_compat.evento(v_id::int, 'CA', new.compromiso_aceptado_en);
    end if;
    if new.estado = 'enviado' then
        perform anfeca_compat.evento(v_id::int, 'EN', new.fecha_envio);
    end if;

    if anfeca_compat.replicar_public() then
        insert into public.solicitudes_certificacion (id, user_id, tipo_tramite, certificacion, estado, fecha_envio, created_at, updated_at,
            certificado_historico_id, tipo_personal, area_certificacion, compromiso_aceptado, compromiso_aceptado_en)
        values (v_id, new.user_id, new.tipo_tramite, new.certificacion, new.estado, new.fecha_envio, new.created_at, new.updated_at,
            new.certificado_historico_id, new.tipo_personal, new.area_certificacion, coalesce(new.compromiso_aceptado, false), new.compromiso_aceptado_en)
        on conflict (id) do update set user_id = excluded.user_id, tipo_tramite = excluded.tipo_tramite, certificacion = excluded.certificacion,
            estado = excluded.estado, fecha_envio = excluded.fecha_envio, created_at = excluded.created_at, updated_at = excluded.updated_at,
            certificado_historico_id = excluded.certificado_historico_id, tipo_personal = excluded.tipo_personal,
            area_certificacion = excluded.area_certificacion, compromiso_aceptado = excluded.compromiso_aceptado,
            compromiso_aceptado_en = excluded.compromiso_aceptado_en;
        perform anfeca_compat.alinear_secuencia('public.solicitudes_certificacion', 'id', v_id);
    end if;

    new.id := v_id;
    return new;
end $$;

create or replace function anfeca_compat.tg_datos_refrendo() returns trigger language plpgsql as $$
declare
    v_id bigint;
    v_tramite text;
    v_art14 boolean;
    v_articulo int;
begin
    if tg_op = 'DELETE' then
        perform anfeca_compat.error('pdm', 'No se permite eliminar datos de refrendo.');
    end if;
    perform anfeca_compat.solicitud_existente(new.solicitud_id);
    v_id := case tg_op when 'INSERT' then coalesce(new.id, nextval(pg_get_serial_sequence('anfeca_modelo.solicitud_refrendo', 'sore_id_solicitud_refrendo'))) else old.id end;

    insert into anfeca_modelo.solicitud_refrendo (sore_id_solicitud_refrendo, sore_id_solicitud, sore_numero_refrendo_asignado,
        sore_refrendo_actual, sore_creado_en, sore_actualizado_en)
    values (v_id, new.solicitud_id, new.numero_refrendo_asignado, new.refrendo_actual, new.created_at, new.updated_at)
    on conflict (sore_id_solicitud_refrendo) do update set sore_id_solicitud = excluded.sore_id_solicitud,
        sore_numero_refrendo_asignado = excluded.sore_numero_refrendo_asignado, sore_refrendo_actual = excluded.sore_refrendo_actual,
        sore_creado_en = excluded.sore_creado_en, sore_actualizado_en = excluded.sore_actualizado_en;
    perform anfeca_compat.alinear_secuencia('anfeca_modelo.solicitud_refrendo', 'sore_id_solicitud_refrendo', v_id);

    if new.inciso_articulo_14 is not null then
        select a.arce_id_articulo into v_articulo from anfeca_modelo.articulo_subindice a where a.arce_subindice = new.inciso_articulo_14;
        if v_articulo is null then
            perform anfeca_compat.error('inciso_articulo_14', 'El inciso no pertenece al catálogo.');
        end if;
    end if;
    select s.soli_tipo_tramite into v_tramite from anfeca_modelo.solicitud s where s.soli_id_solicitud = new.solicitud_id;
    v_art14 := v_tramite = 'refrendo' and coalesce(new.aplica_articulo_14, false);
    update anfeca_modelo.solicitud set soli_art14 = v_art14, soli_id_articulo = v_articulo,
        soli_art13 = (v_tramite = 'refrendo' and not v_art14)
    where soli_id_solicitud = new.solicitud_id;

    if anfeca_compat.replicar_public() then
        insert into public.datos_refrendo (id, solicitud_id, numero_refrendo_asignado, refrendo_actual, aplica_articulo_14, inciso_articulo_14, created_at, updated_at)
        values (v_id, new.solicitud_id, new.numero_refrendo_asignado, new.refrendo_actual, new.aplica_articulo_14, new.inciso_articulo_14, new.created_at, new.updated_at)
        on conflict (id) do update set solicitud_id = excluded.solicitud_id, numero_refrendo_asignado = excluded.numero_refrendo_asignado,
            refrendo_actual = excluded.refrendo_actual, aplica_articulo_14 = excluded.aplica_articulo_14,
            inciso_articulo_14 = excluded.inciso_articulo_14, created_at = excluded.created_at, updated_at = excluded.updated_at;
        perform anfeca_compat.alinear_secuencia('public.datos_refrendo', 'id', v_id);
    end if;

    new.id := v_id;
    return new;
end $$;

create or replace function anfeca_compat.tg_historial_documentos_academicos() returns trigger language plpgsql as $$
declare
    v_doc int;
    v_id int;
    v_local timestamp(0) := anfeca_compat.a_local(coalesce(new.created_at, anfeca_compat.ahora_app()));
    v_sustituido int := anfeca_compat.estado_documento_id('Sustituido');
begin
    if tg_op <> 'INSERT' then
        perform anfeca_compat.error('pdm', 'El historial de documentos no se modifica ni se elimina.');
    end if;
    perform anfeca_compat.solicitud_existente(new.solicitud_id);
    v_doc := anfeca_compat.documento_id(new.tipo_documento);
    perform anfeca_compat.largo(new.ruta_archivo, 500, 'documentos');

    select d.socu_id_documentacion into v_id from anfeca_modelo.solicitud_documento d
    where d.socu_id_solicitud = new.solicitud_id and d.socu_id_documento = v_doc and d.socu_url_archivo = new.ruta_archivo
    order by d.socu_id_documentacion desc limit 1;

    if v_id is null then
        insert into anfeca_modelo.solicitud_documento (socu_id_solicitud, socu_id_documento, socu_fecha, socu_hora, socu_url_archivo)
        values (new.solicitud_id, v_doc, v_local::date, v_local::time(0), new.ruta_archivo) returning socu_id_documentacion into v_id;
        insert into anfeca_modelo.estado_solicitud_documento (esdo_id_documentacion, esdo_id_estado_documento, esdo_observacion, esdo_fecha, esdo_hora)
        values (v_id, v_sustituido, 'Versión anterior conservada (historial)', v_local::date, v_local::time(0));
    else
        update anfeca_modelo.estado_solicitud_documento set esdo_id_estado_documento = v_sustituido,
            esdo_observacion = 'Versión anterior conservada (historial)', esdo_fecha = v_local::date, esdo_hora = v_local::time(0)
        where esdo_id_documentacion = v_id;
    end if;

    if anfeca_compat.replicar_public() then
        insert into public.historial_documentos_academicos (solicitud_id, tipo_documento, ruta_archivo, created_at, updated_at)
        values (new.solicitud_id, new.tipo_documento, new.ruta_archivo, new.created_at, new.updated_at);
    end if;

    new.id := v_id;
    return new;
end $$;
