-- =====================================================================
-- ANFECA — Extensiones ADITIVAS al modelo PowerDesigner (esquema anfeca_modelo)
-- Autorizadas el 20/11/2026 (decisión D-2 de PLAN_INTEGRACION_FUNCIONAL_PDM.md).
--
-- * No modifica ni elimina columnas, tablas ni restricciones originales del PDM.
-- * Agrega solo lo que el PDM no tiene y que usan funcionalidades ya validadas.
-- * Fuente única: la migración database/migrations_pdm/*_extender_anfeca_modelo.php
--   ejecuta este archivo; usa el mismo texto para actualizar el modelo en PowerDesigner.
-- * Todos los objetos calificados con anfeca_modelo.; no toca public.
-- * Prefijo de comentario "EXT ANFECA" en cada objeto para identificarlo.
-- =====================================================================

-- ---------------------------------------------------------------------
-- PERSONA: autorización de datos y cédula profesional (datos_solicitantes)
-- ---------------------------------------------------------------------
alter table anfeca_modelo.PERSONA
   add column PERS_CEDULA_PROFESIONAL   VARCHAR(30)  null,
   add column PERS_DATOS_AUTORIZADOS    BOOL         not null default false,
   add column PERS_FECHA_AUTORIZACION   TIMESTAMP(0) null;
comment on column anfeca_modelo.PERSONA.PERS_DATOS_AUTORIZADOS is 'EXT ANFECA: datos_solicitantes.datos_autorizados';

-- Una persona por CURP (el registro ya lo exige en Laravel).
create unique index EXT_PERSONA_CURP_UK on anfeca_modelo.PERSONA (PERS_CURP);

-- ---------------------------------------------------------------------
-- USUARIO: un usuario técnico de Laravel (public.users) por USUARIO (D-1 A)
-- ---------------------------------------------------------------------
create unique index EXT_USUARIO_PERSONA_UK on anfeca_modelo.USUARIO (USUA_ID_PERSONA);
create unique index EXT_USUARIO_NOMBRE_UK on anfeca_modelo.USUARIO (USUA_NOMBRE);

-- ---------------------------------------------------------------------
-- DOMICILIO: colonia, municipio y código postal en texto (D-6) y uno por persona
-- ---------------------------------------------------------------------
alter table anfeca_modelo.DOMICILIO
   add column DOMI_COLONIA        VARCHAR(150) null,
   add column DOMI_MUNICIPIO      VARCHAR(150) null,
   add column DOMI_CODIGO_POSTAL  VARCHAR(5)   null;
comment on column anfeca_modelo.DOMICILIO.DOMI_COLONIA is 'EXT ANFECA: texto capturado; catálogo COLONIA pendiente (SEPOMEX)';
create unique index EXT_DOMICILIO_PERSONA_UK on anfeca_modelo.DOMICILIO (DOMI_ID_PERSONA);

-- ---------------------------------------------------------------------
-- COMUNICACION: un valor por persona y tipo (sin duplicados)
-- ---------------------------------------------------------------------
create unique index EXT_COMUNICACION_PERSONA_TIPO_UK on anfeca_modelo.COMUNICACION (COMU_ID_PERSONA, COMU_ID_TIPO_COMUNICACION);

-- ---------------------------------------------------------------------
-- ZONA y COORDINADOR_ZONA (public.zonas, public.coordinador_zona) — D-11
-- ---------------------------------------------------------------------
create table anfeca_modelo.ZONA (
   ZONA_ID_ZONA         SERIAL               not null,
   ZONA_SIGLAS          VARCHAR(30)          not null,
   ZONA_DESCRIPCION     VARCHAR(255)         not null,
   constraint PK_ZONA primary key (ZONA_ID_ZONA)
);
comment on table anfeca_modelo.ZONA is 'EXT ANFECA: zonas ANFECA (public.zonas)';
create unique index EXT_ZONA_SIGLAS_UK on anfeca_modelo.ZONA (ZONA_SIGLAS);

create table anfeca_modelo.COORDINADOR_ZONA (
   COZO_ID_COORDINADOR_ZONA SERIAL           not null,
   COZO_ID_ZONA         INT4                 not null,
   COZO_ID_USUARIO      INT4                 not null,
   constraint PK_COORDINADOR_ZONA primary key (COZO_ID_COORDINADOR_ZONA),
   constraint FK_COORDZON_ZONA foreign key (COZO_ID_ZONA)
      references anfeca_modelo.ZONA (ZONA_ID_ZONA) on delete restrict on update restrict,
   constraint FK_COORDZON_USUARIO foreign key (COZO_ID_USUARIO)
      references anfeca_modelo.USUARIO (USUA_ID_USUARIO) on delete restrict on update restrict
);
comment on table anfeca_modelo.COORDINADOR_ZONA is 'EXT ANFECA: public.coordinador_zona';
create unique index EXT_COORDZON_UK on anfeca_modelo.COORDINADOR_ZONA (COZO_ID_ZONA, COZO_ID_USUARIO);

alter table anfeca_modelo.INSTITUCION
   add column INST_ID_ZONA        INT4         null,
   add column INST_CAMPUS         VARCHAR(200) null,
   add constraint FK_INSTITUC_EXT_ZONA foreign key (INST_ID_ZONA)
      references anfeca_modelo.ZONA (ZONA_ID_ZONA) on delete restrict on update restrict;
create unique index EXT_INSTITUCION_AFILIACION_UK on anfeca_modelo.INSTITUCION (INST_NUMERO_AFILIACION);

-- ---------------------------------------------------------------------
-- HISTORICO_CERTIFICADO_IMPORTACION: copia FIEL del Excel histórico
-- (public.certificados_historicos), sin normalizar ni descartar filas — D-9
-- ---------------------------------------------------------------------
create table anfeca_modelo.HISTORICO_CERTIFICADO_IMPORTACION (
   HCIM_ID_IMPORTACION      INT8          not null,
   HCIM_ID_USUARIO          INT4          null,
   HCIM_ZONA                VARCHAR(255)  null,
   HCIM_NOMBRE_HISTORICO    VARCHAR(255)  null,
   HCIM_CORREO_HISTORICO    VARCHAR(255)  null,
   HCIM_APELLIDO_PATERNO    VARCHAR(255)  null,
   HCIM_APELLIDO_MATERNO    VARCHAR(255)  null,
   HCIM_NOMBRES             VARCHAR(255)  null,
   HCIM_CURP_HISTORICA      VARCHAR(18)   null,
   HCIM_INSTITUCION_HISTORICA VARCHAR(255) null,
   HCIM_CERTIFICACION       VARCHAR(255)  null,
   HCIM_PRIMERA_VEZ         VARCHAR(255)  null,
   HCIM_REFRENDO_1          VARCHAR(255)  null,
   HCIM_REFRENDO_2          VARCHAR(255)  null,
   HCIM_REFRENDO_3          VARCHAR(255)  null,
   HCIM_REFRENDO_4          VARCHAR(255)  null,
   HCIM_REFRENDO_5          VARCHAR(255)  null,
   HCIM_REFRENDO_6          VARCHAR(255)  null,
   HCIM_REFRENDO_7          VARCHAR(255)  null,
   HCIM_COLUMNA_SIN_ENCABEZADO TEXT       null,
   HCIM_REFRENDO_CORRESPONDIENTE VARCHAR(255) null,
   HCIM_NUMERO_CERTIFICADO  VARCHAR(255)  null,
   HCIM_LOTE_IMPORTACION    VARCHAR(255)  null,
   HCIM_FILA_ORIGEN         INT4          null,
   HCIM_ESTADO_VINCULACION  VARCHAR(255)  not null default 'pendiente',
   HCIM_ESTADO_NORMALIZACION VARCHAR(12)  not null default 'PENDIENTE',
   HCIM_MOTIVO_NORMALIZACION VARCHAR(300) null,
   HCIM_CREADO_EN           TIMESTAMP(0)  null,
   HCIM_ACTUALIZADO_EN      TIMESTAMP(0)  null,
   constraint PK_HISTORICO_CERT_IMPORTACION primary key (HCIM_ID_IMPORTACION),
   constraint FK_HCIM_USUARIO foreign key (HCIM_ID_USUARIO)
      references anfeca_modelo.USUARIO (USUA_ID_USUARIO) on delete restrict on update restrict,
   constraint CK_HCIM_ESTADO_NORMALIZACION check (HCIM_ESTADO_NORMALIZACION in ('PENDIENTE','NORMALIZADO','REVISION'))
);
comment on table anfeca_modelo.HISTORICO_CERTIFICADO_IMPORTACION is
   'EXT ANFECA: copia fiel de public.certificados_historicos (mismo id). HISTORICO_CERTIFICADO guarda los eventos normalizados.';
create unique index EXT_HCIM_LOTE_FILA_UK on anfeca_modelo.HISTORICO_CERTIFICADO_IMPORTACION (HCIM_LOTE_IMPORTACION, HCIM_FILA_ORIGEN);
create index EXT_HCIM_NUMERO_IDX on anfeca_modelo.HISTORICO_CERTIFICADO_IMPORTACION (HCIM_NUMERO_CERTIFICADO);
create index EXT_HCIM_CURP_IDX on anfeca_modelo.HISTORICO_CERTIFICADO_IMPORTACION (HCIM_CURP_HISTORICA);

-- Cada evento normalizado apunta a su fila de origen.
alter table anfeca_modelo.HISTORICO_CERTIFICADO
   add column HICE_ID_IMPORTACION INT8 null,
   add column HICE_CERTIFICACION  VARCHAR(20) null,
   add constraint FK_HICE_EXT_IMPORTACION foreign key (HICE_ID_IMPORTACION)
      references anfeca_modelo.HISTORICO_CERTIFICADO_IMPORTACION (HCIM_ID_IMPORTACION) on delete restrict on update restrict;
create unique index EXT_HICE_IMPORTACION_TIPO_UK on anfeca_modelo.HISTORICO_CERTIFICADO (HICE_ID_IMPORTACION, HICE_TIPO);

-- ---------------------------------------------------------------------
-- SOLICITUD: tipo de trámite, certificación (D-7 A) y antecedente validado
-- ---------------------------------------------------------------------
alter table anfeca_modelo.SOLICITUD
   add column SOLI_TIPO_TRAMITE     VARCHAR(20) not null,
   add column SOLI_CERTIFICACION    VARCHAR(20) not null,
   add column SOLI_ID_HISTORICO_IMPORTACION INT8 null,
   add constraint CK_SOLI_EXT_TIPO_TRAMITE check (SOLI_TIPO_TRAMITE in ('inicial','refrendo')),
   add constraint CK_SOLI_EXT_CERTIFICACION check (SOLI_CERTIFICACION in ('ANFECA','CACSLA','CACECA')),
   add constraint FK_SOLI_EXT_HISTORICO foreign key (SOLI_ID_HISTORICO_IMPORTACION)
      references anfeca_modelo.HISTORICO_CERTIFICADO_IMPORTACION (HCIM_ID_IMPORTACION) on delete restrict on update restrict;
create unique index EXT_SOLICITUD_FOLIO_UK on anfeca_modelo.SOLICITUD (SOLI_FOLIO_SOLICITUD);

-- ---------------------------------------------------------------------
-- PERSONA_ESTUDIO_PROFESIONAL: formación por trámite (etapa 1) y tipo
-- ---------------------------------------------------------------------
alter table anfeca_modelo.PERSONA_ESTUDIO_PROFESIONAL
   add column PEES_ID_SOLICITUD  INT4        null,
   add column PEES_TIPO          VARCHAR(10) null,
   add constraint CK_PEES_EXT_TIPO check (PEES_TIPO in ('ORIGEN','ULTIMO')),
   add constraint FK_PEES_EXT_SOLICITUD foreign key (PEES_ID_SOLICITUD)
      references anfeca_modelo.SOLICITUD (SOLI_ID_SOLICITUD) on delete restrict on update restrict;
create unique index EXT_PEES_SOLICITUD_TIPO_UK on anfeca_modelo.PERSONA_ESTUDIO_PROFESIONAL (PEES_ID_SOLICITUD, PEES_TIPO);

-- Un título "Otro" queda en el catálogo con estado REV y una sola vez por nivel y nombre.
create unique index EXT_ESTUDIO_NIVEL_NOMBRE_UK on anfeca_modelo.ESTUDIO_PROFESIONAL (ESPR_ID_NIVEL_PROFESIONAL, ESPR_NOMBRE);

-- ---------------------------------------------------------------------
-- SOLICITUD_DOCUMENTO: ruta del archivo en Storage (o URL del enlace)
-- ---------------------------------------------------------------------
alter table anfeca_modelo.SOLICITUD_DOCUMENTO
   add column SOCU_URL_ARCHIVO VARCHAR(500) not null;
comment on column anfeca_modelo.SOLICITUD_DOCUMENTO.SOCU_URL_ARCHIVO is 'EXT ANFECA: ruta relativa del disco local o URL del enlace de evidencias';
create index EXT_SOCU_SOLICITUD_DOCUMENTO_IDX on anfeca_modelo.SOLICITUD_DOCUMENTO (SOCU_ID_SOLICITUD, SOCU_ID_DOCUMENTO);

-- ---------------------------------------------------------------------
-- Claves estables de catálogo (para resolver IDs sin depender del orden)
-- ---------------------------------------------------------------------
create unique index EXT_ENTIDAD_NOMBRE_UK on anfeca_modelo.ENTIDAD_FEDERATIVA (ENFE_NOMBRE);
create unique index EXT_TIPO_COMUNICACION_UK on anfeca_modelo.TIPO_COMUNICACION (TICO_TIPO_COMUNICACION);
create unique index EXT_C_ESTADO_SOLICITUD_UK on anfeca_modelo.C_ESTADO_SOLICITUD (CESO_ESTADO_SOLICITUD);
create unique index EXT_DOCUMENTO_UK on anfeca_modelo.DOCUMENTO (DOCU_DESCRIPCION);
create unique index EXT_ESTADO_DOCUMENTO_UK on anfeca_modelo.ESTADO_DOCUMENTO (ESDO_ESTADO_DOCUMENTO);
create unique index EXT_ARTICULO_SUBINDICE_UK on anfeca_modelo.ARTICULO_SUBINDICE (ARCE_SUBINDICE);
create unique index EXT_NIVEL_PROFESIONAL_UK on anfeca_modelo.NIVEL_PROFESIONAL (NIPR_NIVEL_PROFESIONAL);
create unique index EXT_AREA_CERTIFICACION_UK on anfeca_modelo.AREA_CERTIFICACION (AREA_DESCRIPCION);
create unique index EXT_TIPO_PERSONAL_UK on anfeca_modelo.TIPO_PERSONAL_ACADEMICO (TIPA_DESCRIPCION);
create unique index EXT_ROL_NOMBRE_UK on anfeca_modelo.ROL (ROL_NOMBRE);
create unique index EXT_PRIVILEGIO_NOMBRE_UK on anfeca_modelo.PRIVILEGIO (PRIV_NOMBRE);
create unique index EXT_ROL_PRIVILEGIO_UK on anfeca_modelo.ROL_PRIVILEGIO (ROPR_ID_ROL, ROPR_ID_PRIVILEGIO);
