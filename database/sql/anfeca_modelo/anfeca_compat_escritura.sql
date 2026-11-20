-- =====================================================================
-- ANFECA — Capa de COMPATIBILIDAD: escritura (F4 principal / F5 exclusivo)
--
-- Disparadores INSTEAD OF sobre las vistas de anfeca_compat. Aplican las MISMAS reglas que
-- PlanMigracionPdm (F2) y AplicadorPdm (F3), de modo que la conciliación las verifica:
--   * modo principal  → escriben en anfeca_modelo Y replican en public (respaldo para revertir).
--   * modo exclusivo  → escriben solo en anfeca_modelo; public queda congelado.
-- Errores de datos: RAISE 'ANFECA_VALIDACION|campo|mensaje' (Laravel los muestra en el campo).
-- No se permite DELETE sobre ninguna vista (el historial nunca se borra).
-- =====================================================================

-- ---------------------------------------------------------------------
-- Auxiliares
-- ---------------------------------------------------------------------
create function anfeca_compat.modo() returns text language sql stable as $$
    select coalesce((select ic_modo from anfeca_modelo.integracion_control where ic_id = 1), 'apagado')
$$;

create function anfeca_compat.replicar_public() returns boolean language sql stable as $$
    select anfeca_compat.modo() = 'principal'
$$;

create function anfeca_compat.error(p_campo text, p_mensaje text) returns void language plpgsql as $$
begin
    raise exception using errcode = 'P0001', message = 'ANFECA_VALIDACION|' || p_campo || '|' || p_mensaje;
end $$;

-- Regla N-1: minúsculas, sin acentos ni espacios repetidos.
create function anfeca_compat.clave(p text) returns text language sql immutable as $$
    select translate(lower(regexp_replace(trim(coalesce(p, '')), '\s+', ' ', 'g')), 'áéíóúüñ', 'aeiouun')
$$;

create function anfeca_compat.vacio(p text) returns boolean language sql immutable as $$
    select coalesce(trim(p), '') = ''
$$;

create function anfeca_compat.largo(p text, p_limite int, p_campo text) returns void language plpgsql as $$
begin
    if p is not null and char_length(p) > p_limite then
        perform anfeca_compat.error(p_campo, 'Este dato admite como máximo ' || p_limite || ' caracteres.');
    end if;
end $$;

-- Marca de tiempo de Laravel (zona de la aplicación) → fecha y hora locales del PDM.
create function anfeca_compat.a_local(p timestamp) returns timestamp(0) language sql stable as $$
    select ((p at time zone coalesce((select valor from anfeca_compat.parametro where clave = 'zona_app'), 'UTC'))
            at time zone 'America/Mexico_City')::timestamp(0)
$$;

-- Ahora, en la zona de la aplicación (para fechas no proporcionadas).
create function anfeca_compat.ahora_app() returns timestamp(0) language sql stable as $$
    select (now() at time zone coalesce((select valor from anfeca_compat.parametro where clave = 'zona_app'), 'UTC'))::timestamp(0)
$$;

create function anfeca_compat.alinear_secuencia(p_tabla text, p_columna text, p_id bigint) returns void language plpgsql as $$
declare
    v_secuencia text := pg_get_serial_sequence(p_tabla, p_columna);
    v_ultimo bigint;
begin
    if v_secuencia is null or p_id is null then
        return;
    end if;
    execute format('select last_value from %s', v_secuencia) into v_ultimo;
    if p_id > v_ultimo then
        perform setval(v_secuencia, p_id);
    end if;
end $$;

-- Reglas N-1/N-2/N-3: entidad federativa del catálogo INEGI o error en el campo.
create function anfeca_compat.entidad_id(p text) returns int language plpgsql stable as $$
declare
    v_clave text := anfeca_compat.clave(p);
    v_id int;
    v_alias record;
begin
    if v_clave <> '' then
        select e.enfe_id_entidad_federativa into v_id from anfeca_modelo.entidad_federativa e
        where anfeca_compat.clave(e.enfe_nombre) = v_clave;
        if v_id is not null then
            return v_id;
        end if;
        select * into v_alias from anfeca_compat.alias_entidad a where a.clave = v_clave;
        if found and v_alias.entidad is not null then
            select e.enfe_id_entidad_federativa into v_id from anfeca_modelo.entidad_federativa e where e.enfe_nombre = v_alias.entidad;
            return v_id;
        end if;
    end if;
    perform anfeca_compat.error('entidad_federativa', 'Escriba el nombre de una entidad federativa de México (por ejemplo, «Nuevo León» o «Ciudad de México»).');
    return null;
end $$;

create function anfeca_compat.area_id(p text) returns int language plpgsql stable as $$
declare v_id int;
begin
    select a.area_id_area_certificacion into v_id from anfeca_modelo.area_certificacion a
    where anfeca_compat.clave(a.area_descripcion) = anfeca_compat.clave(p);
    if v_id is null then
        perform anfeca_compat.error('area_certificacion', 'Seleccione un área de certificación del catálogo.');
    end if;
    return v_id;
end $$;

