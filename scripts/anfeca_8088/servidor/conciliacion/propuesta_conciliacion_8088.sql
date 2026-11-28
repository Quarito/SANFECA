-- =====================================================================================================================
-- ANFECA · PROPUESTA de conciliación del modelo físico con la referencia vigente (36 tablas, 173 atributos, 35 FK):
-- el SQL oficial de PowerDesigner con la corrección autorizada C-01 (institucion.inst_coord_regional_cert_academica).
-- SOLO SOBRE LA COPIA INDEPENDIENTE DE 8090 (certificacion_anfeca_8090) o bases de laboratorio: nunca sobre 8088.
-- NO SE EJECUTA SIN AUTORIZACIÓN ESCRITA. Por omisión SIMULA: aplica todo en una transacción y la REVIERTE.
-- Requisitos previos: respaldo verificado (bloque 2) y diagnostico_diferencias_8088.sql revisado.
--
-- Uso (VS Code SSH), desde un directorio privado fuera de Git donde quedarán las exportaciones:
--   psql -X -h 127.0.0.1 -p 5488 -U anfeca_8088 -d certificacion_anfeca -v ON_ERROR_STOP=1 \
--        -v exportar=<directorio privado existente> [-v retirar_con_datos=si] [-v confirmar=si] \
--        -f propuesta_conciliacion_8088.sql 2>&1 | tee conciliacion_$(date +%Y%m%d_%H%M).log
--
-- Qué hace (en este orden, en UNA transacción con bloqueo de las tablas afectadas):
--   0. Exporta a CSV (en el equipo que ejecuta psql) el contenido de todos los atributos adicionales con la llave de
--      su fila, y su huella SHA-256 queda en el registro: la información no se pierde aunque se retire la columna.
--   1. institucion: C-01 ya es el nombre vigente (inst_coord_regional_cert_academica). Solo si una base conserva el
--      nombre ANTERIOR ins_coord_regional_cert_academi (laboratorio), se renombra al vigente conservando los datos.
--   2. institucion: restituye NOT NULL en inst_numero_afiliacion, inst_facultad e inst_zona (se detiene si hay nulos).
--   3. tipo_convocatoria: restituye VARCHAR(50) (se detiene si alguna descripción excede 50 caracteres).
--   4. Retira las FK fk_convocatoria_certificacion y fk_estudio_area_certificacion.
--   5. Retira conv_anio, conv_id_certificacion, espr_id_area_certificacion, eval_folio_evaluacion, inst_campus,
--      inst_sede y sodo_url: solo si están vacías, o con -v retirar_con_datos=si cuando la autorización lo indique
--      (sus datos ya están exportados en el paso 0).
--   6. Comprueba 36 tablas, 173 atributos y 35 FK, y que no quedaron diferencias en estas tablas.
-- Cada paso solo actúa si la diferencia existe: es seguro repetirlo. No toca DOMICILIO, COMUNICACION ni el catálogo.
-- =====================================================================================================================
\set ON_ERROR_STOP on
\pset footer off
SELECT current_database() AS base \gset
\if :{?exportar}
\else
\echo 'DETENIDO: indique -v exportar=<directorio privado> para conservar los datos de los atributos adicionales.'
\quit
\endif
\if :{?confirmar}
\else
\set confirmar no
\endif
\if :{?retirar_con_datos}
\else
\set retirar_con_datos no
\endif
SELECT (:'confirmar' = 'si') AS aplicar, (:'retirar_con_datos' = 'si') AS con_datos \gset
\setenv PSQL_EXPORTAR :exportar
\! test -d "$PSQL_EXPORTAR" -a -w "$PSQL_EXPORTAR" || echo "AVISO: el directorio de exportación no existe o no es escribible: la exportación fallará y se detendrá."

BEGIN;
SET LOCAL lock_timeout = '10s';
LOCK TABLE public.institucion, public.convocatoria, public.estudio_profesional, public.evaluacion, public.solicitud_documento,
           public.tipo_convocatoria IN ACCESS EXCLUSIVE MODE;

CREATE FUNCTION pg_temp.existe(t text, c text) RETURNS boolean LANGUAGE sql AS
$$ SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = t AND column_name = c) $$;

