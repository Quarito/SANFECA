-- ANFECA — Modelo físico PowerDesigner en el esquema aislado anfeca_modelo.
-- Fuente estructural: anfeca_modelo_powerdesigner_corregido_claude.sql
-- SHA-256 de la fuente: 0963a389842f77f62a42c3ce40ca20a0f7f2b779cfb4881f2b0f7d4c04a37a8d
-- Los objetos se califican explícitamente. La transacción pertenece al instalador.
-- Uso manual en una base aislada nueva: psql -1 -v ON_ERROR_STOP=1 -f anfeca_modelo.sql

create schema anfeca_modelo;

/*==============================================================*/
/* Table: ACTA                                                  */
/*==============================================================*/
create table anfeca_modelo.ACTA (
   ACTA_ID_ACTA         SERIAL               not null,
   ACTA_ID_SOLICITUD    INT4                 null,
   ACTA_COORDINADOR     VARCHAR(1024)        null,
   ACTA_SECRETARIO      VARCHAR(1024)        null,
   ACTA_SEDE            VARCHAR(1024)        null,
   constraint PK_ACTA primary key (ACTA_ID_ACTA)
);

/*==============================================================*/
/* Index: ACTA_PK                                               */
/*==============================================================*/
create unique index ACTA_PK on anfeca_modelo.ACTA (
ACTA_ID_ACTA
);

/*==============================================================*/
/* Index: PROPORCIONA_FK                                        */
/*==============================================================*/
create  index PROPORCIONA_FK on anfeca_modelo.ACTA (
ACTA_ID_SOLICITUD
);

/*==============================================================*/
/* Table: ALCALDIA_MUNICIPIO                                    */
/*==============================================================*/
create table anfeca_modelo.ALCALDIA_MUNICIPIO (
   ALMU_ID_ALCALDIA     SERIAL               not null,
   ALMU_ID_ENTIDAD_FEDERATIVA INT4                 not null,
   ALMU_NOMBRE          VARCHAR(150)         not null,
   constraint PK_ALCALDIA_MUNICIPIO primary key (ALMU_ID_ALCALDIA)
);

/*==============================================================*/
/* Index: ALCALDIA_MUNICIPIO_PK                                 */
/*==============================================================*/
create unique index ALCALDIA_MUNICIPIO_PK on anfeca_modelo.ALCALDIA_MUNICIPIO (
ALMU_ID_ALCALDIA
);

/*==============================================================*/
/* Index: CONTIENE_FK                                           */
/*==============================================================*/
create  index CONTIENE_FK on anfeca_modelo.ALCALDIA_MUNICIPIO (
ALMU_ID_ENTIDAD_FEDERATIVA
);

/*==============================================================*/
/* Table: AREA_CERTIFICACION                                    */
/*==============================================================*/
create table anfeca_modelo.AREA_CERTIFICACION (
   AREA_ID_AREA_CERTIFICACION SERIAL               not null,
   AREA_DESCRIPCION     VARCHAR(200)         not null,
   constraint PK_AREA_CERTIFICACION primary key (AREA_ID_AREA_CERTIFICACION)
);

/*==============================================================*/
/* Index: AREA_CERTIFICACION_PK                                 */
/*==============================================================*/
create unique index AREA_CERTIFICACION_PK on anfeca_modelo.AREA_CERTIFICACION (
AREA_ID_AREA_CERTIFICACION
);

/*==============================================================*/
/* Table: ARTICULO_SUBINDICE                                    */
/*==============================================================*/
create table anfeca_modelo.ARTICULO_SUBINDICE (
   ARCE_ID_ARTICULO     SERIAL               not null,
   ARCE_SUBINDICE       VARCHAR(10)          not null,
   ARCE_DESCRIPCION     VARCHAR(100)         not null,
   constraint PK_ARTICULO_SUBINDICE primary key (ARCE_ID_ARTICULO)
);

/*==============================================================*/
/* Index: ARTICULO_SUBINDICE_PK                                 */
/*==============================================================*/
create unique index ARTICULO_SUBINDICE_PK on anfeca_modelo.ARTICULO_SUBINDICE (
ARCE_ID_ARTICULO
);

/*==============================================================*/
/* Table: CERTIFICADO                                           */
/*==============================================================*/
create table anfeca_modelo.CERTIFICADO (
   CERT_ID_CERTIFICADO  SERIAL               not null,
   CERT_ID_EVALUACION   INT4                 not null,
   CERT_FOLIO           VARCHAR(100)         not null,
   CERT_ANIO            VARCHAR(15)          not null,
   CERT_ESTADO          VARCHAR(5)           null,
   constraint PK_CERTIFICADO primary key (CERT_ID_CERTIFICADO)
);

/*==============================================================*/
/* Index: CERTIFICADO_PK                                        */
/*==============================================================*/
create unique index CERTIFICADO_PK on anfeca_modelo.CERTIFICADO (
CERT_ID_CERTIFICADO
);

/*==============================================================*/
/* Index: RELATIONSHIP_36_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_36_FK on anfeca_modelo.CERTIFICADO (
CERT_ID_EVALUACION
);

/*==============================================================*/
/* Table: COLONIA                                               */
/*==============================================================*/
create table anfeca_modelo.COLONIA (
   COLO_ID_COLONIA      SERIAL               not null,
   COLO_ID_ALCALDIA     INT4                 not null,
   COLO_NOMBRE          VARCHAR(150)         not null,
   COLO_CODIGO_POSTAL   VARCHAR(5)           not null,
   constraint PK_COLONIA primary key (COLO_ID_COLONIA)
);

/*==============================================================*/
/* Index: COLONIA_PK                                            */
/*==============================================================*/
create unique index COLONIA_PK on anfeca_modelo.COLONIA (
COLO_ID_COLONIA
);

/*==============================================================*/
/* Index: SE_COMPONE_FK                                         */
/*==============================================================*/
create  index SE_COMPONE_FK on anfeca_modelo.COLONIA (
COLO_ID_ALCALDIA
);

