create or replace view anfeca_compat.formaciones_academicas_solicitud as
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
    (/* titulo_otro_origen */)::varchar(200) as titulo_origen_otro,
    (/* titulo_otro_ultimo */)::varchar(200) as titulo_ultimo_grado_otro/* columnas_catalogo */
from anfeca_modelo.formacion_solicitud f;