\echo '== 0. Exportación de los atributos adicionales (CSV con la llave de cada fila)'
SELECT pg_temp.existe('convocatoria', 'conv_anio') AS e1, pg_temp.existe('convocatoria', 'conv_id_certificacion') AS e2,
       pg_temp.existe('estudio_profesional', 'espr_id_area_certificacion') AS e3, pg_temp.existe('evaluacion', 'eval_folio_evaluacion') AS e4,
       pg_temp.existe('institucion', 'inst_campus') AS e5, pg_temp.existe('institucion', 'inst_sede') AS e6,
       pg_temp.existe('solicitud_documento', 'sodo_url') AS e7, pg_temp.existe('institucion', 'inst_coord_regional_cert_academica') AS e8 \gset
\if :e1
\copy (SELECT conv_id_convocatoria, conv_nombre, conv_anio, conv_id_certificacion FROM public.convocatoria ORDER BY 1) TO PROGRAM 'cat > "$PSQL_EXPORTAR/convocatoria_adicionales.csv"' WITH (FORMAT csv, HEADER)
\endif
\if :e3
\copy (SELECT espr_id_estudio_profesional, espr_id_area_certificacion FROM public.estudio_profesional WHERE espr_id_area_certificacion IS NOT NULL ORDER BY 1) TO PROGRAM 'cat > "$PSQL_EXPORTAR/estudio_profesional_adicionales.csv"' WITH (FORMAT csv, HEADER)
\endif
\if :e4
\copy (SELECT eval_id_evaluacion, eval_folio_evaluacion FROM public.evaluacion WHERE eval_folio_evaluacion IS NOT NULL ORDER BY 1) TO PROGRAM 'cat > "$PSQL_EXPORTAR/evaluacion_adicionales.csv"' WITH (FORMAT csv, HEADER)
\endif
\if :e5
\copy (SELECT inst_id_institucion, inst_numero_afiliacion, inst_campus, inst_sede FROM public.institucion ORDER BY 1) TO PROGRAM 'cat > "$PSQL_EXPORTAR/institucion_adicionales.csv"' WITH (FORMAT csv, HEADER)
\endif
\if :e7
\copy (SELECT socu_id_documentacion, socu_id_solicitud, sodo_url FROM public.solicitud_documento WHERE sodo_url IS NOT NULL ORDER BY 1) TO PROGRAM 'cat > "$PSQL_EXPORTAR/solicitud_documento_adicionales.csv"' WITH (FORMAT csv, HEADER)
\endif
\if :e8
\copy (SELECT inst_id_institucion, inst_coord_regional_cert_academica FROM public.institucion ORDER BY 1) TO PROGRAM 'cat > "$PSQL_EXPORTAR/institucion_coord_regional.csv"' WITH (FORMAT csv, HEADER)
\endif
\! cd "$PSQL_EXPORTAR" && ls -l *.csv 2>/dev/null && sha256sum *.csv 2>/dev/null | tee SHA256SUMS_conciliacion

SELECT set_config('anfeca.retirar_con_datos', :'retirar_con_datos', true) AS retirar_con_datos \gset
\echo '== Comprobaciones que impiden perder información (todas deben ser 0, salvo autorización expresa)'
DO $c$
DECLARE n bigint; datos bigint := 0; r text;
BEGIN
    IF pg_temp.existe('institucion', 'inst_numero_afiliacion') THEN
        SELECT count(*) INTO n FROM public.institucion WHERE inst_numero_afiliacion IS NULL OR inst_facultad IS NULL OR inst_zona IS NULL;
        IF n > 0 THEN RAISE EXCEPTION 'DETENIDO: % instituciones con afiliación, facultad o zona nulas; complételas antes de restituir NOT NULL.', n; END IF;
    END IF;
    SELECT count(*) INTO n FROM public.tipo_convocatoria WHERE length(tico_tipo_convocatoria) > 50;
    IF n > 0 THEN RAISE EXCEPTION 'DETENIDO: % tipos de convocatoria exceden 50 caracteres; se requiere decidir su descripción.', n; END IF;
    IF current_database() = 'certificacion_anfeca' THEN
        RAISE EXCEPTION 'DETENIDO: la conciliación no se ejecuta sobre la base de 8088; use la copia independiente de 8090.';
    END IF;
    IF pg_temp.existe('institucion', 'inst_coord_regional_cert_academica') AND pg_temp.existe('institucion', 'ins_coord_regional_cert_academi') THEN
        RAISE EXCEPTION 'DETENIDO: existen ambas columnas de coordinación regional; se requiere conciliarlas manualmente.';
    END IF;
    FOR r IN SELECT unnest(ARRAY['convocatoria.conv_anio', 'convocatoria.conv_id_certificacion', 'estudio_profesional.espr_id_area_certificacion',
                                 'evaluacion.eval_folio_evaluacion', 'institucion.inst_campus', 'institucion.inst_sede', 'solicitud_documento.sodo_url']) LOOP
        IF pg_temp.existe(split_part(r, '.', 1), split_part(r, '.', 2)) THEN
            EXECUTE format('SELECT count(%I) FROM public.%I', split_part(r, '.', 2), split_part(r, '.', 1)) INTO n;
            RAISE NOTICE '% contiene % valores', r, n;
            datos := datos + n;
        END IF;
    END LOOP;
    IF datos > 0 AND current_setting('anfeca.retirar_con_datos', true) IS DISTINCT FROM 'si' THEN
        RAISE EXCEPTION 'DETENIDO: los atributos adicionales contienen % valores (exportados en el paso 0). Retirarlos requiere -v retirar_con_datos=si con autorización.', datos;
    END IF;