/*==============================================================*/
/* Table: COMUNICACION                                          */
/*==============================================================*/
create table anfeca_modelo.COMUNICACION (
   COMU_ID_COMUNICACION SERIAL               not null,
   COMU_ID_PERSONA      INT4                 not null,
   COMU_ID_TIPO_COMUNICACION INT4                 not null,
   COMU_VALOR           VARCHAR(150)         not null,
   COMU_VISIBLE         BOOL                 null,
   constraint PK_COMUNICACION primary key (COMU_ID_COMUNICACION, COMU_ID_PERSONA, COMU_ID_TIPO_COMUNICACION)
);

/*==============================================================*/
/* Index: CONTACTO_PK                                           */
/*==============================================================*/
create unique index CONTACTO_PK on anfeca_modelo.COMUNICACION (
COMU_ID_COMUNICACION,
COMU_ID_PERSONA,
COMU_ID_TIPO_COMUNICACION
);

/*==============================================================*/
/* Index: TIENE_CONTACTO_FK                                     */
/*==============================================================*/
create  index TIENE_CONTACTO_FK on anfeca_modelo.COMUNICACION (
COMU_ID_PERSONA
);

/*==============================================================*/
/* Index: CLASIFICADO_POR_FK                                    */
/*==============================================================*/
create  index CLASIFICADO_POR_FK on anfeca_modelo.COMUNICACION (
COMU_ID_TIPO_COMUNICACION
);

/*==============================================================*/
/* Table: CONVOCATORIA                                          */
/*==============================================================*/
create table anfeca_modelo.CONVOCATORIA (
   CONV_ID_CONVOCATORIA SERIAL               not null,
   CONV_ID_TIPO_CONVOCATORIA INT4                 not null,
   CONV_NOMBRE          VARCHAR(150)         not null,
   CONV_MONTO_RECUPERACION MONEY                not null,
   CONV_FECHA_INICIO_REGISTRO DATE                 not null,
   CONV_FECHA_FIN_REGISTRO DATE                 not null,
   CONV_FECHA_RESULTADO DATE                 not null,
   CONV_FECHA_ENTREGA_CERTIFICADOS DATE                 not null,
   CONV_PUNTUACION_MAX  INT4                 not null,
   CONV_PUNTUACION_MIN  INT4                 not null,
   CONV_LUGAR_ENTREGA    VARCHAR(300)         null,
   CONV_EVENTO_ENTREGA   VARCHAR(300)         null,
   constraint PK_CONVOCATORIA primary key (CONV_ID_CONVOCATORIA)
);

/*==============================================================*/
/* Index: CONVOCATORIA_PK                                       */
/*==============================================================*/
create unique index CONVOCATORIA_PK on anfeca_modelo.CONVOCATORIA (
CONV_ID_CONVOCATORIA
);

/*==============================================================*/
/* Index: RELATIONSHIP_31_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_31_FK on anfeca_modelo.CONVOCATORIA (
CONV_ID_TIPO_CONVOCATORIA
);

/*==============================================================*/
/* Table: C_ESTADO_SOLICITUD                                    */
/*==============================================================*/
create table anfeca_modelo.C_ESTADO_SOLICITUD (
   CESO_ID_C_ESTADO_SOLICITUD SERIAL               not null,
   CESO_ESTADO_SOLICITUD VARCHAR(50)          not null,
   constraint PK_C_ESTADO_SOLICITUD primary key (CESO_ID_C_ESTADO_SOLICITUD)
);

/*==============================================================*/
/* Index: C_ESTADO_SOLICITUD_PK                                 */
/*==============================================================*/
create unique index C_ESTADO_SOLICITUD_PK on anfeca_modelo.C_ESTADO_SOLICITUD (
CESO_ID_C_ESTADO_SOLICITUD
);

/*==============================================================*/
/* Table: DICTAMEN                                              */
/*==============================================================*/
create table anfeca_modelo.DICTAMEN (
   DICT_ID_DICTAMEN     SERIAL               not null,
   DICT_ID_TIPO_DICTAMEN INT4                 not null,
   DICT_ID_EVALUACION   INT4                 null,
   DICT_ENCABEZADO      VARCHAR(9)           not null,
   DICT_FOLIO_DICTAMEN  VARCHAR(9)           not null,
   DICT_FECHA_DICTAMEN_CREACION DATE                 not null,
   DICT_HORA_DICTAMEN_CREACION TIME                 not null,
   DICT_FECHA_DICTAMEN_ULTIMA_IMPR DATE                 null,
   constraint PK_DICTAMEN primary key (DICT_ID_DICTAMEN)
);

/*==============================================================*/
/* Table: DOCUMENTO                                             */
/*==============================================================*/
create table anfeca_modelo.DOCUMENTO (
   DOCU_ID_DOCUMENTO    SERIAL               not null,
   DOCU_DESCRIPCION     VARCHAR(150)         not null,
   DOCU_ESTATUS         VARCHAR(3)           not null,
   constraint PK_DOCUMENTO primary key (DOCU_ID_DOCUMENTO)
);

/*==============================================================*/
/* Table: DOMICILIO                                             */
/*==============================================================*/
create table anfeca_modelo.DOMICILIO (
   DOMI_ID_DOMICILIO    SERIAL               not null,
   DOMI_ID_PERSONA      INT4                 not null,
   DOMI_ID_ENTIDAD_FEDERATIVA INT4                 not null,
   DOMI_CALLE           VARCHAR(170)         not null,
   DOMI_NUMERO_EXT      VARCHAR(6)           not null,
   DOMI_NUMERO_INT      VARCHAR(4)           null,
   constraint PK_DOMICILIO primary key (DOMI_ID_DOMICILIO)
);

/*==============================================================*/
/* Index: DOMICILIO_PK                                          */
/*==============================================================*/
create unique index DOMICILIO_PK on anfeca_modelo.DOMICILIO (
DOMI_ID_DOMICILIO
);

/*==============================================================*/
/* Index: TIENE_DOM_FK                                          */
/*==============================================================*/
create  index TIENE_DOM_FK on anfeca_modelo.DOMICILIO (
DOMI_ID_PERSONA
);

/*==============================================================*/
/* Index: SE_ENCUENTRA_FK                                       */
/*==============================================================*/
create  index SE_ENCUENTRA_FK on anfeca_modelo.DOMICILIO (
DOMI_ID_ENTIDAD_FEDERATIVA
);