create function anfeca_compat.tipo_personal_id(p text) returns int language plpgsql stable as $$
declare v_id int;
begin
    select t.tipa_id_tipo_personal_academico into v_id from anfeca_modelo.tipo_personal_academico t
    where anfeca_compat.clave(t.tipa_descripcion) = anfeca_compat.clave(p);
    if v_id is null then
        perform anfeca_compat.error('tipo_personal', 'Seleccione un tipo de personal académico del catálogo.');
    end if;
    return v_id;
end $$;

create function anfeca_compat.rol_id(p_nombre text) returns int language plpgsql as $$
declare v_id int;
begin
    select r.rol_id_rol into v_id from anfeca_modelo.rol r where r.rol_nombre = p_nombre;
    if v_id is null then
        insert into anfeca_modelo.rol (rol_nombre) values (p_nombre) returning rol_id_rol into v_id;
    end if;
    return v_id;
end $$;

create function anfeca_compat.documento_id(p_campo text) returns int language plpgsql stable as $$
declare v_id int;
begin
    select x.docu_id_documento into v_id from anfeca_modelo.documento x
    join anfeca_compat.mapa_documento m on m.descripcion = x.docu_descripcion where m.campo = p_campo;
    if v_id is null then
        perform anfeca_compat.error('pdm', 'Tipo de documento fuera del catálogo: ' || p_campo);
    end if;
    return v_id;
end $$;

create function anfeca_compat.estado_documento_id(p_nombre text) returns int language sql stable as $$
    select e.esdo_id_estado_documento from anfeca_modelo.estado_documento e where e.esdo_estado_documento = p_nombre
$$;

-- Evento de la solicitud (BO, FA, CA, EN) en fecha y hora locales; uno por tipo.
create function anfeca_compat.evento(p_sol int, p_siglas text, p_marca timestamp) returns void language plpgsql as $$
declare
    v_local timestamp(0);
    v_descripcion text;
begin
    if p_marca is null or exists (select 1 from anfeca_modelo.estado_solicitud e where e.esso_id_solicitud = p_sol and e.esso_siglas = p_siglas) then
        return;
    end if;
    v_local := anfeca_compat.a_local(p_marca);
    select m.descripcion into v_descripcion from anfeca_compat.mapa_estado_solicitud m where m.siglas = p_siglas;
    insert into anfeca_modelo.estado_solicitud (esso_id_c_estado_solicitud, esso_id_solicitud, esso_descripcion, esso_siglas, esso_fecha, esso_hora, esso_motivo_rechazo)
    values ((select c.ceso_id_c_estado_solicitud from anfeca_modelo.c_estado_solicitud c where c.ceso_estado_solicitud = v_descripcion),
            p_sol, v_descripcion, p_siglas, v_local::date, v_local::time(0), null);
end $$;

create function anfeca_compat.comunicacion_set(p_persona int, p_campo text, p_valor text) returns void language plpgsql as $$
declare v_tipo int;
begin
    select t.tico_id_tipo_comunicacion into v_tipo from anfeca_modelo.tipo_comunicacion t
    join anfeca_compat.mapa_comunicacion m on m.descripcion = t.tico_tipo_comunicacion where m.campo = p_campo;
    if anfeca_compat.vacio(p_valor) then
        delete from anfeca_modelo.comunicacion where cumu_id_persona = p_persona and comu_id_tipo_comunicacion = v_tipo;
        return;
    end if;
    perform anfeca_compat.largo(p_valor, 150, p_campo);
    insert into anfeca_modelo.comunicacion (cumu_id_persona, comu_id_tipo_comunicacion, comu_valor, comu_visible)
    values (p_persona, v_tipo, p_valor, null)
    on conflict (cumu_id_persona, comu_id_tipo_comunicacion) do update set comu_valor = excluded.comu_valor;
end $$;

-- Documento vigente: si cambia la ruta, la versión anterior pasa a «Sustituido» (nunca se borra).
create function anfeca_compat.documento_set(p_sol int, p_campo text, p_url text, p_marca timestamp) returns void language plpgsql as $$
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
        where d.socu_id_solicitud = p_sol and d.docu_id_documento = v_doc and d.socu_url_archivo = p_url
          and e.esdo_id_estado_documento = v_cargado) then
        return; -- sin cambio
    end if;

    -- La versión vigente anterior (si la hay) se conserva como Sustituido.
    update anfeca_modelo.estado_solicitud_documento e
       set esdo_id_estado_documento = v_sustituido, esdo_observacion = 'Versión anterior conservada (historial)',
           esdo_fecha = v_local::date, esdo_hora = v_local::time(0)
     where e.esdo_id_estado_documento = v_cargado
       and e.esdo_id_documentacion in (select d.socu_id_documentacion from anfeca_modelo.solicitud_documento d
                                       where d.socu_id_solicitud = p_sol and d.docu_id_documento = v_doc);

    if anfeca_compat.vacio(p_url) then
        return;
    end if;
    perform anfeca_compat.largo(p_url, 500, p_campo);

    -- Misma ruta ya registrada como sustituida: se reactiva.
    select d.socu_id_documentacion into v_id from anfeca_modelo.solicitud_documento d
    where d.socu_id_solicitud = p_sol and d.docu_id_documento = v_doc and d.socu_url_archivo = p_url limit 1;
    if v_id is not null then
        update anfeca_modelo.estado_solicitud_documento set esdo_id_estado_documento = v_cargado, esdo_observacion = 'Archivo vigente'
        where esdo_id_documentacion = v_id;
        return;
    end if;

    insert into anfeca_modelo.solicitud_documento (socu_id_solicitud, docu_id_documento, socu_fecha, socu_hora, socu_url_archivo)
    values (p_sol, v_doc, v_local::date, v_local::time(0), p_url) returning socu_id_documentacion into v_id;
    insert into anfeca_modelo.estado_solicitud_documento (esdo_id_documentacion, esdo_id_estado_documento, esdo_observacion, esdo_fecha, esdo_hora)
    values (v_id, v_cargado, 'Archivo vigente', v_local::date, v_local::time(0));
