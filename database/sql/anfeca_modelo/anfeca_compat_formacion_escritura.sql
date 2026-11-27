create or replace function anfeca_compat.tg_formaciones_academicas_solicitud() returns trigger language plpgsql as $$
declare
    v_id bigint;
    v_persona int;
    v_entrada jsonb;
    v_catalogo jsonb;
    v_columna text;
    v_asignaciones text;
    v_columnas_public text[];
begin
    if tg_op = 'DELETE' then
        perform anfeca_compat.error('pdm', 'No se permite eliminar la formación académica.');
    end if;
    v_entrada := to_jsonb(new);
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

    perform anfeca_compat.estudio_set(new.solicitud_id::int, v_persona, 'ORIGEN', new.nivel_origen, new.titulo_origen,
        case when char_length(new.titulo_origen_otro) > 150
                  and new.titulo_origen_otro = left(v_entrada->>'titulo_origen_especifico', 200)
             then null else new.titulo_origen_otro end, new.titulo_origen_archivo, new.cedula_origen_archivo);
    perform anfeca_compat.estudio_set(new.solicitud_id::int, v_persona, 'ULTIMO', new.nivel_ultimo_grado, new.titulo_ultimo_grado,
        case when char_length(new.titulo_ultimo_grado_otro) > 150
                  and new.titulo_ultimo_grado_otro = left(v_entrada->>'titulo_ultimo_grado_especifico', 200)
             then null else new.titulo_ultimo_grado_otro end, new.titulo_ultimo_grado_archivo, new.cedula_ultimo_grado_archivo);

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

    -- Las columnas de catálogo son las propiedades de la vista que también están en la
    -- extensión técnica. Las columnas propias de la extensión usan el prefijo fosc_.
    select coalesce(jsonb_object_agg(a.attname, v_entrada -> a.attname), '{}'::jsonb)
      into v_catalogo
      from pg_attribute a
      where a.attrelid = 'anfeca_modelo.formacion_solicitud'::regclass and a.attnum > 0
        and not a.attisdropped and left(a.attname, 5) <> 'fosc_' and v_entrada ? a.attname;

    if v_catalogo <> '{}'::jsonb then
        select array_agg(a.attname::text) into v_columnas_public from pg_attribute a
         where a.attrelid = 'public.formaciones_academicas_solicitud'::regclass
           and a.attnum > 0 and not a.attisdropped;

        select k into v_columna from jsonb_object_keys(jsonb_strip_nulls(v_catalogo)) as k
         where not (k = any(v_columnas_public)) order by k limit 1;
        if v_columna is not null then
            perform anfeca_compat.error(v_columna, 'Falta la columna correspondiente en Laravel para replicar y revertir la formación.');
        end if;

        select string_agg(format('%1$I = c.%1$I', k), ', ') into v_asignaciones
          from jsonb_object_keys(v_catalogo) as k;
        execute format('update anfeca_modelo.formacion_solicitud f set %s
                        from jsonb_populate_record(null::anfeca_modelo.formacion_solicitud, $1) c
                        where f.fosc_id_formacion = $2', v_asignaciones) using v_catalogo, v_id;

        if anfeca_compat.replicar_public() then
            select string_agg(format('%1$I = c.%1$I', k), ', ') into v_asignaciones
              from jsonb_object_keys(v_catalogo) as k where k = any(v_columnas_public);
            if v_asignaciones is not null then
                execute format('update public.formaciones_academicas_solicitud p set %s
                                from jsonb_populate_record(null::public.formaciones_academicas_solicitud, $1) c
                                where p.id = $2', v_asignaciones) using v_catalogo, v_id;
            end if;
        end if;
    end if;

    new.id := v_id;
    return new;
end $$;