/*==============================================================*/
/* Table: ENTIDAD_FEDERATIVA                                    */
/*==============================================================*/
create table anfeca_modelo.ENTIDAD_FEDERATIVA (
   ENFE_ID_ENTIDAD_FEDERATIVA SERIAL               not null,
   ENFE_NOMBRE          VARCHAR(85)          not null,
   constraint PK_ENTIDAD_FEDERATIVA primary key (ENFE_ID_ENTIDAD_FEDERATIVA)
);

/*==============================================================*/
/* Index: ENTIDAD_FEDERATIVA_PK                                 */
/*==============================================================*/
create unique index ENTIDAD_FEDERATIVA_PK on anfeca_modelo.ENTIDAD_FEDERATIVA (
ENFE_ID_ENTIDAD_FEDERATIVA
);

/*==============================================================*/
/* Table: ESTADO_DOCUMENTO                                      */
/*==============================================================*/
create table anfeca_modelo.ESTADO_DOCUMENTO (
   ESDO_ID_ESTADO_DOCUMENTO SERIAL               not null,
   ESDO_ESTADO_DOCUMENTO VARCHAR(250)         not null,
   ESDO_ESTADO          VARCHAR(3)           not null,
   constraint PK_ESTADO_DOCUMENTO primary key (ESDO_ID_ESTADO_DOCUMENTO)
);

/*==============================================================*/
/* Index: ESTADO_DOCUMENTO_PK                                   */
/*==============================================================*/
create unique index ESTADO_DOCUMENTO_PK on anfeca_modelo.ESTADO_DOCUMENTO (
ESDO_ID_ESTADO_DOCUMENTO
);

/*==============================================================*/
/* Table: ESTADO_SOLICITUD                                      */
/*==============================================================*/
create table anfeca_modelo.ESTADO_SOLICITUD (
   ESSO_ID__ESTADO_SOLICITUD SERIAL               not null,
   ESSO_ID_C_ESTADO_SOLICITUD INT4                 null,
   ESSO_ID_SOLICITUD    INT4                 not null,
   ESSO_DESCRIPCION     VARCHAR(55)          not null,
   ESSO_SIGLAS          VARCHAR(2)           not null,
   ESSO_FECHA           DATE                 not null,
   ESSO_HORA            TIME                 not null,
   ESSO_MOTIVO_RECHAZO  VARCHAR(300)         null,
   constraint PK_ESTADO_SOLICITUD primary key (ESSO_ID__ESTADO_SOLICITUD)
);

/*==============================================================*/
/* Index: ESTADO_SOLICITUD_PK                                   */
/*==============================================================*/
create unique index ESTADO_SOLICITUD_PK on anfeca_modelo.ESTADO_SOLICITUD (
ESSO_ID__ESTADO_SOLICITUD
);

/*==============================================================*/
/* Index: RELATIONSHIP_42_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_42_FK on anfeca_modelo.ESTADO_SOLICITUD (
ESSO_ID_C_ESTADO_SOLICITUD
);

/*==============================================================*/
/* Table: ESTADO_SOLICITUD_DOCUMENTO                            */
/*==============================================================*/
create table anfeca_modelo.ESTADO_SOLICITUD_DOCUMENTO (
   ESDO_ID_ESTADO_SOLICITUD_DOCUME SERIAL               not null,
   ESDO_ID_DOCUMENTACION INT4                 not null,
   ESDO_ID_ESTADO_DOCUMENTO INT4                 not null,
   ESDO_OBSERVACION     VARCHAR(300)         not null,
   ESDO_FECHA           DATE                 not null,
   ESDO_HORA            TIME                 not null,
   constraint PK_ESTADO_SOLICITUD_DOCUMENTO primary key (ESDO_ID_ESTADO_SOLICITUD_DOCUME)
);

/*==============================================================*/
/* Index: ESTADO_SOLICITUD_DOCUMENTO_PK                         */
/*==============================================================*/
create unique index ESTADO_SOLICITUD_DOCUMENTO_PK on anfeca_modelo.ESTADO_SOLICITUD_DOCUMENTO (
ESDO_ID_ESTADO_SOLICITUD_DOCUME
);

/*==============================================================*/
/* Index: RELATIONSHIP_40_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_40_FK on anfeca_modelo.ESTADO_SOLICITUD_DOCUMENTO (
ESDO_ID_DOCUMENTACION
);

/*==============================================================*/
/* Index: RELATIONSHIP_41_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_41_FK on anfeca_modelo.ESTADO_SOLICITUD_DOCUMENTO (
ESDO_ID_ESTADO_DOCUMENTO
);

/*==============================================================*/
/* Table: ESTUDIO_PROFESIONAL                                   */
/*==============================================================*/
create table anfeca_modelo.ESTUDIO_PROFESIONAL (
   ESPR_ID_ESTUDIO_PROFESIONAL SERIAL               not null,
   ESPR_ID_NIVEL_PROFESIONAL INT4                 not null,
   ESPR_NOMBRE          VARCHAR(150)         not null,
   ESPR_ESTADO          VARCHAR(3)           not null,
   constraint PK_ESTUDIO_PROFESIONAL primary key (ESPR_ID_ESTUDIO_PROFESIONAL)
);

/*==============================================================*/
/* Index: GRADO_ACADEMICO_PK                                    */
/*==============================================================*/
create unique index GRADO_ACADEMICO_PK on anfeca_modelo.ESTUDIO_PROFESIONAL (
ESPR_ID_ESTUDIO_PROFESIONAL
);

/*==============================================================*/
/* Table: EVALUACION                                            */
/*==============================================================*/
create table anfeca_modelo.EVALUACION (
   EVAL_ID_EVALUACION   SERIAL               not null,
   EVAL_ID_SOLICITUD    INT4                 not null,
   EVAL_PUNTUACION      INT4                 null,
   EVAL_FECHA_EVALUACION DATE                 not null,
   EVAL_HORA_EVALUACION TIME                 not null,
   constraint PK_EVALUACION primary key (EVAL_ID_EVALUACION)
);