end $$;

-- Estudio de un trámite (ORIGEN o ULTIMO). «Otro» o títulos fuera de catálogo quedan en revisión (REV).
create function anfeca_compat.estudio_set(p_sol int, p_persona int, p_tipo text, p_nivel text, p_titulo text, p_otro text,
                                          p_url_titulo text, p_url_cedula text) returns void language plpgsql as $$
declare
    v_nivel int;
    v_nombre text;
    v_estado text;
    v_estudio int;
    v_campo_otro text := case p_tipo when 'ORIGEN' then 'titulo_origen_otro' else 'titulo_ultimo_grado_otro' end;
begin
    if anfeca_compat.vacio(p_nivel) or anfeca_compat.vacio(p_titulo) then
        delete from anfeca_modelo.persona_estudio_profesional where pees_id_solicitud = p_sol and pees_tipo = p_tipo;
        return;
    end if;

    select n.nipr_id_nivel_profesional into v_nivel from anfeca_modelo.nivel_profesional n where n.nipr_nivel_profesional = p_nivel;
    if v_nivel is null then
        perform anfeca_compat.error(case p_tipo when 'ORIGEN' then 'nivel_origen' else 'nivel_ultimo_grado' end, 'Seleccione un nivel del catálogo.');
    end if;

    if p_titulo = 'Otro' then
        v_nombre := coalesce(nullif(trim(p_otro), ''), 'Otro');
        v_estado := 'REV';
    elsif exists (select 1 from anfeca_modelo.estudio_profesional e where e.espr_id_nivel_profesional = v_nivel
                  and e.espr_nombre = p_titulo and e.espr_estado = 'ACT') then
        v_nombre := p_titulo;
        v_estado := 'ACT';
    else
        v_nombre := p_titulo;
        v_estado := 'REV';
    end if;

    perform anfeca_compat.largo(v_nombre, 150, v_campo_otro);
    perform anfeca_compat.largo(p_url_titulo, 300, 'documentos');
    perform anfeca_compat.largo(p_url_cedula, 180, 'documentos');

    select e.espr_id_estudio_profesional into v_estudio from anfeca_modelo.estudio_profesional e
    where e.espr_id_nivel_profesional = v_nivel and e.espr_nombre = v_nombre;
    if v_estudio is null then
        insert into anfeca_modelo.estudio_profesional (espr_id_nivel_profesional, espr_nombre, espr_estado)
        values (v_nivel, v_nombre, v_estado) returning espr_id_estudio_profesional into v_estudio;
    end if;

    insert into anfeca_modelo.persona_estudio_profesional (pees_id_estuido_profesional, pees_id_persona, pees_url_titulo, pees_cedula, pees_url_cedula, pees_id_solicitud, pees_tipo)
    values (v_estudio, p_persona, p_url_titulo, null, p_url_cedula, p_sol, p_tipo)
    on conflict (pees_id_solicitud, pees_tipo) do update
       set pees_id_estuido_profesional = excluded.pees_id_estuido_profesional, pees_id_persona = excluded.pees_id_persona,
           pees_url_titulo = excluded.pees_url_titulo, pees_url_cedula = excluded.pees_url_cedula;
end $$;

create function anfeca_compat.solicitud_existente(p_sol bigint) returns int language plpgsql stable as $$
declare v_persona int;
begin
    select s.soli_id_persona into v_persona from anfeca_modelo.solicitud s where s.soli_id_solicitud = p_sol;
    if v_persona is null then
        perform anfeca_compat.error('pdm', 'La solicitud no existe en el modelo institucional.');
    end if;
    return v_persona;
end $$;

-- ---------------------------------------------------------------------
-- datos_solicitantes → PERSONA, USUARIO (técnico 1:1), DOMICILIO, COMUNICACION
-- ---------------------------------------------------------------------
create function anfeca_compat.tg_datos_solicitantes() returns trigger language plpgsql as $$
declare
    v_id bigint;
    v_genero varchar(1);
    v_entidad int;
    v_usuario record;
