-- =====================================================================================================================
-- ANFECA · Fase 10.8 · Diagnóstico de area_certificacion y de los programas ANUIES (estudio_profesional).
-- EXCLUSIVAMENTE DE LECTURA: transacción READ ONLY terminada en ROLLBACK; no crea objetos (ni temporales).
-- Funciona con o sin la columna no autorizada P-03 (estudio_profesional.espr_id_area_certificacion).
-- Uso (VS Code SSH, base de 8088 o su copia 8090):
--   PGOPTIONS='-c default_transaction_read_only=on' psql -X -h 127.0.0.1 -p 5488 -U anfeca_8088 -d certificacion_anfeca \
--        -v ON_ERROR_STOP=1 -f 10_8_diagnostico_anuies_areas.sql 2>&1 | tee ~/diagnostico_anuies_areas_$(date +%Y%m%d_%H%M).txt
-- =====================================================================================================================
\set ON_ERROR_STOP on
\pset footer off
\pset null '(nulo)'
BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY;

\echo '== 1. area_certificacion: existencia, llave primaria y columnas'
SELECT to_regclass('public.area_certificacion') IS NOT NULL AS existe,
       (SELECT conname FROM pg_constraint WHERE conrelid = 'public.area_certificacion'::regclass AND contype = 'p') AS llave_primaria,
       (SELECT string_agg(column_name || ' ' || data_type || coalesce('(' || character_maximum_length || ')', '') || CASE WHEN is_nullable = 'NO' THEN ' not null' ELSE '' END, ', ' ORDER BY ordinal_position)
          FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'area_certificacion') AS columnas,
       (SELECT last_value FROM public.area_certificacion_area_id_area_certificacion_seq) AS secuencia;

\echo '== 2. Registros de area_certificacion (identificadores reales) y correspondencia con las 5 áreas esperadas'
WITH esperadas(orden, descripcion) AS (VALUES (1, 'Contaduría Pública'), (2, 'Administración'), (3, 'Informática Administrativa'),
        (4, 'Negocios Internacionales'), (5, 'Otras Disciplinas Vinculadas al Ámbito Económico Administrativo')),
n AS (SELECT a.*, lower(translate(btrim(a.area_descripcion), 'ÁÉÍÓÚÜÑáéíóúüñ', 'AEIOUUNaeiouun')) AS clave FROM public.area_certificacion a)
SELECT e.orden, e.descripcion AS esperada, n.area_id_area_certificacion AS id_real, n.area_descripcion AS almacenada,
       CASE WHEN n.area_id_area_certificacion IS NULL THEN 'FALTA'
            WHEN n.area_descripcion = e.descripcion THEN 'idéntica'
            ELSE 'equivalente (difiere en acentos, mayúsculas o espacios)' END AS resultado
  FROM esperadas e LEFT JOIN n ON n.clave = lower(translate(e.descripcion, 'ÁÉÍÓÚÜÑáéíóúüñ', 'AEIOUUNaeiouun'))
 ORDER BY e.orden;
SELECT area_id_area_certificacion AS id, area_descripcion, length(area_descripcion) AS longitud FROM public.area_certificacion ORDER BY 1;
SELECT count(*) AS registros,
       count(*) - count(DISTINCT lower(translate(btrim(area_descripcion), 'ÁÉÍÓÚÜÑáéíóúüñ', 'AEIOUUNaeiouun'))) AS duplicados_normalizados
  FROM public.area_certificacion;

\echo '== 3. Relaciones que apuntan a area_certificacion (oficial: solo solicitud.soli_id_area_certificacion)'
SELECT c.conname AS llave, c.conrelid::regclass AS tabla, pg_get_constraintdef(c.oid) AS definicion,
       CASE WHEN c.conname = 'fk_solicitu_pertenece_area_cer' THEN 'oficial' ELSE 'NO OFICIAL' END AS clasificacion
  FROM pg_constraint c WHERE c.contype = 'f' AND c.confrelid = 'public.area_certificacion'::regclass ORDER BY 1;
SELECT a.area_id_area_certificacion AS id, a.area_descripcion, count(s.soli_id_solicitud) AS solicitudes
  FROM public.area_certificacion a LEFT JOIN public.solicitud s ON s.soli_id_area_certificacion = a.area_id_area_certificacion GROUP BY 1, 2 ORDER BY 1;
SELECT hice_area_certificacion AS area_en_historicos, count(*) AS certificados FROM public.historico_certificado GROUP BY 1 ORDER BY 2 DESC LIMIT 15;

\echo '== 4. Programas académicos (estudio_profesional): totales por nivel y estado'
SELECT n.nipr_id_nivel_profesional AS id_nivel, n.nipr_nivel_profesional AS nivel, e.espr_estado AS estado, count(*) AS programas
  FROM public.estudio_profesional e JOIN public.nivel_profesional n ON n.nipr_id_nivel_profesional = e.espr_id_nivel_profesional
 GROUP BY 1, 2, 3 ORDER BY 1, 3;
SELECT count(*) AS total, count(*) FILTER (WHERE espr_estado = 'ACT') AS activos_anuies, count(*) FILTER (WHERE espr_estado <> 'ACT') AS otros_estados,
       count(DISTINCT (lower(btrim(espr_nombre)), espr_id_nivel_profesional)) FILTER (WHERE espr_estado = 'ACT') AS activos_distintos_por_nombre_y_nivel
  FROM public.estudio_profesional;
SELECT count(*) AS activos_repetidos_por_nombre_y_nivel FROM (SELECT 1 FROM public.estudio_profesional WHERE espr_estado = 'ACT'
        GROUP BY lower(btrim(espr_nombre)), espr_id_nivel_profesional HAVING count(*) > 1) x;