/*==============================================================*/
/* Index: EVALUACION_PK                                         */
/*==============================================================*/
create unique index EVALUACION_PK on anfeca_modelo.EVALUACION (
EVAL_ID_EVALUACION
);

/*==============================================================*/
/* Index: TIENE_ARTICULO_FK                                     */
/*==============================================================*/
-- OMITIDO: índice inválido sobre EVALUACION.SOLI_ID_ARTICULO (la columna pertenece a SOLICITUD).

/*==============================================================*/
/* Index: RELATIONSHIP_34_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_34_FK on anfeca_modelo.EVALUACION (
EVAL_ID_SOLICITUD
);

/*==============================================================*/
/* Table: HISTORICO_CERTIFICADO                                 */
/*==============================================================*/
create table anfeca_modelo.HISTORICO_CERTIFICADO (
   HICE_ID_HISTORICO_CERTIFICADO SERIAL               not null,
   HICE_ANIO_CERTIFICACION VARCHAR(4)           null,
   HICE_NUMERO_CERTIFICACION VARCHAR(4)           null,
   HICE_TIPO            VARCHAR(15)          null,
   HICE_FOLIO_CERTIFICADO VARCHAR(7)           not null,
   HICE_CURP            VARCHAR(18)          null,
   HICE_AREA_CERTIFICACION VARCHAR(120)         null,
   constraint PK_HISTORICO_CERTIFICADO primary key (HICE_ID_HISTORICO_CERTIFICADO, HICE_FOLIO_CERTIFICADO)
);

/*==============================================================*/
/* Table: INSTITUCION                                           */
/*==============================================================*/
create table anfeca_modelo.INSTITUCION (
   INST_ID_INSTITUCION  SERIAL               not null,
   INST_NUMERO_AFILIACION VARCHAR(7)           not null,
   INST_NOMBRE          VARCHAR(300)         not null,
   INST_FACULTAD        VARCHAR(500)         not null,
   INST_ZONA            VARCHAR(80)          not null,
   INS_COORD_REGIONAL_CERT_ACADEMI VARCHAR(200)         null,
   constraint PK_INSTITUCION primary key (INST_ID_INSTITUCION)
);

/*==============================================================*/
/* Index: INSTITUCION_PK                                        */
/*==============================================================*/
create unique index INSTITUCION_PK on anfeca_modelo.INSTITUCION (
INST_ID_INSTITUCION
);

/*==============================================================*/
/* Table: NIVEL_PROFESIONAL                                     */
/*==============================================================*/
create table anfeca_modelo.NIVEL_PROFESIONAL (
   NIPR_ID_NIVEL_PROFESIONAL SERIAL               not null,
   NIPR_NIVEL_PROFESIONAL VARCHAR(300)         not null,
   constraint PK_NIVEL_PROFESIONAL primary key (NIPR_ID_NIVEL_PROFESIONAL)
);

/*==============================================================*/
/* Table: PAGO                                                  */
/*==============================================================*/
create table anfeca_modelo.PAGO (
   PAGO_ID_PAGO         SERIAL               not null,
   PAGO_ID_SOLICITUD    INT4                 not null,
   PAGO_URL_COMPROBANTE VARCHAR(255)         not null,
   constraint PK_PAGO primary key (PAGO_ID_PAGO)
);

/*==============================================================*/
/* Index: PAGO_PK                                               */
/*==============================================================*/
create unique index PAGO_PK on anfeca_modelo.PAGO (
PAGO_ID_PAGO
);

/*==============================================================*/
/* Table: PERSONA                                               */
/*==============================================================*/
create table anfeca_modelo.PERSONA (
   PERS_ID_PERSONA      SERIAL               not null,
   PERS_ID_INSTITUCION  INT4                 null,
   PERS_NOMBRE          VARCHAR(150)         not null,
   PERS_APELLIDO_PATERNO VARCHAR(60)          null,
   PERS_APELLIDO_MATERNO VARCHAR(60)          null,
   PERS_CURP            VARCHAR(18)          not null,
   PERS_RFC             VARCHAR(13)          not null,
   PERS_GENERO          VARCHAR(1)           null,
   PERS_NUM_AFILIACION_INSTITUCION VARCHAR(10)          null,
   PERS_ACTIVO          BOOL                 null,
   constraint PK_PERSONA primary key (PERS_ID_PERSONA)
);

comment on table anfeca_modelo.PERSONA is
'//Entidad que definirá a una persona registrada en el SIPCA';

/*==============================================================*/
/* Index: PERSONA_PK                                            */
/*==============================================================*/
create unique index PERSONA_PK on anfeca_modelo.PERSONA (
PERS_ID_PERSONA
);

/*==============================================================*/
/* Index: PERTENECE_A_FK                                        */
/*==============================================================*/
create  index PERTENECE_A_FK on anfeca_modelo.PERSONA (
PERS_ID_INSTITUCION
);

/*==============================================================*/
/* Table: PERSONA_ESTUDIO_PROFESIONAL                           */
/*==============================================================*/
create table anfeca_modelo.PERSONA_ESTUDIO_PROFESIONAL (
   PEES_ID_PERSONA_ESTUDIO_PROFESI SERIAL               not null,
   PEES_ID_ESTUIDO_PROFESIONAL INT4                 not null,
   PEES_ID_PERSONA      INT4                 not null,
   PEES_URL_TITULO      VARCHAR(300)         null,
   PEES_CEDULA          VARCHAR(20)          null,
   PEES_URL_CEDULA      VARCHAR(180)         null,
   constraint PK_PERSONA_ESTUDIO_PROFESIONAL primary key (PEES_ID_PERSONA_ESTUDIO_PROFESI, PEES_ID_ESTUIDO_PROFESIONAL, PEES_ID_PERSONA)
);

/*==============================================================*/
/* Index: RELATIONSHIP_27_PK                                    */
/*==============================================================*/
create unique index RELATIONSHIP_27_PK on anfeca_modelo.PERSONA_ESTUDIO_PROFESIONAL (
PEES_ID_PERSONA_ESTUDIO_PROFESI,
PEES_ID_ESTUIDO_PROFESIONAL,
PEES_ID_PERSONA
);