begin
    if tg_op = 'DELETE' then
        perform anfeca_compat.error('pdm', 'No se permite eliminar datos personales.');
    end if;
    if anfeca_compat.vacio(new.curp) then
        perform anfeca_compat.error('curp', 'La CURP es obligatoria.');
    end if;
    perform anfeca_compat.largo(new.nombres, 150, 'nombres');
    perform anfeca_compat.largo(new.apellido_paterno, 60, 'apellido_paterno');
    perform anfeca_compat.largo(new.apellido_materno, 60, 'apellido_materno');
    perform anfeca_compat.largo(new.rfc, 13, 'rfc');
    perform anfeca_compat.largo(new.cedula_profesional, 30, 'cedula_profesional');
    perform anfeca_compat.largo(new.calle, 170, 'calle');
    perform anfeca_compat.largo(new.numero_exterior, 6, 'numero_exterior');
    perform anfeca_compat.largo(new.numero_interior, 4, 'numero_interior');

    v_genero := case
        when anfeca_compat.vacio(new.genero) then null
        when anfeca_compat.clave(new.genero) = 'femenino' then 'F'
        when anfeca_compat.clave(new.genero) = 'masculino' then 'M'
    end;
    if v_genero is null and not anfeca_compat.vacio(new.genero) then
        perform anfeca_compat.error('genero', 'Seleccione un género válido.');
    end if;
    v_entidad := anfeca_compat.entidad_id(new.entidad_federativa);

    if tg_op = 'INSERT' then
        v_id := coalesce(new.id, nextval(pg_get_serial_sequence('anfeca_modelo.persona', 'pers_id_persona')));
        insert into anfeca_modelo.persona (pers_id_persona, pers_id_institucion, pers_nombre, pers_apellido_paterno, pers_apellido_materno,
            pers_curp, pers_rfc, pers_genero, pers_num_afiliacion_institucion, pers_activo, pers_cedula_profesional,
            pers_datos_autorizados, pers_fecha_autorizacion, pers_creado_en, pers_actualizado_en)
        values (v_id, null, new.nombres, new.apellido_paterno, new.apellido_materno, new.curp, new.rfc, v_genero, null, true,
            new.cedula_profesional, coalesce(new.datos_autorizados, false), new.fecha_autorizacion, new.created_at, new.updated_at);
        perform anfeca_compat.alinear_secuencia('anfeca_modelo.persona', 'pers_id_persona', v_id);
    else
        v_id := old.id;
        update anfeca_modelo.persona set pers_nombre = new.nombres, pers_apellido_paterno = new.apellido_paterno,
            pers_apellido_materno = new.apellido_materno, pers_curp = new.curp, pers_rfc = new.rfc, pers_genero = v_genero,
            pers_cedula_profesional = new.cedula_profesional, pers_datos_autorizados = coalesce(new.datos_autorizados, false),
            pers_fecha_autorizacion = new.fecha_autorizacion, pers_creado_en = new.created_at, pers_actualizado_en = new.updated_at
        where pers_id_persona = v_id;
    end if;

    -- Usuario técnico (D-1 A): mismo ID, correo y hash que public.users.
    if not exists (select 1 from anfeca_modelo.usuario u where u.usua_id_persona = v_id) then
        select * into v_usuario from public.users x where x.id = new.user_id;
        if not found then
            perform anfeca_compat.error('pdm', 'La cuenta de acceso no existe.');
        end if;
        perform anfeca_compat.largo(v_usuario.email, 150, 'correo_electronico');
        insert into anfeca_modelo.usuario (usua_id_usuario, usua_id_persona, usua_id_rol, usua_nombre, usua_contrasenia, usua_estatus)
        values (v_usuario.id, v_id, anfeca_compat.rol_id(v_usuario.rol), v_usuario.email, v_usuario.password, 'ACTIVO');
    end if;

    insert into anfeca_modelo.domicilio (domi_id_persona, domi_id_entidad_federativa, domi_calle, domi_numero_ext, domi_numero_int,
        domi_colonia, domi_municipio, domi_codigo_postal)
    values (v_id, v_entidad, new.calle, new.numero_exterior, new.numero_interior, new.colonia, new.municipio, new.codigo_postal)
    on conflict (domi_id_persona) do update set domi_id_entidad_federativa = excluded.domi_id_entidad_federativa,
        domi_calle = excluded.domi_calle, domi_numero_ext = excluded.domi_numero_ext, domi_numero_int = excluded.domi_numero_int,
        domi_colonia = excluded.domi_colonia, domi_municipio = excluded.domi_municipio, domi_codigo_postal = excluded.domi_codigo_postal;

    perform anfeca_compat.comunicacion_set(v_id::int, 'telefono_particular', new.telefono_particular);
    perform anfeca_compat.comunicacion_set(v_id::int, 'telefono_celular', new.telefono_celular);
    perform anfeca_compat.comunicacion_set(v_id::int, 'correo_electronico', new.correo_electronico);

    if anfeca_compat.replicar_public() then
        insert into public.datos_solicitantes (id, user_id, curp, rfc, cedula_profesional, nombres, apellido_paterno, apellido_materno,
            calle, numero_exterior, numero_interior, codigo_postal, entidad_federativa, colonia, municipio, correo_electronico,
            telefono_particular, telefono_celular, created_at, updated_at, datos_autorizados, fecha_autorizacion, genero)
        values (v_id, new.user_id, new.curp, new.rfc, new.cedula_profesional, new.nombres, new.apellido_paterno, new.apellido_materno,
            new.calle, new.numero_exterior, new.numero_interior, new.codigo_postal, new.entidad_federativa, new.colonia, new.municipio,
            new.correo_electronico, new.telefono_particular, new.telefono_celular, new.created_at, new.updated_at,
            coalesce(new.datos_autorizados, false), new.fecha_autorizacion, new.genero)
        on conflict (id) do update set user_id = excluded.user_id, curp = excluded.curp, rfc = excluded.rfc,
            cedula_profesional = excluded.cedula_profesional, nombres = excluded.nombres, apellido_paterno = excluded.apellido_paterno,
            apellido_materno = excluded.apellido_materno, calle = excluded.calle, numero_exterior = excluded.numero_exterior,
            numero_interior = excluded.numero_interior, codigo_postal = excluded.codigo_postal, entidad_federativa = excluded.entidad_federativa,
            colonia = excluded.colonia, municipio = excluded.municipio, correo_electronico = excluded.correo_electronico,
            telefono_particular = excluded.telefono_particular, telefono_celular = excluded.telefono_celular,
            created_at = excluded.created_at, updated_at = excluded.updated_at, datos_autorizados = excluded.datos_autorizados,
            fecha_autorizacion = excluded.fecha_autorizacion, genero = excluded.genero;
        perform anfeca_compat.alinear_secuencia('public.datos_solicitantes', 'id', v_id);
    end if;

    new.id := v_id;
    return new;
