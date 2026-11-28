-- =====================================================================================================================
-- SOLO LABORATORIO (base NUEVA y desechable): reproduce las 15 diferencias estructurales reportadas en 8088 para ensayar
-- anfeca:verificar-esquema y diagnostico_diferencias_8088.sql. NUNCA se ejecuta en 8088, 8086, 8008 ni producción.
-- Los tipos de las columnas adicionales y el destino de fk_convocatoria_certificacion son SUPUESTOS del laboratorio:
-- los reales los informa el diagnóstico en 8088.
-- =====================================================================================================================
\set ON_ERROR_STOP on
SELECT current_database() !~ '^(certificacion_anfeca|bd_)' AS es_prueba \gset
\if :es_prueba
\else
\echo 'DETENIDO: solo en bases de prueba.'
\quit
\endif
BEGIN;
ALTER TABLE convocatoria ADD COLUMN conv_anio INT4 NULL, ADD COLUMN conv_id_certificacion INT4 NULL;
ALTER TABLE estudio_profesional ADD COLUMN espr_id_area_certificacion INT4 NULL;
ALTER TABLE evaluacion ADD COLUMN eval_folio_evaluacion VARCHAR(30) NULL;
ALTER TABLE institucion RENAME COLUMN ins_coord_regional_cert_academi TO inst_coord_regional_cert_academica;
ALTER TABLE institucion ADD COLUMN inst_campus VARCHAR(200) NULL, ADD COLUMN inst_sede VARCHAR(200) NULL,
    ALTER COLUMN inst_numero_afiliacion DROP NOT NULL, ALTER COLUMN inst_facultad DROP NOT NULL, ALTER COLUMN inst_zona DROP NOT NULL;
ALTER TABLE solicitud_documento ADD COLUMN sodo_url VARCHAR(500) NULL;
ALTER TABLE tipo_convocatoria ALTER COLUMN tico_tipo_convocatoria TYPE VARCHAR(150);
ALTER TABLE convocatoria ADD CONSTRAINT fk_convocatoria_certificacion FOREIGN KEY (conv_id_certificacion)
    REFERENCES area_certificacion (area_id_area_certificacion) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE estudio_profesional ADD CONSTRAINT fk_estudio_area_certificacion FOREIGN KEY (espr_id_area_certificacion)
    REFERENCES area_certificacion (area_id_area_certificacion) ON DELETE RESTRICT ON UPDATE RESTRICT;
COMMIT;