/*==============================================================*/
/* Index: RELATIONSHIP_27_FK                                    */
/*==============================================================*/
create index RELATIONSHIP_27_FK on anfeca_modelo.PERSONA_ESTUDIO_PROFESIONAL (
PEES_ID_ESTUIDO_PROFESIONAL
);

/*==============================================================*/
/* Index: RELATIONSHIP_28_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_28_FK on anfeca_modelo.PERSONA_ESTUDIO_PROFESIONAL (
PEES_ID_PERSONA
);

/*==============================================================*/
/* Table: PRIVILEGIO                                            */
/*==============================================================*/
create table anfeca_modelo.PRIVILEGIO (
   PRIV_ID_PRIVILEGIO   SERIAL               not null,
   PRIV_NOMBRE          VARCHAR(150)         not null,
   PRIV_DESCRIPCION     VARCHAR(150)         not null,
   constraint PK_PRIVILEGIO primary key (PRIV_ID_PRIVILEGIO)
);

/*==============================================================*/
/* Index: PRIVILEGIO_PK                                         */
/*==============================================================*/
create unique index PRIVILEGIO_PK on anfeca_modelo.PRIVILEGIO (
PRIV_ID_PRIVILEGIO
);

/*==============================================================*/
/* Table: REFRENDO_CERTIFICADO                                  */
/*==============================================================*/
create table anfeca_modelo.REFRENDO_CERTIFICADO (
   RECE_ID_REFRENDO_CERTIFICACION SERIAL               not null,
   RECE_ID_EVALUACION   INT4                 null,
   RECE_ID_CERTIFICADO  INT4                 not null,
   RECE_NUMERO_REFRENDO INT4                 not null,
   RECE_ANIO_REFRENDO   INT4                 not null,
   RECE_ART14           BOOL                 null,
   RECE_ART13           BOOL                 not null,
   constraint PK_REFRENDO_CERTIFICADO primary key (RECE_ID_REFRENDO_CERTIFICACION)
);

/*==============================================================*/
/* Index: REFRENDO_CERTIFICACION_PK                             */
/*==============================================================*/
create unique index REFRENDO_CERTIFICACION_PK on anfeca_modelo.REFRENDO_CERTIFICADO (
RECE_ID_REFRENDO_CERTIFICACION
);

/*==============================================================*/
/* Index: RELATIONSHIP_37_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_37_FK on anfeca_modelo.REFRENDO_CERTIFICADO (
RECE_ID_EVALUACION
);

/*==============================================================*/
/* Index: RELATIONSHIP_38_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_38_FK on anfeca_modelo.REFRENDO_CERTIFICADO (
RECE_ID_CERTIFICADO
);

/*==============================================================*/
/* Table: ROL                                                   */
/*==============================================================*/
create table anfeca_modelo.ROL (
   ROL_ID_ROL           SERIAL               not null,
   ROL_NOMBRE           VARCHAR(150)         not null,
   constraint PK_ROL primary key (ROL_ID_ROL)
);

/*==============================================================*/
/* Index: ROL_PK                                                */
/*==============================================================*/
create unique index ROL_PK on anfeca_modelo.ROL (
ROL_ID_ROL
);

/*==============================================================*/
/* Table: ROL_PRIVILEGIO                                        */
/*==============================================================*/
create table anfeca_modelo.ROL_PRIVILEGIO (
   ROPR_ID_ROL_PRIVILEGIO SERIAL               not null,
   ROPR_ID_ROL          INT4                 not null,
   ROPR_ID_PRIVILEGIO   INT4                 not null,
   constraint PK_ROL_PRIVILEGIO primary key (ROPR_ID_ROL_PRIVILEGIO)
);

/*==============================================================*/
/* Index: ROL_PRIVILEGIO_PK                                     */
/*==============================================================*/
create unique index ROL_PRIVILEGIO_PK on anfeca_modelo.ROL_PRIVILEGIO (
ROPR_ID_ROL_PRIVILEGIO
);

/*==============================================================*/
/* Index: ROL_PRIVILEGIO_FK                                     */
/*==============================================================*/
create  index ROL_PRIVILEGIO_FK on anfeca_modelo.ROL_PRIVILEGIO (
ROPR_ID_ROL
);

/*==============================================================*/
/* Index: ROL_PRIVILEGIO2_FK                                    */
/*==============================================================*/
create  index ROL_PRIVILEGIO2_FK on anfeca_modelo.ROL_PRIVILEGIO (
ROPR_ID_PRIVILEGIO
);

/*==============================================================*/
/* Table: SEDE                                                  */
/*==============================================================*/
create table anfeca_modelo.SEDE (
   SEDE_ID_SEDE         SERIAL               not null,
   INST_ID_INSTITUCION  INT4                 not null,
   SEDE_DESCRIPCION     VARCHAR(150)         not null,
   constraint PK_SEDE primary key (SEDE_ID_SEDE)
);

/*==============================================================*/
/* Index: SEDE_PK                                               */
/*==============================================================*/
create unique index SEDE_PK on anfeca_modelo.SEDE (
SEDE_ID_SEDE
);

/*==============================================================*/
/* Index: TIENE_SEDE_FK                                         */
/*==============================================================*/
create  index TIENE_SEDE_FK on anfeca_modelo.SEDE (
INST_ID_INSTITUCION
);

/*==============================================================*/
/* Table: SOLICITUD                                             */
/*==============================================================*/
create table anfeca_modelo.SOLICITUD (
   SOLI_ID_SOLICITUD    SERIAL               not null,
   SOLI_ID_PERSONA      INT4                 not null,
   SOLI_ID_AREA_CERTIFICACION INT4                 not null,
   SOLI_ID_TIPO_PERSONAL_ACADEMICO INT4                 not null,
   SOLI_ID_CONVOCATORIA INT4                 null,
   SOLI_ID_ARTICULO     INT4                 null,
   SOLI_NUMERO_CERTIFICADO VARCHAR(10)          null,
   SOLI_FECHA_REGISTRO_SOLICITUD DATE                 not null,
   SOLI_FOLIO_SOLICITUD INT4                 not null,
   SOLI_ART13           BOOL                 not null,
   SOLI_ART14           BOOL                 not null,
   constraint PK_SOLICITUD primary key (SOLI_ID_SOLICITUD)
);