end $$;

create trigger tg_datos_solicitantes instead of insert or update or delete on anfeca_compat.datos_solicitantes
    for each row execute function anfeca_compat.tg_datos_solicitantes();

-- ---------------------------------------------------------------------
-- selecciones_certificacion → SELECCION_CERTIFICACION
-- ---------------------------------------------------------------------
create function anfeca_compat.tg_selecciones_certificacion() returns trigger language plpgsql as $$
declare v_id bigint;
begin
    if tg_op = 'DELETE' then
        perform anfeca_compat.error('pdm', 'No se permite eliminar la selección de certificación.');
    end if;
    if not exists (select 1 from anfeca_modelo.usuario u where u.usua_id_usuario = new.user_id) then
        perform anfeca_compat.error('certificacion', 'Primero registre sus datos personales.');
    end if;
    v_id := case tg_op when 'INSERT' then coalesce(new.id, nextval(pg_get_serial_sequence('anfeca_modelo.seleccion_certificacion', 'secc_id_seleccion'))) else old.id end;

    insert into anfeca_modelo.seleccion_certificacion (secc_id_seleccion, secc_id_usuario, secc_certificacion, secc_tipo_personal,
        secc_area_certificacion, secc_creado_en, secc_actualizado_en)
    values (v_id, new.user_id, new.certificacion, new.tipo_personal, new.area_certificacion, new.created_at, new.updated_at)
    on conflict (secc_id_seleccion) do update set secc_id_usuario = excluded.secc_id_usuario, secc_certificacion = excluded.secc_certificacion,
        secc_tipo_personal = excluded.secc_tipo_personal, secc_area_certificacion = excluded.secc_area_certificacion,
        secc_creado_en = excluded.secc_creado_en, secc_actualizado_en = excluded.secc_actualizado_en;
    perform anfeca_compat.alinear_secuencia('anfeca_modelo.seleccion_certificacion', 'secc_id_seleccion', v_id);

    if anfeca_compat.replicar_public() then
        insert into public.selecciones_certificacion (id, user_id, certificacion, tipo_personal, area_certificacion, created_at, updated_at)
        values (v_id, new.user_id, new.certificacion, new.tipo_personal, new.area_certificacion, new.created_at, new.updated_at)
        on conflict (id) do update set user_id = excluded.user_id, certificacion = excluded.certificacion, tipo_personal = excluded.tipo_personal,
            area_certificacion = excluded.area_certificacion, created_at = excluded.created_at, updated_at = excluded.updated_at;
        perform anfeca_compat.alinear_secuencia('public.selecciones_certificacion', 'id', v_id);
    end if;

    new.id := v_id;
    return new;
end $$;

create trigger tg_selecciones_certificacion instead of insert or update or delete on anfeca_compat.selecciones_certificacion
    for each row execute function anfeca_compat.tg_selecciones_certificacion();

-- ---------------------------------------------------------------------
-- solicitudes_certificacion → SOLICITUD + eventos BO / CA / EN
-- ---------------------------------------------------------------------
create function anfeca_compat.tg_solicitudes_certificacion() returns trigger language plpgsql as $$
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
            solo_art13, soli_art14, soli_tipo_tramite, soli_certificacion, soli_id_historico_importacion, soli_creado_en, soli_actualizado_en)
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

create trigger tg_solicitudes_certificacion instead of insert or update or delete on anfeca_compat.solicitudes_certificacion
    for each row execute function anfeca_compat.tg_solicitudes_certificacion();

-- ---------------------------------------------------------------------
-- datos_refrendo → SOLICITUD_REFRENDO + SOLICITUD (art. 13/14, inciso)
-- ---------------------------------------------------------------------
create function anfeca_compat.tg_datos_refrendo() returns trigger language plpgsql as $$
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
        solo_art13 = (v_tramite = 'refrendo' and not v_art14)
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

create trigger tg_datos_refrendo instead of insert or update or delete on anfeca_compat.datos_refrendo
    for each row execute function anfeca_compat.tg_datos_refrendo();