END $c$;

\echo '== 1-5. Conciliación'
DO $a$
BEGIN
    IF pg_temp.existe('institucion', 'ins_coord_regional_cert_academi') THEN
        ALTER TABLE public.institucion RENAME COLUMN ins_coord_regional_cert_academi TO inst_coord_regional_cert_academica;
        RAISE NOTICE 'institucion: C-01 aplicada (ins_coord_regional_cert_academi → inst_coord_regional_cert_academica, datos conservados)';
    END IF;
    ALTER TABLE public.institucion ALTER COLUMN inst_numero_afiliacion SET NOT NULL, ALTER COLUMN inst_facultad SET NOT NULL,
        ALTER COLUMN inst_zona SET NOT NULL;
    IF (SELECT character_maximum_length FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'tipo_convocatoria'
          AND column_name = 'tico_tipo_convocatoria') <> 50 THEN
        ALTER TABLE public.tipo_convocatoria ALTER COLUMN tico_tipo_convocatoria TYPE VARCHAR(50);
        RAISE NOTICE 'tipo_convocatoria.tico_tipo_convocatoria restituida a VARCHAR(50)';
    END IF;
    ALTER TABLE public.convocatoria DROP CONSTRAINT IF EXISTS fk_convocatoria_certificacion;
    ALTER TABLE public.estudio_profesional DROP CONSTRAINT IF EXISTS fk_estudio_area_certificacion;
    ALTER TABLE public.convocatoria DROP COLUMN IF EXISTS conv_anio, DROP COLUMN IF EXISTS conv_id_certificacion;
    ALTER TABLE public.estudio_profesional DROP COLUMN IF EXISTS espr_id_area_certificacion;
    ALTER TABLE public.evaluacion DROP COLUMN IF EXISTS eval_folio_evaluacion;
    ALTER TABLE public.institucion DROP COLUMN IF EXISTS inst_campus, DROP COLUMN IF EXISTS inst_sede;
    ALTER TABLE public.solicitud_documento DROP COLUMN IF EXISTS sodo_url;
END $a$;

\echo '== 6. Resultado dentro de la transacción (esperado: 36 · 173 · 35)'
SELECT (SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE') AS tablas,
       (SELECT count(*) FROM information_schema.columns c JOIN information_schema.tables t USING (table_schema, table_name)
         WHERE c.table_schema = 'public' AND t.table_type = 'BASE TABLE') AS atributos,
       (SELECT count(*) FROM pg_constraint WHERE contype = 'f' AND connamespace = 'public'::regnamespace) AS llaves_foraneas \gset
\echo 'Tablas' :tablas '· atributos' :atributos '· llaves foráneas' :llaves_foraneas
SELECT (:tablas = 36 AND :atributos = 173 AND :llaves_foraneas = 35) AS coincide \gset
\if :coincide
\else
\echo 'DETENIDO: el resultado no es 36/173/35; se revierte todo.'
ROLLBACK;
\quit
\endif
\if :aplicar
COMMIT;
\echo 'CONCILIACIÓN APLICADA. Verifique con: php artisan anfeca:verificar-esquema (debe terminar con código 0).'
\else
ROLLBACK;
\echo 'SIMULACIÓN: todo se revirtió. Para aplicar (solo con autorización): -v confirmar=si'
\endif