/*==============================================================*/
/* Index: SOLICITUD_PK                                          */
/*==============================================================*/
create unique index SOLICITUD_PK on anfeca_modelo.SOLICITUD (
SOLI_ID_SOLICITUD
);

/*==============================================================*/
/* Index: PUEDE_HACER_FK                                        */
/*==============================================================*/
create  index PUEDE_HACER_FK on anfeca_modelo.SOLICITUD (
SOLI_ID_PERSONA
);

/*==============================================================*/
/* Index: ESTA_ASOCIADA_FK                                      */
/*==============================================================*/
create  index ESTA_ASOCIADA_FK on anfeca_modelo.SOLICITUD (
SOLI_ID_TIPO_PERSONAL_ACADEMICO
);

/*==============================================================*/
/* Index: PERTENECE_A_AREA_FK                                   */
/*==============================================================*/
create  index PERTENECE_A_AREA_FK on anfeca_modelo.SOLICITUD (
SOLI_ID_AREA_CERTIFICACION
);

/*==============================================================*/
/* Index: RELATIONSHIP_32_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_32_FK on anfeca_modelo.SOLICITUD (
SOLI_ID_CONVOCATORIA
);

/*==============================================================*/
/* Table: SOLICITUD_DOCUMENTO                                   */
/*==============================================================*/
create table anfeca_modelo.SOLICITUD_DOCUMENTO (
   SOCU_ID_DOCUMENTACION SERIAL               not null,
   SOCU_ID_SOLICITUD    INT4                 not null,
   SOCU_ID_DOCUMENTO    INT4                 not null,
   SOCU_FECHA           DATE                 not null,
   SOCU_HORA            TIME                 not null,
   constraint PK_SOLICITUD_DOCUMENTO primary key (SOCU_ID_DOCUMENTACION)
);

/*==============================================================*/
/* Index: SOLICITUD_DOCUMENTO_PK                                */
/*==============================================================*/
create unique index SOLICITUD_DOCUMENTO_PK on anfeca_modelo.SOLICITUD_DOCUMENTO (
SOCU_ID_DOCUMENTACION
);

/*==============================================================*/
/* Index: TIENE_AS_FK                                           */
/*==============================================================*/
create  index TIENE_AS_FK on anfeca_modelo.SOLICITUD_DOCUMENTO (
SOCU_ID_SOLICITUD
);

/*==============================================================*/
/* Index: RELATIONSHIP_39_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_39_FK on anfeca_modelo.SOLICITUD_DOCUMENTO (
SOCU_ID_DOCUMENTO
);

/*==============================================================*/
/* Table: TIPO_COMUNICACION                                     */
/*==============================================================*/
create table anfeca_modelo.TIPO_COMUNICACION (
   TICO_ID_TIPO_COMUNICACION SERIAL               not null,
   TICO_TIPO_COMUNICACION VARCHAR(50)          not null,
   TICO_ESTADO          VARCHAR(3)           not null,
   constraint PK_TIPO_COMUNICACION primary key (TICO_ID_TIPO_COMUNICACION)
);

/*==============================================================*/
/* Index: TIPO_COMUNICACION_PK                                  */
/*==============================================================*/
create unique index TIPO_COMUNICACION_PK on anfeca_modelo.TIPO_COMUNICACION (
TICO_ID_TIPO_COMUNICACION
);

/*==============================================================*/
/* Table: TIPO_CONVOCATORIA                                     */
/*==============================================================*/
create table anfeca_modelo.TIPO_CONVOCATORIA (
   TICO_ID_TIPO_CONVOCATORIA SERIAL               not null,
   TICO_TIPO_CONVOCATORIA VARCHAR(50)          not null,
   TICO_ESTADO          VARCHAR(3)           not null,
   TICO_SIGLAS          VARCHAR(65)          not null,
   constraint PK_TIPO_CONVOCATORIA primary key (TICO_ID_TIPO_CONVOCATORIA)
);

/*==============================================================*/
/* Index: TIPO_CONVOCATORIA_PK                                  */
/*==============================================================*/
create unique index TIPO_CONVOCATORIA_PK on anfeca_modelo.TIPO_CONVOCATORIA (
TICO_ID_TIPO_CONVOCATORIA
);

/*==============================================================*/
/* Table: TIPO_DICTAMEN                                         */
/*==============================================================*/
create table anfeca_modelo.TIPO_DICTAMEN (
   TIDI_ID_TIPO_DICTAMEN SERIAL               not null,
   TIDI_DESCRIPCION     VARCHAR(120)         not null,
   TIDI_TIPO_DICTAMEN_ESTADO BOOL                 not null,
   constraint PK_TIPO_DICTAMEN primary key (TIDI_ID_TIPO_DICTAMEN)
);

/*==============================================================*/
/* Table: TIPO_PERSONAL_ACADEMICO                               */
/*==============================================================*/
create table anfeca_modelo.TIPO_PERSONAL_ACADEMICO (
   TIPA_ID_TIPO_PERSONAL_ACADEMICO SERIAL               not null,
   TIPA_DESCRIPCION     VARCHAR(150)         not null,
   TIPA_ESTADO          BOOL                 not null,
   constraint PK_TIPO_PERSONAL_ACADEMICO primary key (TIPA_ID_TIPO_PERSONAL_ACADEMICO)
);

/*==============================================================*/
/* Index: TIPO_PERSONAL_ACADEMICO_PK                            */
/*==============================================================*/
create unique index TIPO_PERSONAL_ACADEMICO_PK on anfeca_modelo.TIPO_PERSONAL_ACADEMICO (
TIPA_ID_TIPO_PERSONAL_ACADEMICO
);

/*==============================================================*/
/* Table: USUARIO                                               */
/*==============================================================*/
create table anfeca_modelo.USUARIO (
   USUA_ID_USUARIO      SERIAL               not null,
   USUA_ID_PERSONA      INT4                 not null,
   USUA_ID_ROL          INT4                 not null,
   USUA_NOMBRE          VARCHAR(150)         not null,
   USUA_CONTRASENIA     VARCHAR(255)         not null,
   USUA_ESTATUS         VARCHAR(20)          not null,
   constraint PK_USUARIO primary key (USUA_ID_USUARIO)
);