-- ---------------------------------------------------------------------
-- formaciones_academicas_solicitud → FORMACION_SOLICITUD + PERSONA_ESTUDIO_PROFESIONAL
--   + documentos académicos (historial) + evento FA + institución de la persona
-- ---------------------------------------------------------------------
create function anfeca_compat.tg_formaciones_academicas_solicitud() returns trigger language plpgsql as $$
declare
    v_id bigint;
    v_persona int;
begin
    if tg_op = 'DELETE' then
        perform anfeca_compat.error('pdm', 'No se permite eliminar la formación académica.');
    end if;
    v_persona := anfeca_compat.solicitud_existente(new.solicitud_id);
    if new.institucion_afiliada_id is not null
       and not exists (select 1 from anfeca_modelo.institucion i where i.inst_id_institucion = new.institucion_afiliada_id) then
        perform anfeca_compat.error('institucion_afiliada_id', 'La institución no está registrada en el modelo institucional.');
    end if;
    v_id := case tg_op when 'INSERT' then coalesce(new.id, nextval(pg_get_serial_sequence('anfeca_modelo.formacion_solicitud', 'fosc_id_formacion'))) else old.id end;

    insert into anfeca_modelo.formacion_solicitud (fosc_id_formacion, fosc_id_solicitud, fosc_id_institucion, fosc_creado_en, fosc_actualizado_en)
    values (v_id, new.solicitud_id, new.institucion_afiliada_id, new.created_at, new.updated_at)
    on conflict (fosc_id_formacion) do update set fosc_id_solicitud = excluded.fosc_id_solicitud, fosc_id_institucion = excluded.fosc_id_institucion,
        fosc_creado_en = excluded.fosc_creado_en, fosc_actualizado_en = excluded.fosc_actualizado_en;
    perform anfeca_compat.alinear_secuencia('anfeca_modelo.formacion_solicitud', 'fosc_id_formacion', v_id);

    -- Institución de la persona = la de su formación más reciente.
    update anfeca_modelo.persona set pers_id_institucion = (
        select f.fosc_id_institucion from anfeca_modelo.formacion_solicitud f
        join anfeca_modelo.solicitud s on s.soli_id_solicitud = f.fosc_id_solicitud
        where s.soli_id_persona = v_persona and f.fosc_id_institucion is not null
        order by s.soli_id_solicitud desc limit 1)
    where pers_id_persona = v_persona;

    perform anfeca_compat.estudio_set(new.solicitud_id::int, v_persona, 'ORIGEN', new.nivel_origen, new.titulo_origen, new.titulo_origen_otro,
        new.titulo_origen_archivo, new.cedula_origen_archivo);
    perform anfeca_compat.estudio_set(new.solicitud_id::int, v_persona, 'ULTIMO', new.nivel_ultimo_grado, new.titulo_ultimo_grado,
        new.titulo_ultimo_grado_otro, new.titulo_ultimo_grado_archivo, new.cedula_ultimo_grado_archivo);

    perform anfeca_compat.documento_set(new.solicitud_id::int, 'titulo_origen_archivo', new.titulo_origen_archivo, new.updated_at);
    perform anfeca_compat.documento_set(new.solicitud_id::int, 'cedula_origen_archivo', new.cedula_origen_archivo, new.updated_at);
    perform anfeca_compat.documento_set(new.solicitud_id::int, 'titulo_ultimo_grado_archivo', new.titulo_ultimo_grado_archivo, new.updated_at);
    perform anfeca_compat.documento_set(new.solicitud_id::int, 'cedula_ultimo_grado_archivo', new.cedula_ultimo_grado_archivo, new.updated_at);

    perform anfeca_compat.evento(new.solicitud_id::int, 'FA', new.formacion_completada_at);

    if anfeca_compat.replicar_public() then
        insert into public.formaciones_academicas_solicitud (id, solicitud_id, nivel_origen, titulo_origen, nivel_ultimo_grado, titulo_ultimo_grado,
            institucion_afiliada_id, titulo_origen_archivo, cedula_origen_archivo, titulo_ultimo_grado_archivo, cedula_ultimo_grado_archivo,
            created_at, updated_at, formacion_completada_at, titulo_origen_otro, titulo_ultimo_grado_otro)
        values (v_id, new.solicitud_id, new.nivel_origen, new.titulo_origen, new.nivel_ultimo_grado, new.titulo_ultimo_grado,
            new.institucion_afiliada_id, new.titulo_origen_archivo, new.cedula_origen_archivo, new.titulo_ultimo_grado_archivo,
            new.cedula_ultimo_grado_archivo, new.created_at, new.updated_at, new.formacion_completada_at, new.titulo_origen_otro,
            new.titulo_ultimo_grado_otro)
        on conflict (id) do update set solicitud_id = excluded.solicitud_id, nivel_origen = excluded.nivel_origen, titulo_origen = excluded.titulo_origen,
            nivel_ultimo_grado = excluded.nivel_ultimo_grado, titulo_ultimo_grado = excluded.titulo_ultimo_grado,
            institucion_afiliada_id = excluded.institucion_afiliada_id, titulo_origen_archivo = excluded.titulo_origen_archivo,
            cedula_origen_archivo = excluded.cedula_origen_archivo, titulo_ultimo_grado_archivo = excluded.titulo_ultimo_grado_archivo,
            cedula_ultimo_grado_archivo = excluded.cedula_ultimo_grado_archivo, created_at = excluded.created_at, updated_at = excluded.updated_at,
            formacion_completada_at = excluded.formacion_completada_at, titulo_origen_otro = excluded.titulo_origen_otro,
            titulo_ultimo_grado_otro = excluded.titulo_ultimo_grado_otro;
        perform anfeca_compat.alinear_secuencia('public.formaciones_academicas_solicitud', 'id', v_id);
    end if;

    new.id := v_id;
    return new;