SELECT count(*) = 1 AS hay_p03 FROM information_schema.columns
 WHERE table_schema = 'public' AND table_name = 'estudio_profesional' AND column_name = 'espr_id_area_certificacion' \gset
\if :hay_p03
\echo '== 5. P-03 PRESENTE (estudio_profesional.espr_id_area_certificacion, no autorizada): matriz por área y nivel'
WITH p AS (
    SELECT e.espr_id_area_certificacion AS id_area, e.espr_estado,
           CASE WHEN lower(translate(n.nipr_nivel_profesional, 'ÁÉÍÓÚáéíóú', 'AEIOUaeiou')) LIKE 'licenciatura%' THEN 'L'
                WHEN lower(translate(n.nipr_nivel_profesional, 'ÁÉÍÓÚáéíóú', 'AEIOUaeiou')) LIKE 'especialidad%' THEN 'E'
                WHEN lower(translate(n.nipr_nivel_profesional, 'ÁÉÍÓÚáéíóú', 'AEIOUaeiou')) LIKE 'maestria%' THEN 'M'
                WHEN lower(translate(n.nipr_nivel_profesional, 'ÁÉÍÓÚáéíóú', 'AEIOUaeiou')) LIKE 'doctorado%' THEN 'D' ELSE '?' END AS nivel
      FROM public.estudio_profesional e JOIN public.nivel_profesional n ON n.nipr_id_nivel_profesional = e.espr_id_nivel_profesional
     WHERE e.espr_estado = 'ACT'),
filas AS (SELECT a.area_id_area_certificacion AS id, a.area_descripcion AS area FROM public.area_certificacion a
          UNION ALL SELECT NULL, 'Sin clasificación')
SELECT f.area AS "Área de certificación", f.id AS "ID oficial",
       count(p.*) FILTER (WHERE p.nivel = 'L') AS "Licenciaturas", count(p.*) FILTER (WHERE p.nivel = 'E') AS "Especialidades",
       count(p.*) FILTER (WHERE p.nivel = 'M') AS "Maestrías", count(p.*) FILTER (WHERE p.nivel = 'D') AS "Doctorados",
       count(p.*) FILTER (WHERE p.nivel = '?') AS "Otro nivel", count(p.*) AS "Total"
  FROM filas f LEFT JOIN p ON p.id_area IS NOT DISTINCT FROM f.id
 GROUP BY f.area, f.id ORDER BY f.id NULLS LAST;
SELECT count(*) FILTER (WHERE espr_id_area_certificacion IS NOT NULL) AS programas_con_area, count(*) FILTER (WHERE espr_id_area_certificacion IS NULL) AS programas_sin_area,
       count(*) FILTER (WHERE espr_estado <> 'ACT' AND espr_id_area_certificacion IS NOT NULL) AS no_anuies_con_area,
       (SELECT string_agg(DISTINCT espr_id_area_certificacion::text, ', ') FROM public.estudio_profesional) AS identificadores_usados,
       (SELECT count(*) FROM public.estudio_profesional e WHERE e.espr_id_area_certificacion IS NOT NULL
           AND NOT EXISTS (SELECT 1 FROM public.area_certificacion a WHERE a.area_id_area_certificacion = e.espr_id_area_certificacion)) AS identificadores_inconsistentes
  FROM public.estudio_profesional;
\echo '   Muestra por área (5 programas por área, solo nombres de catálogo)'
SELECT * FROM (SELECT a.area_descripcion, n.nipr_nivel_profesional, e.espr_nombre,
       row_number() OVER (PARTITION BY a.area_id_area_certificacion ORDER BY e.espr_id_estudio_profesional) AS k
  FROM public.estudio_profesional e JOIN public.area_certificacion a ON a.area_id_area_certificacion = e.espr_id_area_certificacion
  JOIN public.nivel_profesional n ON n.nipr_id_nivel_profesional = e.espr_id_nivel_profesional) x WHERE k <= 5 ORDER BY 1, 4;
\else
\echo '== 5. P-03 AUSENTE: la clasificación por área de los programas NO puede calcularse con el modelo oficial'
SELECT n.nipr_nivel_profesional AS nivel, count(*) AS "Sin clasificación (activos ANUIES)"
  FROM public.estudio_profesional e JOIN public.nivel_profesional n ON n.nipr_id_nivel_profesional = e.espr_id_nivel_profesional
 WHERE e.espr_estado = 'ACT' GROUP BY 1 ORDER BY 1;
\endif

\echo '== 6. Única relación OFICIAL indirecta: estudio ← persona_estudio_profesional → persona ← solicitud → área'
\echo '   (asocia el estudio de una persona con el área en que ELLA solicitó certificarse; no clasifica el programa)'
SELECT a.area_descripcion AS area_de_la_solicitud, count(DISTINCT pe.pees_id_estuido_profesional) AS programas_distintos, count(DISTINCT s.soli_id_persona) AS personas
  FROM public.solicitud s JOIN public.area_certificacion a ON a.area_id_area_certificacion = s.soli_id_area_certificacion
  JOIN public.persona_estudio_profesional pe ON pe.pees_id_persona = s.soli_id_persona GROUP BY 1 ORDER BY 1;
SELECT count(*) AS programas_vinculados_a_mas_de_un_area FROM (
  SELECT pe.pees_id_estuido_profesional FROM public.solicitud s JOIN public.persona_estudio_profesional pe ON pe.pees_id_persona = s.soli_id_persona
   GROUP BY 1 HAVING count(DISTINCT s.soli_id_area_certificacion) > 1) x;

ROLLBACK;
\echo 'Diagnóstico terminado: transacción de solo lectura revertida; no se modificó nada.'