/*==============================================================*/
/* Index: USUARIO_PK                                            */
/*==============================================================*/
create unique index USUARIO_PK on anfeca_modelo.USUARIO (
USUA_ID_USUARIO
);

/*==============================================================*/
/* Index: TIENE_USUARIO2_FK                                     */
/*==============================================================*/
create  index TIENE_USUARIO2_FK on anfeca_modelo.USUARIO (
USUA_ID_PERSONA
);

/*==============================================================*/
/* Index: TIENE_ROL_FK                                          */
/*==============================================================*/
create  index TIENE_ROL_FK on anfeca_modelo.USUARIO (
USUA_ID_ROL
);

alter table anfeca_modelo.ACTA
   add constraint FK_ACTA_PROPORCIO_SOLICITU foreign key (ACTA_ID_SOLICITUD)
      references anfeca_modelo.SOLICITUD (SOLI_ID_SOLICITUD)
      on delete restrict on update restrict;

alter table anfeca_modelo.ALCALDIA_MUNICIPIO
   add constraint FK_ALCALDIA_CONTIENE_ENTIDAD_ foreign key (ALMU_ID_ENTIDAD_FEDERATIVA)
      references anfeca_modelo.ENTIDAD_FEDERATIVA (ENFE_ID_ENTIDAD_FEDERATIVA)
      on delete restrict on update restrict;

alter table anfeca_modelo.CERTIFICADO
   add constraint FK_CERTIFIC_RELATIONS_EVALUACI foreign key (CERT_ID_EVALUACION)
      references anfeca_modelo.EVALUACION (EVAL_ID_EVALUACION)
      on delete restrict on update restrict;

alter table anfeca_modelo.COLONIA
   add constraint FK_COLONIA_SE_COMPON_ALCALDIA foreign key (COLO_ID_ALCALDIA)
      references anfeca_modelo.ALCALDIA_MUNICIPIO (ALMU_ID_ALCALDIA)
      on delete restrict on update restrict;

alter table anfeca_modelo.COMUNICACION
   add constraint FK_COMUNICA_CLASIFICA_TIPO_COM foreign key (COMU_ID_TIPO_COMUNICACION)
      references anfeca_modelo.TIPO_COMUNICACION (TICO_ID_TIPO_COMUNICACION)
      on delete restrict on update restrict;

alter table anfeca_modelo.COMUNICACION
   add constraint FK_COMUNICA_TIENE_CON_PERSONA foreign key (COMU_ID_PERSONA)
      references anfeca_modelo.PERSONA (PERS_ID_PERSONA)
      on delete restrict on update restrict;

alter table anfeca_modelo.CONVOCATORIA
   add constraint FK_CONVOCAT_RELATIONS_TIPO_CON foreign key (CONV_ID_TIPO_CONVOCATORIA)
      references anfeca_modelo.TIPO_CONVOCATORIA (TICO_ID_TIPO_CONVOCATORIA)
      on delete restrict on update restrict;

alter table anfeca_modelo.DICTAMEN
   add constraint FK_DICTAMEN_REFERENCE_TIPO_DIC foreign key (DICT_ID_TIPO_DICTAMEN)
      references anfeca_modelo.TIPO_DICTAMEN (TIDI_ID_TIPO_DICTAMEN)
      on delete restrict on update restrict;

alter table anfeca_modelo.DICTAMEN
   add constraint FK_DICTAMEN_REFERENCE_EVALUACI foreign key (DICT_ID_EVALUACION)
      references anfeca_modelo.EVALUACION (EVAL_ID_EVALUACION)
      on delete restrict on update restrict;

alter table anfeca_modelo.DOMICILIO
   add constraint FK_DOMICILI_SE_ENCUEN_ENTIDAD_ foreign key (DOMI_ID_ENTIDAD_FEDERATIVA)
      references anfeca_modelo.ENTIDAD_FEDERATIVA (ENFE_ID_ENTIDAD_FEDERATIVA)
      on delete restrict on update restrict;

alter table anfeca_modelo.DOMICILIO
   add constraint FK_DOMICILI_TIENE_DOM_PERSONA foreign key (DOMI_ID_PERSONA)
      references anfeca_modelo.PERSONA (PERS_ID_PERSONA)
      on delete restrict on update restrict;

alter table anfeca_modelo.ESTADO_SOLICITUD
   add constraint FK_ESTADO_S_REFERENCE_SOLICITU foreign key (ESSO_ID_SOLICITUD)
      references anfeca_modelo.SOLICITUD (SOLI_ID_SOLICITUD)
      on delete restrict on update restrict;

alter table anfeca_modelo.ESTADO_SOLICITUD
   add constraint FK_ESTADO_S_RELATIONS_C_ESTADO foreign key (ESSO_ID_C_ESTADO_SOLICITUD)
      references anfeca_modelo.C_ESTADO_SOLICITUD (CESO_ID_C_ESTADO_SOLICITUD)
      on delete restrict on update restrict;

alter table anfeca_modelo.ESTADO_SOLICITUD_DOCUMENTO
   add constraint FK_ESTADO_S_RELATIONS_SOLICITU foreign key (ESDO_ID_DOCUMENTACION)
      references anfeca_modelo.SOLICITUD_DOCUMENTO (SOCU_ID_DOCUMENTACION)
      on delete restrict on update restrict;

alter table anfeca_modelo.ESTADO_SOLICITUD_DOCUMENTO
   add constraint FK_ESTADO_S_RELATIONS_ESTADO_D foreign key (ESDO_ID_ESTADO_DOCUMENTO)
      references anfeca_modelo.ESTADO_DOCUMENTO (ESDO_ID_ESTADO_DOCUMENTO)
      on delete restrict on update restrict;

alter table anfeca_modelo.ESTUDIO_PROFESIONAL
   add constraint FK_ESTUDIO__REFERENCE_NIVEL_PR foreign key (ESPR_ID_NIVEL_PROFESIONAL)
      references anfeca_modelo.NIVEL_PROFESIONAL (NIPR_ID_NIVEL_PROFESIONAL)
      on delete restrict on update restrict;