end $$;

create trigger tg_formaciones_academicas_solicitud instead of insert or update or delete on anfeca_compat.formaciones_academicas_solicitud
    for each row execute function anfeca_compat.tg_formaciones_academicas_solicitud();

-- ---------------------------------------------------------------------
-- documentos_probatorios_solicitud → DOCUMENTOS_SOLICITUD + SOLICITUD_DOCUMENTO (historial) + PAGO
-- ---------------------------------------------------------------------
create function anfeca_compat.tg_documentos_probatorios_solicitud() returns trigger language plpgsql as $$
declare
    v_id bigint;
    v_pago int;
begin
    if tg_op = 'DELETE' then
        perform anfeca_compat.error('pdm', 'No se permite eliminar documentos.');
    end if;
    perform anfeca_compat.solicitud_existente(new.solicitud_id);
    v_id := case tg_op when 'INSERT' then coalesce(new.id, nextval(pg_get_serial_sequence('anfeca_modelo.documentos_solicitud', 'dosc_id_documentos'))) else old.id end;

    insert into anfeca_modelo.documentos_solicitud (dosc_id_documentos, dosc_id_solicitud, dosc_creado_en, dosc_actualizado_en)
    values (v_id, new.solicitud_id, new.created_at, new.updated_at)
    on conflict (dosc_id_documentos) do update set dosc_id_solicitud = excluded.dosc_id_solicitud,
        dosc_creado_en = excluded.dosc_creado_en, dosc_actualizado_en = excluded.dosc_actualizado_en;
    perform anfeca_compat.alinear_secuencia('anfeca_modelo.documentos_solicitud', 'dosc_id_documentos', v_id);

    perform anfeca_compat.documento_set(new.solicitud_id::int, 'constancia_antiguedad_archivo', new.constancia_antiguedad_archivo, new.updated_at);
    perform anfeca_compat.documento_set(new.solicitud_id::int, 'comprobante_pago_archivo', new.comprobante_pago_archivo, new.updated_at);
    perform anfeca_compat.documento_set(new.solicitud_id::int, 'solicitud_articulo_14_archivo', new.solicitud_articulo_14_archivo, new.updated_at);
    perform anfeca_compat.documento_set(new.solicitud_id::int, 'constancia_articulo_14_archivo', new.constancia_articulo_14_archivo, new.updated_at);
    perform anfeca_compat.documento_set(new.solicitud_id::int, 'link', new.link, new.updated_at);

    -- PAGO: una fila vigente por solicitud.
    if anfeca_compat.vacio(new.comprobante_pago_archivo) then
        delete from anfeca_modelo.pago where pago_id_solicitud = new.solicitud_id;
    else
        perform anfeca_compat.largo(new.comprobante_pago_archivo, 255, 'comprobante_pago_archivo');
        select p.pago_id_pago into v_pago from anfeca_modelo.pago p where p.pago_id_solicitud = new.solicitud_id order by p.pago_id_pago limit 1;
        if v_pago is null then
            insert into anfeca_modelo.pago (pago_id_solicitud, pago_url_comprobante) values (new.solicitud_id, new.comprobante_pago_archivo);
        else
            update anfeca_modelo.pago set pago_url_comprobante = new.comprobante_pago_archivo where pago_id_pago = v_pago;
            delete from anfeca_modelo.pago where pago_id_solicitud = new.solicitud_id and pago_id_pago <> v_pago;
        end if;
    end if;

    if anfeca_compat.replicar_public() then
        insert into public.documentos_probatorios_solicitud (id, solicitud_id, constancia_antiguedad_archivo, comprobante_pago_archivo, link,
            created_at, updated_at, solicitud_articulo_14_archivo, constancia_articulo_14_archivo)
        values (v_id, new.solicitud_id, new.constancia_antiguedad_archivo, new.comprobante_pago_archivo, new.link, new.created_at, new.updated_at,
            new.solicitud_articulo_14_archivo, new.constancia_articulo_14_archivo)
        on conflict (id) do update set solicitud_id = excluded.solicitud_id, constancia_antiguedad_archivo = excluded.constancia_antiguedad_archivo,
            comprobante_pago_archivo = excluded.comprobante_pago_archivo, link = excluded.link, created_at = excluded.created_at,
            updated_at = excluded.updated_at, solicitud_articulo_14_archivo = excluded.solicitud_articulo_14_archivo,
            constancia_articulo_14_archivo = excluded.constancia_articulo_14_archivo;
        perform anfeca_compat.alinear_secuencia('public.documentos_probatorios_solicitud', 'id', v_id);
    end if;

    new.id := v_id;
    return new;
end $$;

