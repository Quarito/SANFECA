-- =====================================================================
-- ANFECA — Extensiones ADITIVAS F4 al modelo PowerDesigner (anfeca_modelo)
--
-- Para que ANFECA lea y escriba en el PDM sin perder información (F4/F5), el PDM
-- necesita conservar datos por TRÁMITE que hoy solo existen en public:
--   * la selección de certificación del usuario (selecciones_certificacion),
--   * los datos propios de la solicitud de refrendo (refrendo actual, número asignado),
--   * el registro de formación por solicitud (institución elegida en ese trámite),
--   * el registro de documentos probatorios por solicitud,
--   * fechas de creación y actualización (auditoría) de persona y solicitud.
--
-- No modifica columnas ni restricciones originales. Todo calificado con anfeca_modelo.
-- Reflejar en PowerDesigner junto con anfeca_modelo_extensiones.sql (F1).
-- =====================================================================

alter table anfeca_modelo.PERSONA
   add column PERS_CREADO_EN       TIMESTAMP(0) null,
   add column PERS_ACTUALIZADO_EN  TIMESTAMP(0) null;

alter table anfeca_modelo.SOLICITUD
   add column SOLI_CREADO_EN       TIMESTAMP(0) null,
   add column SOLI_ACTUALIZADO_EN  TIMESTAMP(0) null;

-- Selección de certificación vigente del usuario (una por usuario).
create table anfeca_modelo.SELECCION_CERTIFICACION (
   SECC_ID_SELECCION      BIGSERIAL     not null,
   SECC_ID_USUARIO        INT4          not null,
   SECC_CERTIFICACION     VARCHAR(20)   not null,
   SECC_TIPO_PERSONAL     VARCHAR(100)  not null,
   SECC_AREA_CERTIFICACION VARCHAR(150) not null,
   SECC_CREADO_EN         TIMESTAMP(0)  null,
   SECC_ACTUALIZADO_EN    TIMESTAMP(0)  null,
   constraint PK_SELECCION_CERTIFICACION primary key (SECC_ID_SELECCION),
   constraint FK_SECC_USUARIO foreign key (SECC_ID_USUARIO)
      references anfeca_modelo.USUARIO (USUA_ID_USUARIO) on delete restrict on update restrict
);
create unique index EXT_SECC_USUARIO_UK on anfeca_modelo.SELECCION_CERTIFICACION (SECC_ID_USUARIO);
comment on table anfeca_modelo.SELECCION_CERTIFICACION is 'EXT ANFECA F4: public.selecciones_certificacion';

-- Datos de la solicitud de refrendo que no están en SOLICITUD (art. 13/14 sí están en SOLICITUD).
create table anfeca_modelo.SOLICITUD_REFRENDO (
   SORE_ID_SOLICITUD_REFRENDO   BIGSERIAL   not null,
   SORE_ID_SOLICITUD            INT4        not null,
   SORE_NUMERO_REFRENDO_ASIGNADO VARCHAR(50) null,
   SORE_REFRENDO_ACTUAL         INT4        null,
   SORE_CREADO_EN               TIMESTAMP(0) null,
   SORE_ACTUALIZADO_EN          TIMESTAMP(0) null,
   constraint PK_SOLICITUD_REFRENDO primary key (SORE_ID_SOLICITUD_REFRENDO),
   constraint FK_SORE_SOLICITUD foreign key (SORE_ID_SOLICITUD)
      references anfeca_modelo.SOLICITUD (SOLI_ID_SOLICITUD) on delete restrict on update restrict
);
create unique index EXT_SORE_SOLICITUD_UK on anfeca_modelo.SOLICITUD_REFRENDO (SORE_ID_SOLICITUD);
comment on table anfeca_modelo.SOLICITUD_REFRENDO is 'EXT ANFECA F4: public.datos_refrendo (lo no cubierto por SOLICITUD)';

-- Formación académica registrada en un trámite: institución elegida en ESE trámite.
-- Los estudios y archivos están en PERSONA_ESTUDIO_PROFESIONAL; la autorización es el evento FA.
create table anfeca_modelo.FORMACION_SOLICITUD (
   FOSC_ID_FORMACION      BIGSERIAL    not null,
   FOSC_ID_SOLICITUD      INT4         not null,
   FOSC_ID_INSTITUCION    INT4         null,
   FOSC_CREADO_EN         TIMESTAMP(0) null,
   FOSC_ACTUALIZADO_EN    TIMESTAMP(0) null,
   constraint PK_FORMACION_SOLICITUD primary key (FOSC_ID_FORMACION),
   constraint FK_FOSC_SOLICITUD foreign key (FOSC_ID_SOLICITUD)
      references anfeca_modelo.SOLICITUD (SOLI_ID_SOLICITUD) on delete restrict on update restrict,
   constraint FK_FOSC_INSTITUCION foreign key (FOSC_ID_INSTITUCION)
      references anfeca_modelo.INSTITUCION (INST_ID_INSTITUCION) on delete restrict on update restrict
);
create unique index EXT_FOSC_SOLICITUD_UK on anfeca_modelo.FORMACION_SOLICITUD (FOSC_ID_SOLICITUD);
comment on table anfeca_modelo.FORMACION_SOLICITUD is 'EXT ANFECA F4: registro de formaciones_academicas_solicitud';

-- Registro de documentos probatorios de un trámite (los archivos están en SOLICITUD_DOCUMENTO).
create table anfeca_modelo.DOCUMENTOS_SOLICITUD (
   DOSC_ID_DOCUMENTOS     BIGSERIAL    not null,
   DOSC_ID_SOLICITUD      INT4         not null,
   DOSC_CREADO_EN         TIMESTAMP(0) null,
   DOSC_ACTUALIZADO_EN    TIMESTAMP(0) null,
   constraint PK_DOCUMENTOS_SOLICITUD primary key (DOSC_ID_DOCUMENTOS),
   constraint FK_DOSC_SOLICITUD foreign key (DOSC_ID_SOLICITUD)
      references anfeca_modelo.SOLICITUD (SOLI_ID_SOLICITUD) on delete restrict on update restrict
);
create unique index EXT_DOSC_SOLICITUD_UK on anfeca_modelo.DOCUMENTOS_SOLICITUD (DOSC_ID_SOLICITUD);
comment on table anfeca_modelo.DOCUMENTOS_SOLICITUD is 'EXT ANFECA F4: registro de documentos_probatorios_solicitud';