alter table anfeca_modelo.EVALUACION
   add constraint FK_EVALUACI_RELATIONS_SOLICITU foreign key (EVAL_ID_SOLICITUD)
      references anfeca_modelo.SOLICITUD (SOLI_ID_SOLICITUD)
      on delete restrict on update restrict;

alter table anfeca_modelo.PAGO
   add constraint FK_PAGO_REFERENCE_SOLICITU foreign key (PAGO_ID_SOLICITUD)
      references anfeca_modelo.SOLICITUD (SOLI_ID_SOLICITUD)
      on delete restrict on update restrict;

alter table anfeca_modelo.PERSONA
   add constraint FK_PERSONA_PERTENECE_INSTITUC foreign key (PERS_ID_INSTITUCION)
      references anfeca_modelo.INSTITUCION (INST_ID_INSTITUCION)
      on delete restrict on update restrict;

alter table anfeca_modelo.PERSONA_ESTUDIO_PROFESIONAL
   add constraint FK_PERSONA__RELATIONS_ESTUDIO_ foreign key (PEES_ID_ESTUIDO_PROFESIONAL)
      references anfeca_modelo.ESTUDIO_PROFESIONAL (ESPR_ID_ESTUDIO_PROFESIONAL)
      on delete restrict on update restrict;

alter table anfeca_modelo.PERSONA_ESTUDIO_PROFESIONAL
   add constraint FK_PERSONA__RELATIONS_PERSONA foreign key (PEES_ID_PERSONA)
      references anfeca_modelo.PERSONA (PERS_ID_PERSONA)
      on delete restrict on update restrict;

alter table anfeca_modelo.REFRENDO_CERTIFICADO
   add constraint FK_REFRENDO_RELATIONS_EVALUACI foreign key (RECE_ID_EVALUACION)
      references anfeca_modelo.EVALUACION (EVAL_ID_EVALUACION)
      on delete restrict on update restrict;

alter table anfeca_modelo.REFRENDO_CERTIFICADO
   add constraint FK_REFRENDO_RELATIONS_CERTIFIC foreign key (RECE_ID_CERTIFICADO)
      references anfeca_modelo.CERTIFICADO (CERT_ID_CERTIFICADO)
      on delete restrict on update restrict;

alter table anfeca_modelo.ROL_PRIVILEGIO
   add constraint FK_ROL_PRIV_ROL_PRIVI_ROL foreign key (ROPR_ID_ROL)
      references anfeca_modelo.ROL (ROL_ID_ROL)
      on delete restrict on update restrict;

alter table anfeca_modelo.ROL_PRIVILEGIO
   add constraint FK_ROL_PRIV_ROL_PRIVI_PRIVILEG foreign key (ROPR_ID_PRIVILEGIO)
      references anfeca_modelo.PRIVILEGIO (PRIV_ID_PRIVILEGIO)
      on delete restrict on update restrict;

alter table anfeca_modelo.SEDE
   add constraint FK_SEDE_TIENE_SED_INSTITUC foreign key (INST_ID_INSTITUCION)
      references anfeca_modelo.INSTITUCION (INST_ID_INSTITUCION)
      on delete restrict on update restrict;

alter table anfeca_modelo.SOLICITUD
   add constraint FK_SOLICITU_ESTA_ASOC_TIPO_PER foreign key (SOLI_ID_TIPO_PERSONAL_ACADEMICO)
      references anfeca_modelo.TIPO_PERSONAL_ACADEMICO (TIPA_ID_TIPO_PERSONAL_ACADEMICO)
      on delete restrict on update restrict;

alter table anfeca_modelo.SOLICITUD
   add constraint FK_SOLICITU_PERTENECE_AREA_CER foreign key (SOLI_ID_AREA_CERTIFICACION)
      references anfeca_modelo.AREA_CERTIFICACION (AREA_ID_AREA_CERTIFICACION)
      on delete restrict on update restrict;

alter table anfeca_modelo.SOLICITUD
   add constraint FK_SOLICITU_PUEDE_HAC_PERSONA foreign key (SOLI_ID_PERSONA)
      references anfeca_modelo.PERSONA (PERS_ID_PERSONA)
      on delete restrict on update restrict;

alter table anfeca_modelo.SOLICITUD
   add constraint FK_SOLICITU_RELATIONS_CONVOCAT foreign key (SOLI_ID_CONVOCATORIA)
      references anfeca_modelo.CONVOCATORIA (CONV_ID_CONVOCATORIA)
      on delete restrict on update restrict;

alter table anfeca_modelo.SOLICITUD
   add constraint FK_SOLICITU_TIENE_ART_ARTICULO foreign key (SOLI_ID_ARTICULO)
      references anfeca_modelo.ARTICULO_SUBINDICE (ARCE_ID_ARTICULO)
      on delete restrict on update restrict;

alter table anfeca_modelo.SOLICITUD_DOCUMENTO
   add constraint FK_SOLICITU_RELATIONS_DOCUMENT foreign key (SOCU_ID_DOCUMENTO)
      references anfeca_modelo.DOCUMENTO (DOCU_ID_DOCUMENTO)
      on delete restrict on update restrict;

alter table anfeca_modelo.SOLICITUD_DOCUMENTO
   add constraint FK_SOLICITU_TIENE_AS_SOLICITU foreign key (SOCU_ID_SOLICITUD)
      references anfeca_modelo.SOLICITUD (SOLI_ID_SOLICITUD)
      on delete restrict on update restrict;

alter table anfeca_modelo.USUARIO
   add constraint FK_USUARIO_TIENE_ROL_ROL foreign key (USUA_ID_ROL)
      references anfeca_modelo.ROL (ROL_ID_ROL)
      on delete restrict on update restrict;

alter table anfeca_modelo.USUARIO
   add constraint FK_USUARIO_TIENE_USU_PERSONA foreign key (USUA_ID_PERSONA)
      references anfeca_modelo.PERSONA (PERS_ID_PERSONA)
      on delete restrict on update restrict;