create trigger tg_documentos_probatorios_solicitud instead of insert or update or delete on anfeca_compat.documentos_probatorios_solicitud
    for each row execute function anfeca_compat.tg_documentos_probatorios_solicitud();

-- ---------------------------------------------------------------------
-- historial_documentos_academicos → la versión anterior queda como «Sustituido»
-- ---------------------------------------------------------------------
create function anfeca_compat.tg_historial_documentos_academicos() returns trigger language plpgsql as $$
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
    where d.socu_id_solicitud = new.solicitud_id and d.docu_id_documento = v_doc and d.socu_url_archivo = new.ruta_archivo
    order by d.socu_id_documentacion desc limit 1;

    if v_id is null then
        insert into anfeca_modelo.solicitud_documento (socu_id_solicitud, docu_id_documento, socu_fecha, socu_hora, socu_url_archivo)
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

create trigger tg_historial_documentos_academicos instead of insert or update or delete on anfeca_compat.historial_documentos_academicos
    for each row execute function anfeca_compat.tg_historial_documentos_academicos();

-- ---------------------------------------------------------------------
-- Vistas de solo lectura (catálogos que ANFECA no captura)
-- ---------------------------------------------------------------------
create function anfeca_compat.tg_solo_lectura() returns trigger language plpgsql as $$
begin
    perform anfeca_compat.error('pdm', 'El catálogo ' || tg_table_name || ' se administra en el modelo institucional; no se modifica desde ANFECA.');
    return null;
end $$;

create trigger tg_solo_lectura instead of insert or update or delete on anfeca_compat.certificados_historicos
    for each row execute function anfeca_compat.tg_solo_lectura();
create trigger tg_solo_lectura instead of insert or update or delete on anfeca_compat.instituciones_afiliadas
    for each row execute function anfeca_compat.tg_solo_lectura();

-- ---------------------------------------------------------------------
-- GUARDIA de public (modos principal y exclusivo)
--   Las tablas de dominio de public solo se escriben desde los disparadores de las vistas
--   (réplica en principal) o desde la exportación de reversión (anfeca.exportando = si).
--   Una escritura directa (consola, proceso viejo sin search_path) se rechaza: así public
--   nunca se desincroniza en silencio ni se usa como fuente en exclusivo.
-- ---------------------------------------------------------------------
create function anfeca_compat.tg_guardia_public() returns trigger language plpgsql as $$
begin
    if anfeca_compat.modo() in ('principal', 'exclusivo')
       and pg_trigger_depth() = 1
       and coalesce(current_setting('anfeca.exportando', true), '') <> 'si' then
        raise exception using errcode = 'P0001', message = 'ANFECA_VALIDACION|pdm|Con el modo PDM '
            || anfeca_compat.modo() || ' la tabla public.' || tg_table_name || ' no se escribe directamente; use la aplicación (vistas anfeca_compat).';
    end if;
    return coalesce(new, old);
end $$;

do $$
declare t text;
begin
    foreach t in array array['datos_solicitantes', 'selecciones_certificacion', 'solicitudes_certificacion', 'datos_refrendo',
        'formaciones_academicas_solicitud', 'documentos_probatorios_solicitud', 'historial_documentos_academicos',
        'certificados_historicos', 'instituciones_afiliadas'] loop
        execute format('create trigger anfeca_compat_guardia before insert or update or delete on public.%I
                        for each row execute function anfeca_compat.tg_guardia_public()', t);
    end loop;
end $$;

-- ---------------------------------------------------------------------
-- public.users sigue siendo la tabla técnica de autenticación (D-1 A).
-- En principal/exclusivo, contraseña, correo y rol se sincronizan con USUARIO.
-- ---------------------------------------------------------------------
create function anfeca_compat.tg_users_usuario() returns trigger language plpgsql as $$
begin
    if anfeca_compat.modo() in ('principal', 'exclusivo')
       and (new.password is distinct from old.password or new.email is distinct from old.email or new.rol is distinct from old.rol) then
        perform anfeca_compat.largo(new.email, 150, 'email');
        update anfeca_modelo.usuario set usua_contrasenia = new.password, usua_nombre = new.email, usua_id_rol = coalesce(case when new.rol is not null then anfeca_compat.rol_id(new.rol) end, usua_id_rol)
        where usua_id_usuario = new.id;
    end if;
    return new;
end $$;

create trigger anfeca_compat_usuario after update on public.users
    for each row execute function anfeca_compat.tg_users_usuario();

-- ---------------------------------------------------------------------
-- Valores por omisión: cada vista hereda los de su tabla de public (salvo el ID, que asigna
-- el disparador), para que un INSERT de Eloquent sin esas columnas se comporte igual.
-- ---------------------------------------------------------------------
do $$
declare c record;
begin
    for c in select v.table_name, v.column_name, t.column_default
             from information_schema.columns v
             join information_schema.columns t on t.table_schema = 'public' and t.table_name = v.table_name and t.column_name = v.column_name
             where v.table_schema = 'anfeca_compat' and t.column_default is not null and t.column_default not like 'nextval(%'
               and v.table_name in (select table_name from information_schema.views where table_schema = 'anfeca_compat') loop
        execute format('alter view anfeca_compat.%I alter column %I set default %s', c.table_name, c.column_name, c.column_default);
    end loop;
end $$;
