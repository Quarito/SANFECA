# Auditoría de las 36 tablas PowerDesigner

## Alcance y evidencia

Se auditó el commit `74fb30b`, cabeza del [PR #11](https://github.com/Quarito/SANFECA/pull/11), con PostgreSQL 18.7. Se comprobó el SHA-256 completo de `anfeca_modelo_powerdesigner_corregido_claude.sql` antes de ejecutar el archivo.

La referencia se ejecutó directamente con `psql -X -v ON_ERROR_STOP=1` en `referencia_pdm36`. En una segunda base vacía, `instalado_pdm36`, se aplicó `php artisan anfeca:modelo-instalar --ejecutar --confirmar --no-interaction`. Las dos bases se crearon con `template0` en el mismo clúster temporal. Ambos volcados se generaron con `pg_dump -n anfeca_modelo --schema-only` y se retiraron exclusivamente las líneas variables `\restrict` y `\unrestrict` producidas por el cliente. Se incluyeron propietarios, permisos, comentarios y el SQL restante. `cmp` devolvió igualdad.

| Evidencia | Resultado |
| --- | --- |
| SHA-256 de la fuente aprobada | `0963a389842f77f62a42c3ce40ca20a0f7f2b779cfb4881f2b0f7d4c04a37a8d` |
| SHA-256 de cada volcado normalizado | `aceed2c859f69addc6c1916c1db9fff56b5f4798a975a00b6cdfdf75e9a8ad52` |
| SHA-256 de cada inventario JSONL ordenado | `3ee2da2ae7ee8b737d33b3c70ec4e797e589a5d84338cc9f4cf55a9f88225211` |
| Inventario base | 36 tablas, 173 columnas, 36 PK, 35 FK, 96 índices y 36 secuencias. |
| Restricciones y unicidad | 139 restricciones `NOT NULL` en el catálogo de PostgreSQL 18. Cero restricciones independientes `UNIQUE` y `CHECK` en el DDL base. Hay 67 índices únicos, incluidos los 36 que respaldan las PK. |
| Triggers y funciones | Cero triggers de aplicación y cero funciones propias del esquema base. PostgreSQL genera 140 triggers internos de integridad para las FK. |

Se compararon `pg_class`, `pg_attribute`, `pg_attrdef`, `pg_collation`, `pg_constraint`, `pg_index`, `pg_sequence`, `pg_depend`, `pg_trigger`, `pg_proc` e `information_schema.columns`. Las definiciones registraron tipo, longitud, precisión, escala, nulabilidad, predeterminados, identidad y reglas de restricciones. Para las secuencias se registraron el tipo, inicio, incremento, límites, cache, ciclo y el vínculo a su columna. Los identificadores se muestran tal como los registra PostgreSQL para nombres SQL sin comillas.

## Inventario base instalado

`Columnas` e `Índices` indican las cantidades reales. `Restricciones` cuenta las de nulabilidad, porque las PK y FK se consignan por separado. El detalle que sigue incluye los nombres y definiciones.

| Número | Tabla | Columnas | PK | FK | Índices | Restricciones | Resultado |
| ---: | --- | ---: | --- | --- | ---: | --- | --- |
| 1 | `acta` | 5 | `(acta_id_acta)` | `fk_acta_proporcio_solicitu` | 3 | 1 NOT NULL | PASÓ |
| 2 | `alcaldia_municipio` | 3 | `(almu_id_alcaldia)` | `fk_alcaldia_contiene_entidad_` | 3 | 3 NOT NULL | PASÓ |
| 3 | `area_certificacion` | 2 | `(area_id_area_certificacion)` | — | 2 | 2 NOT NULL | PASÓ |
| 4 | `articulo_subindice` | 3 | `(arce_id_articulo)` | — | 2 | 3 NOT NULL | PASÓ |
| 5 | `c_estado_solicitud` | 2 | `(ceso_id_c_estado_solicitud)` | — | 2 | 2 NOT NULL | PASÓ |
| 6 | `certificado` | 5 | `(cert_id_certificado)` | `fk_certific_relations_evaluaci` | 3 | 4 NOT NULL | PASÓ |
| 7 | `colonia` | 4 | `(colo_id_colonia)` | `fk_colonia_se_compon_alcaldia` | 3 | 4 NOT NULL | PASÓ |
| 8 | `comunicacion` | 5 | `(comu_id_comunicacion, comu_id_persona, comu_id_tipo_comunicacion)` | `fk_comunica_clasifica_tipo_com`, `fk_comunica_tiene_con_persona` | 4 | 4 NOT NULL | PASÓ |
| 9 | `convocatoria` | 12 | `(conv_id_convocatoria)` | `fk_convocat_relations_tipo_con` | 3 | 10 NOT NULL | PASÓ |
| 10 | `dictamen` | 8 | `(dict_id_dictamen)` | `fk_dictamen_reference_evaluaci`, `fk_dictamen_reference_tipo_dic` | 1 | 6 NOT NULL | PASÓ |
| 11 | `documento` | 3 | `(docu_id_documento)` | — | 1 | 3 NOT NULL | PASÓ |
| 12 | `domicilio` | 6 | `(domi_id_domicilio)` | `fk_domicili_se_encuen_entidad_`, `fk_domicili_tiene_dom_persona` | 4 | 5 NOT NULL | PASÓ |
| 13 | `entidad_federativa` | 2 | `(enfe_id_entidad_federativa)` | — | 2 | 2 NOT NULL | PASÓ |
| 14 | `estado_documento` | 3 | `(esdo_id_estado_documento)` | — | 2 | 3 NOT NULL | PASÓ |
| 15 | `estado_solicitud` | 8 | `(esso_id__estado_solicitud)` | `fk_estado_s_reference_solicitu`, `fk_estado_s_relations_c_estado` | 3 | 6 NOT NULL | PASÓ |
| 16 | `estado_solicitud_documento` | 6 | `(esdo_id_estado_solicitud_docume)` | `fk_estado_s_relations_estado_d`, `fk_estado_s_relations_solicitu` | 4 | 6 NOT NULL | PASÓ |
| 17 | `estudio_profesional` | 4 | `(espr_id_estudio_profesional)` | `fk_estudio__reference_nivel_pr` | 2 | 4 NOT NULL | PASÓ |
| 18 | `evaluacion` | 5 | `(eval_id_evaluacion)` | `fk_evaluaci_relations_solicitu` | 3 | 4 NOT NULL | PASÓ |
| 19 | `historico_certificado` | 7 | `(hice_id_historico_certificado, hice_folio_certificado)` | — | 1 | 2 NOT NULL | PASÓ |
| 20 | `institucion` | 6 | `(inst_id_institucion)` | — | 2 | 5 NOT NULL | PASÓ |
| 21 | `nivel_profesional` | 2 | `(nipr_id_nivel_profesional)` | — | 1 | 2 NOT NULL | PASÓ |
| 22 | `pago` | 3 | `(pago_id_pago)` | `fk_pago_reference_solicitu` | 2 | 3 NOT NULL | PASÓ |
| 23 | `persona` | 10 | `(pers_id_persona)` | `fk_persona_pertenece_instituc` | 3 | 4 NOT NULL | PASÓ |
| 24 | `persona_estudio_profesional` | 6 | `(pees_id_persona_estudio_profesi, pees_id_estuido_profesional, pees_id_persona)` | `fk_persona__relations_estudio_`, `fk_persona__relations_persona` | 4 | 3 NOT NULL | PASÓ |
| 25 | `privilegio` | 3 | `(priv_id_privilegio)` | — | 2 | 3 NOT NULL | PASÓ |
| 26 | `refrendo_certificado` | 7 | `(rece_id_refrendo_certificacion)` | `fk_refrendo_relations_certific`, `fk_refrendo_relations_evaluaci` | 4 | 5 NOT NULL | PASÓ |
| 27 | `rol` | 2 | `(rol_id_rol)` | — | 2 | 2 NOT NULL | PASÓ |
| 28 | `rol_privilegio` | 3 | `(ropr_id_rol_privilegio)` | `fk_rol_priv_rol_privi_privileg`, `fk_rol_priv_rol_privi_rol` | 4 | 3 NOT NULL | PASÓ |
| 29 | `sede` | 3 | `(sede_id_sede)` | `fk_sede_tiene_sed_instituc` | 3 | 3 NOT NULL | PASÓ |
| 30 | `solicitud` | 11 | `(soli_id_solicitud)` | `fk_solicitu_esta_asoc_tipo_per`, `fk_solicitu_pertenece_area_cer`, `fk_solicitu_puede_hac_persona`, `fk_solicitu_relations_convocat`, `fk_solicitu_tiene_art_articulo` | 6 | 8 NOT NULL | PASÓ |
| 31 | `solicitud_documento` | 5 | `(socu_id_documentacion)` | `fk_solicitu_relations_document`, `fk_solicitu_tiene_as_solicitu` | 4 | 5 NOT NULL | PASÓ |
| 32 | `tipo_comunicacion` | 3 | `(tico_id_tipo_comunicacion)` | — | 2 | 3 NOT NULL | PASÓ |
| 33 | `tipo_convocatoria` | 4 | `(tico_id_tipo_convocatoria)` | — | 2 | 4 NOT NULL | PASÓ |
| 34 | `tipo_dictamen` | 3 | `(tidi_id_tipo_dictamen)` | — | 1 | 3 NOT NULL | PASÓ |
| 35 | `tipo_personal_academico` | 3 | `(tipa_id_tipo_personal_academico)` | — | 2 | 3 NOT NULL | PASÓ |
| 36 | `usuario` | 6 | `(usua_id_usuario)` | `fk_usuario_tiene_rol_rol`, `fk_usuario_tiene_usu_persona` | 4 | 6 NOT NULL | PASÓ |

## Detalle estructural por tabla

Cada columna se lista en orden físico. El sufijo `!` representa `NOT NULL`. El texto entre paréntesis forma parte del tipo. Se indica el predeterminado cuando existe. Las definiciones de FK incluyen las acciones de actualización y borrado. Los índices se muestran según `pg_get_indexdef`, con el método, unicidad, columnas y orden.

### `acta`

- Columnas. `acta_id_acta integer ! = nextval('anfeca_modelo.acta_acta_id_acta_seq'::regclass)`, `acta_id_solicitud integer`, `acta_coordinador character varying(1024)`, `acta_secretario character varying(1024)`, `acta_sede character varying(1024)`.
- Llaves. `fk_acta_proporcio_solicitu FOREIGN KEY (acta_id_solicitud) REFERENCES anfeca_modelo.solicitud(soli_id_solicitud) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_acta PRIMARY KEY (acta_id_acta)`.
- Índices. `CREATE UNIQUE INDEX acta_pk ON anfeca_modelo.acta USING btree (acta_id_acta)`, `CREATE UNIQUE INDEX pk_acta ON anfeca_modelo.acta USING btree (acta_id_acta)`, `CREATE INDEX proporciona_fk ON anfeca_modelo.acta USING btree (acta_id_solicitud)`.
- Secuencia. `acta_acta_id_acta_seq → acta_id_acta`.

### `alcaldia_municipio`

- Columnas. `almu_id_alcaldia integer ! = nextval('anfeca_modelo.alcaldia_municipio_almu_id_alcaldia_seq'::regclass)`, `almu_id_entidad_federativa integer !`, `almu_nombre character varying(150) !`.
- Llaves. `fk_alcaldia_contiene_entidad_ FOREIGN KEY (almu_id_entidad_federativa) REFERENCES anfeca_modelo.entidad_federativa(enfe_id_entidad_federativa) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_alcaldia_municipio PRIMARY KEY (almu_id_alcaldia)`.
- Índices. `CREATE UNIQUE INDEX alcaldia_municipio_pk ON anfeca_modelo.alcaldia_municipio USING btree (almu_id_alcaldia)`, `CREATE INDEX contiene_fk ON anfeca_modelo.alcaldia_municipio USING btree (almu_id_entidad_federativa)`, `CREATE UNIQUE INDEX pk_alcaldia_municipio ON anfeca_modelo.alcaldia_municipio USING btree (almu_id_alcaldia)`.
- Secuencia. `alcaldia_municipio_almu_id_alcaldia_seq → almu_id_alcaldia`.

### `area_certificacion`

- Columnas. `area_id_area_certificacion integer ! = nextval('anfeca_modelo.area_certificacion_area_id_area_certificacion_seq'::regclass)`, `area_descripcion character varying(200) !`.
- Llaves. `pk_area_certificacion PRIMARY KEY (area_id_area_certificacion)`.
- Índices. `CREATE UNIQUE INDEX area_certificacion_pk ON anfeca_modelo.area_certificacion USING btree (area_id_area_certificacion)`, `CREATE UNIQUE INDEX pk_area_certificacion ON anfeca_modelo.area_certificacion USING btree (area_id_area_certificacion)`.
- Secuencia. `area_certificacion_area_id_area_certificacion_seq → area_id_area_certificacion`.

### `articulo_subindice`

- Columnas. `arce_id_articulo integer ! = nextval('anfeca_modelo.articulo_subindice_arce_id_articulo_seq'::regclass)`, `arce_subindice character varying(10) !`, `arce_descripcion character varying(100) !`.
- Llaves. `pk_articulo_subindice PRIMARY KEY (arce_id_articulo)`.
- Índices. `CREATE UNIQUE INDEX articulo_subindice_pk ON anfeca_modelo.articulo_subindice USING btree (arce_id_articulo)`, `CREATE UNIQUE INDEX pk_articulo_subindice ON anfeca_modelo.articulo_subindice USING btree (arce_id_articulo)`.
- Secuencia. `articulo_subindice_arce_id_articulo_seq → arce_id_articulo`.

### `c_estado_solicitud`

- Columnas. `ceso_id_c_estado_solicitud integer ! = nextval('anfeca_modelo.c_estado_solicitud_ceso_id_c_estado_solicitud_seq'::regclass)`, `ceso_estado_solicitud character varying(50) !`.
- Llaves. `pk_c_estado_solicitud PRIMARY KEY (ceso_id_c_estado_solicitud)`.
- Índices. `CREATE UNIQUE INDEX c_estado_solicitud_pk ON anfeca_modelo.c_estado_solicitud USING btree (ceso_id_c_estado_solicitud)`, `CREATE UNIQUE INDEX pk_c_estado_solicitud ON anfeca_modelo.c_estado_solicitud USING btree (ceso_id_c_estado_solicitud)`.
- Secuencia. `c_estado_solicitud_ceso_id_c_estado_solicitud_seq → ceso_id_c_estado_solicitud`.

### `certificado`

- Columnas. `cert_id_certificado integer ! = nextval('anfeca_modelo.certificado_cert_id_certificado_seq'::regclass)`, `cert_id_evaluacion integer !`, `cert_folio character varying(100) !`, `cert_anio character varying(15) !`, `cert_estado character varying(5)`.
- Llaves. `fk_certific_relations_evaluaci FOREIGN KEY (cert_id_evaluacion) REFERENCES anfeca_modelo.evaluacion(eval_id_evaluacion) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_certificado PRIMARY KEY (cert_id_certificado)`.
- Índices. `CREATE UNIQUE INDEX certificado_pk ON anfeca_modelo.certificado USING btree (cert_id_certificado)`, `CREATE UNIQUE INDEX pk_certificado ON anfeca_modelo.certificado USING btree (cert_id_certificado)`, `CREATE INDEX relationship_36_fk ON anfeca_modelo.certificado USING btree (cert_id_evaluacion)`.
- Secuencia. `certificado_cert_id_certificado_seq → cert_id_certificado`.

### `colonia`

- Columnas. `colo_id_colonia integer ! = nextval('anfeca_modelo.colonia_colo_id_colonia_seq'::regclass)`, `colo_id_alcaldia integer !`, `colo_nombre character varying(150) !`, `colo_codigo_postal character varying(5) !`.
- Llaves. `fk_colonia_se_compon_alcaldia FOREIGN KEY (colo_id_alcaldia) REFERENCES anfeca_modelo.alcaldia_municipio(almu_id_alcaldia) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_colonia PRIMARY KEY (colo_id_colonia)`.
- Índices. `CREATE UNIQUE INDEX colonia_pk ON anfeca_modelo.colonia USING btree (colo_id_colonia)`, `CREATE UNIQUE INDEX pk_colonia ON anfeca_modelo.colonia USING btree (colo_id_colonia)`, `CREATE INDEX se_compone_fk ON anfeca_modelo.colonia USING btree (colo_id_alcaldia)`.
- Secuencia. `colonia_colo_id_colonia_seq → colo_id_colonia`.

### `comunicacion`

- Columnas. `comu_id_comunicacion integer ! = nextval('anfeca_modelo.comunicacion_comu_id_comunicacion_seq'::regclass)`, `comu_id_persona integer !`, `comu_id_tipo_comunicacion integer !`, `comu_valor character varying(150) !`, `comu_visible boolean`.
- Llaves. `fk_comunica_clasifica_tipo_com FOREIGN KEY (comu_id_tipo_comunicacion) REFERENCES anfeca_modelo.tipo_comunicacion(tico_id_tipo_comunicacion) ON UPDATE RESTRICT ON DELETE RESTRICT`, `fk_comunica_tiene_con_persona FOREIGN KEY (comu_id_persona) REFERENCES anfeca_modelo.persona(pers_id_persona) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_comunicacion PRIMARY KEY (comu_id_comunicacion, comu_id_persona, comu_id_tipo_comunicacion)`.
- Índices. `CREATE INDEX clasificado_por_fk ON anfeca_modelo.comunicacion USING btree (comu_id_tipo_comunicacion)`, `CREATE UNIQUE INDEX contacto_pk ON anfeca_modelo.comunicacion USING btree (comu_id_comunicacion, comu_id_persona, comu_id_tipo_comunicacion)`, `CREATE UNIQUE INDEX pk_comunicacion ON anfeca_modelo.comunicacion USING btree (comu_id_comunicacion, comu_id_persona, comu_id_tipo_comunicacion)`, `CREATE INDEX tiene_contacto_fk ON anfeca_modelo.comunicacion USING btree (comu_id_persona)`.
- Secuencia. `comunicacion_comu_id_comunicacion_seq → comu_id_comunicacion`.

### `convocatoria`

- Columnas. `conv_id_convocatoria integer ! = nextval('anfeca_modelo.convocatoria_conv_id_convocatoria_seq'::regclass)`, `conv_id_tipo_convocatoria integer !`, `conv_nombre character varying(150) !`, `conv_monto_recuperacion money !`, `conv_fecha_inicio_registro date !`, `conv_fecha_fin_registro date !`, `conv_fecha_resultado date !`, `conv_fecha_entrega_certificados date !`, `conv_puntuacion_max integer !`, `conv_puntuacion_min integer !`, `conv_lugar_entrega character varying(300)`, `conv_evento_entrega character varying(300)`.
- Llaves. `fk_convocat_relations_tipo_con FOREIGN KEY (conv_id_tipo_convocatoria) REFERENCES anfeca_modelo.tipo_convocatoria(tico_id_tipo_convocatoria) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_convocatoria PRIMARY KEY (conv_id_convocatoria)`.
- Índices. `CREATE UNIQUE INDEX convocatoria_pk ON anfeca_modelo.convocatoria USING btree (conv_id_convocatoria)`, `CREATE UNIQUE INDEX pk_convocatoria ON anfeca_modelo.convocatoria USING btree (conv_id_convocatoria)`, `CREATE INDEX relationship_31_fk ON anfeca_modelo.convocatoria USING btree (conv_id_tipo_convocatoria)`.
- Secuencia. `convocatoria_conv_id_convocatoria_seq → conv_id_convocatoria`.

### `dictamen`

- Columnas. `dict_id_dictamen integer ! = nextval('anfeca_modelo.dictamen_dict_id_dictamen_seq'::regclass)`, `dict_id_tipo_dictamen integer !`, `dict_id_evaluacion integer`, `dict_encabezado character varying(9) !`, `dict_folio_dictamen character varying(9) !`, `dict_fecha_dictamen_creacion date !`, `dict_hora_dictamen_creacion time without time zone !`, `dict_fecha_dictamen_ultima_impr date`.
- Llaves. `fk_dictamen_reference_evaluaci FOREIGN KEY (dict_id_evaluacion) REFERENCES anfeca_modelo.evaluacion(eval_id_evaluacion) ON UPDATE RESTRICT ON DELETE RESTRICT`, `fk_dictamen_reference_tipo_dic FOREIGN KEY (dict_id_tipo_dictamen) REFERENCES anfeca_modelo.tipo_dictamen(tidi_id_tipo_dictamen) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_dictamen PRIMARY KEY (dict_id_dictamen)`.
- Índices. `CREATE UNIQUE INDEX pk_dictamen ON anfeca_modelo.dictamen USING btree (dict_id_dictamen)`.
- Secuencia. `dictamen_dict_id_dictamen_seq → dict_id_dictamen`.

### `documento`

- Columnas. `docu_id_documento integer ! = nextval('anfeca_modelo.documento_docu_id_documento_seq'::regclass)`, `docu_descripcion character varying(150) !`, `docu_estatus character varying(3) !`.
- Llaves. `pk_documento PRIMARY KEY (docu_id_documento)`.
- Índices. `CREATE UNIQUE INDEX pk_documento ON anfeca_modelo.documento USING btree (docu_id_documento)`.
- Secuencia. `documento_docu_id_documento_seq → docu_id_documento`.

### `domicilio`

- Columnas. `domi_id_domicilio integer ! = nextval('anfeca_modelo.domicilio_domi_id_domicilio_seq'::regclass)`, `domi_id_persona integer !`, `domi_id_entidad_federativa integer !`, `domi_calle character varying(170) !`, `domi_numero_ext character varying(6) !`, `domi_numero_int character varying(4)`.
- Llaves. `fk_domicili_se_encuen_entidad_ FOREIGN KEY (domi_id_entidad_federativa) REFERENCES anfeca_modelo.entidad_federativa(enfe_id_entidad_federativa) ON UPDATE RESTRICT ON DELETE RESTRICT`, `fk_domicili_tiene_dom_persona FOREIGN KEY (domi_id_persona) REFERENCES anfeca_modelo.persona(pers_id_persona) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_domicilio PRIMARY KEY (domi_id_domicilio)`.
- Índices. `CREATE UNIQUE INDEX domicilio_pk ON anfeca_modelo.domicilio USING btree (domi_id_domicilio)`, `CREATE UNIQUE INDEX pk_domicilio ON anfeca_modelo.domicilio USING btree (domi_id_domicilio)`, `CREATE INDEX se_encuentra_fk ON anfeca_modelo.domicilio USING btree (domi_id_entidad_federativa)`, `CREATE INDEX tiene_dom_fk ON anfeca_modelo.domicilio USING btree (domi_id_persona)`.
- Secuencia. `domicilio_domi_id_domicilio_seq → domi_id_domicilio`.

### `entidad_federativa`

- Columnas. `enfe_id_entidad_federativa integer ! = nextval('anfeca_modelo.entidad_federativa_enfe_id_entidad_federativa_seq'::regclass)`, `enfe_nombre character varying(85) !`.
- Llaves. `pk_entidad_federativa PRIMARY KEY (enfe_id_entidad_federativa)`.
- Índices. `CREATE UNIQUE INDEX entidad_federativa_pk ON anfeca_modelo.entidad_federativa USING btree (enfe_id_entidad_federativa)`, `CREATE UNIQUE INDEX pk_entidad_federativa ON anfeca_modelo.entidad_federativa USING btree (enfe_id_entidad_federativa)`.
- Secuencia. `entidad_federativa_enfe_id_entidad_federativa_seq → enfe_id_entidad_federativa`.

### `estado_documento`

- Columnas. `esdo_id_estado_documento integer ! = nextval('anfeca_modelo.estado_documento_esdo_id_estado_documento_seq'::regclass)`, `esdo_estado_documento character varying(250) !`, `esdo_estado character varying(3) !`.
- Llaves. `pk_estado_documento PRIMARY KEY (esdo_id_estado_documento)`.
- Índices. `CREATE UNIQUE INDEX estado_documento_pk ON anfeca_modelo.estado_documento USING btree (esdo_id_estado_documento)`, `CREATE UNIQUE INDEX pk_estado_documento ON anfeca_modelo.estado_documento USING btree (esdo_id_estado_documento)`.
- Secuencia. `estado_documento_esdo_id_estado_documento_seq → esdo_id_estado_documento`.

### `estado_solicitud`

- Columnas. `esso_id__estado_solicitud integer ! = nextval('anfeca_modelo.estado_solicitud_esso_id__estado_solicitud_seq'::regclass)`, `esso_id_c_estado_solicitud integer`, `esso_id_solicitud integer !`, `esso_descripcion character varying(55) !`, `esso_siglas character varying(2) !`, `esso_fecha date !`, `esso_hora time without time zone !`, `esso_motivo_rechazo character varying(300)`.
- Llaves. `fk_estado_s_reference_solicitu FOREIGN KEY (esso_id_solicitud) REFERENCES anfeca_modelo.solicitud(soli_id_solicitud) ON UPDATE RESTRICT ON DELETE RESTRICT`, `fk_estado_s_relations_c_estado FOREIGN KEY (esso_id_c_estado_solicitud) REFERENCES anfeca_modelo.c_estado_solicitud(ceso_id_c_estado_solicitud) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_estado_solicitud PRIMARY KEY (esso_id__estado_solicitud)`.
- Índices. `CREATE UNIQUE INDEX estado_solicitud_pk ON anfeca_modelo.estado_solicitud USING btree (esso_id__estado_solicitud)`, `CREATE UNIQUE INDEX pk_estado_solicitud ON anfeca_modelo.estado_solicitud USING btree (esso_id__estado_solicitud)`, `CREATE INDEX relationship_42_fk ON anfeca_modelo.estado_solicitud USING btree (esso_id_c_estado_solicitud)`.
- Secuencia. `estado_solicitud_esso_id__estado_solicitud_seq → esso_id__estado_solicitud`.

### `estado_solicitud_documento`

- Columnas. `esdo_id_estado_solicitud_docume integer ! = nextval('anfeca_modelo.estado_solicitud_documento_esdo_id_estado_solicitud_docume_seq'::regclass)`, `esdo_id_documentacion integer !`, `esdo_id_estado_documento integer !`, `esdo_observacion character varying(300) !`, `esdo_fecha date !`, `esdo_hora time without time zone !`.
- Llaves. `fk_estado_s_relations_estado_d FOREIGN KEY (esdo_id_estado_documento) REFERENCES anfeca_modelo.estado_documento(esdo_id_estado_documento) ON UPDATE RESTRICT ON DELETE RESTRICT`, `fk_estado_s_relations_solicitu FOREIGN KEY (esdo_id_documentacion) REFERENCES anfeca_modelo.solicitud_documento(socu_id_documentacion) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_estado_solicitud_documento PRIMARY KEY (esdo_id_estado_solicitud_docume)`.
- Índices. `CREATE UNIQUE INDEX estado_solicitud_documento_pk ON anfeca_modelo.estado_solicitud_documento USING btree (esdo_id_estado_solicitud_docume)`, `CREATE UNIQUE INDEX pk_estado_solicitud_documento ON anfeca_modelo.estado_solicitud_documento USING btree (esdo_id_estado_solicitud_docume)`, `CREATE INDEX relationship_40_fk ON anfeca_modelo.estado_solicitud_documento USING btree (esdo_id_documentacion)`, `CREATE INDEX relationship_41_fk ON anfeca_modelo.estado_solicitud_documento USING btree (esdo_id_estado_documento)`.
- Secuencia. `estado_solicitud_documento_esdo_id_estado_solicitud_docume_seq → esdo_id_estado_solicitud_docume`.

### `estudio_profesional`

- Columnas. `espr_id_estudio_profesional integer ! = nextval('anfeca_modelo.estudio_profesional_espr_id_estudio_profesional_seq'::regclass)`, `espr_id_nivel_profesional integer !`, `espr_nombre character varying(150) !`, `espr_estado character varying(3) !`.
- Llaves. `fk_estudio__reference_nivel_pr FOREIGN KEY (espr_id_nivel_profesional) REFERENCES anfeca_modelo.nivel_profesional(nipr_id_nivel_profesional) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_estudio_profesional PRIMARY KEY (espr_id_estudio_profesional)`.
- Índices. `CREATE UNIQUE INDEX grado_academico_pk ON anfeca_modelo.estudio_profesional USING btree (espr_id_estudio_profesional)`, `CREATE UNIQUE INDEX pk_estudio_profesional ON anfeca_modelo.estudio_profesional USING btree (espr_id_estudio_profesional)`.
- Secuencia. `estudio_profesional_espr_id_estudio_profesional_seq → espr_id_estudio_profesional`.

### `evaluacion`

- Columnas. `eval_id_evaluacion integer ! = nextval('anfeca_modelo.evaluacion_eval_id_evaluacion_seq'::regclass)`, `eval_id_solicitud integer !`, `eval_puntuacion integer`, `eval_fecha_evaluacion date !`, `eval_hora_evaluacion time without time zone !`.
- Llaves. `fk_evaluaci_relations_solicitu FOREIGN KEY (eval_id_solicitud) REFERENCES anfeca_modelo.solicitud(soli_id_solicitud) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_evaluacion PRIMARY KEY (eval_id_evaluacion)`.
- Índices. `CREATE UNIQUE INDEX evaluacion_pk ON anfeca_modelo.evaluacion USING btree (eval_id_evaluacion)`, `CREATE UNIQUE INDEX pk_evaluacion ON anfeca_modelo.evaluacion USING btree (eval_id_evaluacion)`, `CREATE INDEX relationship_34_fk ON anfeca_modelo.evaluacion USING btree (eval_id_solicitud)`.
- Secuencia. `evaluacion_eval_id_evaluacion_seq → eval_id_evaluacion`.

### `historico_certificado`

- Columnas. `hice_id_historico_certificado integer ! = nextval('anfeca_modelo.historico_certificado_hice_id_historico_certificado_seq'::regclass)`, `hice_anio_certificacion character varying(4)`, `hice_numero_certificacion character varying(4)`, `hice_tipo character varying(15)`, `hice_folio_certificado character varying(7) !`, `hice_curp character varying(18)`, `hice_area_certificacion character varying(120)`.
- Llaves. `pk_historico_certificado PRIMARY KEY (hice_id_historico_certificado, hice_folio_certificado)`.
- Índices. `CREATE UNIQUE INDEX pk_historico_certificado ON anfeca_modelo.historico_certificado USING btree (hice_id_historico_certificado, hice_folio_certificado)`.
- Secuencia. `historico_certificado_hice_id_historico_certificado_seq → hice_id_historico_certificado`.

### `institucion`

- Columnas. `inst_id_institucion integer ! = nextval('anfeca_modelo.institucion_inst_id_institucion_seq'::regclass)`, `inst_numero_afiliacion character varying(7) !`, `inst_nombre character varying(300) !`, `inst_facultad character varying(500) !`, `inst_zona character varying(80) !`, `ins_coord_regional_cert_academi character varying(200)`.
- Llaves. `pk_institucion PRIMARY KEY (inst_id_institucion)`.
- Índices. `CREATE UNIQUE INDEX institucion_pk ON anfeca_modelo.institucion USING btree (inst_id_institucion)`, `CREATE UNIQUE INDEX pk_institucion ON anfeca_modelo.institucion USING btree (inst_id_institucion)`.
- Secuencia. `institucion_inst_id_institucion_seq → inst_id_institucion`.

### `nivel_profesional`

- Columnas. `nipr_id_nivel_profesional integer ! = nextval('anfeca_modelo.nivel_profesional_nipr_id_nivel_profesional_seq'::regclass)`, `nipr_nivel_profesional character varying(300) !`.
- Llaves. `pk_nivel_profesional PRIMARY KEY (nipr_id_nivel_profesional)`.
- Índices. `CREATE UNIQUE INDEX pk_nivel_profesional ON anfeca_modelo.nivel_profesional USING btree (nipr_id_nivel_profesional)`.
- Secuencia. `nivel_profesional_nipr_id_nivel_profesional_seq → nipr_id_nivel_profesional`.

### `pago`

- Columnas. `pago_id_pago integer ! = nextval('anfeca_modelo.pago_pago_id_pago_seq'::regclass)`, `pago_id_solicitud integer !`, `pago_url_comprobante character varying(255) !`.
- Llaves. `fk_pago_reference_solicitu FOREIGN KEY (pago_id_solicitud) REFERENCES anfeca_modelo.solicitud(soli_id_solicitud) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_pago PRIMARY KEY (pago_id_pago)`.
- Índices. `CREATE UNIQUE INDEX pago_pk ON anfeca_modelo.pago USING btree (pago_id_pago)`, `CREATE UNIQUE INDEX pk_pago ON anfeca_modelo.pago USING btree (pago_id_pago)`.
- Secuencia. `pago_pago_id_pago_seq → pago_id_pago`.

### `persona`

- Columnas. `pers_id_persona integer ! = nextval('anfeca_modelo.persona_pers_id_persona_seq'::regclass)`, `pers_id_institucion integer`, `pers_nombre character varying(150) !`, `pers_apellido_paterno character varying(60)`, `pers_apellido_materno character varying(60)`, `pers_curp character varying(18) !`, `pers_rfc character varying(13) !`, `pers_genero character varying(1)`, `pers_num_afiliacion_institucion character varying(10)`, `pers_activo boolean`.
- Llaves. `fk_persona_pertenece_instituc FOREIGN KEY (pers_id_institucion) REFERENCES anfeca_modelo.institucion(inst_id_institucion) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_persona PRIMARY KEY (pers_id_persona)`.
- Índices. `CREATE UNIQUE INDEX persona_pk ON anfeca_modelo.persona USING btree (pers_id_persona)`, `CREATE INDEX pertenece_a_fk ON anfeca_modelo.persona USING btree (pers_id_institucion)`, `CREATE UNIQUE INDEX pk_persona ON anfeca_modelo.persona USING btree (pers_id_persona)`.
- Secuencia. `persona_pers_id_persona_seq → pers_id_persona`.

### `persona_estudio_profesional`

- Columnas. `pees_id_persona_estudio_profesi integer ! = nextval('anfeca_modelo.persona_estudio_profesional_pees_id_persona_estudio_profesi_seq'::regclass)`, `pees_id_estuido_profesional integer !`, `pees_id_persona integer !`, `pees_url_titulo character varying(300)`, `pees_cedula character varying(20)`, `pees_url_cedula character varying(180)`.
- Llaves. `fk_persona__relations_estudio_ FOREIGN KEY (pees_id_estuido_profesional) REFERENCES anfeca_modelo.estudio_profesional(espr_id_estudio_profesional) ON UPDATE RESTRICT ON DELETE RESTRICT`, `fk_persona__relations_persona FOREIGN KEY (pees_id_persona) REFERENCES anfeca_modelo.persona(pers_id_persona) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_persona_estudio_profesional PRIMARY KEY (pees_id_persona_estudio_profesi, pees_id_estuido_profesional, pees_id_persona)`.
- Índices. `CREATE UNIQUE INDEX pk_persona_estudio_profesional ON anfeca_modelo.persona_estudio_profesional USING btree (pees_id_persona_estudio_profesi, pees_id_estuido_profesional, pees_id_persona)`, `CREATE INDEX relationship_27_fk ON anfeca_modelo.persona_estudio_profesional USING btree (pees_id_estuido_profesional)`, `CREATE UNIQUE INDEX relationship_27_pk ON anfeca_modelo.persona_estudio_profesional USING btree (pees_id_persona_estudio_profesi, pees_id_estuido_profesional, pees_id_persona)`, `CREATE INDEX relationship_28_fk ON anfeca_modelo.persona_estudio_profesional USING btree (pees_id_persona)`.
- Secuencia. `persona_estudio_profesional_pees_id_persona_estudio_profesi_seq → pees_id_persona_estudio_profesi`.

### `privilegio`

- Columnas. `priv_id_privilegio integer ! = nextval('anfeca_modelo.privilegio_priv_id_privilegio_seq'::regclass)`, `priv_nombre character varying(150) !`, `priv_descripcion character varying(150) !`.
- Llaves. `pk_privilegio PRIMARY KEY (priv_id_privilegio)`.
- Índices. `CREATE UNIQUE INDEX pk_privilegio ON anfeca_modelo.privilegio USING btree (priv_id_privilegio)`, `CREATE UNIQUE INDEX privilegio_pk ON anfeca_modelo.privilegio USING btree (priv_id_privilegio)`.
- Secuencia. `privilegio_priv_id_privilegio_seq → priv_id_privilegio`.

### `refrendo_certificado`

- Columnas. `rece_id_refrendo_certificacion integer ! = nextval('anfeca_modelo.refrendo_certificado_rece_id_refrendo_certificacion_seq'::regclass)`, `rece_id_evaluacion integer`, `rece_id_certificado integer !`, `rece_numero_refrendo integer !`, `rece_anio_refrendo integer !`, `rece_art14 boolean`, `rece_art13 boolean !`.
- Llaves. `fk_refrendo_relations_certific FOREIGN KEY (rece_id_certificado) REFERENCES anfeca_modelo.certificado(cert_id_certificado) ON UPDATE RESTRICT ON DELETE RESTRICT`, `fk_refrendo_relations_evaluaci FOREIGN KEY (rece_id_evaluacion) REFERENCES anfeca_modelo.evaluacion(eval_id_evaluacion) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_refrendo_certificado PRIMARY KEY (rece_id_refrendo_certificacion)`.
- Índices. `CREATE UNIQUE INDEX pk_refrendo_certificado ON anfeca_modelo.refrendo_certificado USING btree (rece_id_refrendo_certificacion)`, `CREATE UNIQUE INDEX refrendo_certificacion_pk ON anfeca_modelo.refrendo_certificado USING btree (rece_id_refrendo_certificacion)`, `CREATE INDEX relationship_37_fk ON anfeca_modelo.refrendo_certificado USING btree (rece_id_evaluacion)`, `CREATE INDEX relationship_38_fk ON anfeca_modelo.refrendo_certificado USING btree (rece_id_certificado)`.
- Secuencia. `refrendo_certificado_rece_id_refrendo_certificacion_seq → rece_id_refrendo_certificacion`.

### `rol`

- Columnas. `rol_id_rol integer ! = nextval('anfeca_modelo.rol_rol_id_rol_seq'::regclass)`, `rol_nombre character varying(150) !`.
- Llaves. `pk_rol PRIMARY KEY (rol_id_rol)`.
- Índices. `CREATE UNIQUE INDEX pk_rol ON anfeca_modelo.rol USING btree (rol_id_rol)`, `CREATE UNIQUE INDEX rol_pk ON anfeca_modelo.rol USING btree (rol_id_rol)`.
- Secuencia. `rol_rol_id_rol_seq → rol_id_rol`.

### `rol_privilegio`

- Columnas. `ropr_id_rol_privilegio integer ! = nextval('anfeca_modelo.rol_privilegio_ropr_id_rol_privilegio_seq'::regclass)`, `ropr_id_rol integer !`, `ropr_id_privilegio integer !`.
- Llaves. `fk_rol_priv_rol_privi_privileg FOREIGN KEY (ropr_id_privilegio) REFERENCES anfeca_modelo.privilegio(priv_id_privilegio) ON UPDATE RESTRICT ON DELETE RESTRICT`, `fk_rol_priv_rol_privi_rol FOREIGN KEY (ropr_id_rol) REFERENCES anfeca_modelo.rol(rol_id_rol) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_rol_privilegio PRIMARY KEY (ropr_id_rol_privilegio)`.
- Índices. `CREATE UNIQUE INDEX pk_rol_privilegio ON anfeca_modelo.rol_privilegio USING btree (ropr_id_rol_privilegio)`, `CREATE INDEX rol_privilegio2_fk ON anfeca_modelo.rol_privilegio USING btree (ropr_id_privilegio)`, `CREATE INDEX rol_privilegio_fk ON anfeca_modelo.rol_privilegio USING btree (ropr_id_rol)`, `CREATE UNIQUE INDEX rol_privilegio_pk ON anfeca_modelo.rol_privilegio USING btree (ropr_id_rol_privilegio)`.
- Secuencia. `rol_privilegio_ropr_id_rol_privilegio_seq → ropr_id_rol_privilegio`.

### `sede`

- Columnas. `sede_id_sede integer ! = nextval('anfeca_modelo.sede_sede_id_sede_seq'::regclass)`, `inst_id_institucion integer !`, `sede_descripcion character varying(150) !`.
- Llaves. `fk_sede_tiene_sed_instituc FOREIGN KEY (inst_id_institucion) REFERENCES anfeca_modelo.institucion(inst_id_institucion) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_sede PRIMARY KEY (sede_id_sede)`.
- Índices. `CREATE UNIQUE INDEX pk_sede ON anfeca_modelo.sede USING btree (sede_id_sede)`, `CREATE UNIQUE INDEX sede_pk ON anfeca_modelo.sede USING btree (sede_id_sede)`, `CREATE INDEX tiene_sede_fk ON anfeca_modelo.sede USING btree (inst_id_institucion)`.
- Secuencia. `sede_sede_id_sede_seq → sede_id_sede`.

### `solicitud`

- Columnas. `soli_id_solicitud integer ! = nextval('anfeca_modelo.solicitud_soli_id_solicitud_seq'::regclass)`, `soli_id_persona integer !`, `soli_id_area_certificacion integer !`, `soli_id_tipo_personal_academico integer !`, `soli_id_convocatoria integer`, `soli_id_articulo integer`, `soli_numero_certificado character varying(10)`, `soli_fecha_registro_solicitud date !`, `soli_folio_solicitud integer !`, `soli_art13 boolean !`, `soli_art14 boolean !`.
- Llaves. `fk_solicitu_esta_asoc_tipo_per FOREIGN KEY (soli_id_tipo_personal_academico) REFERENCES anfeca_modelo.tipo_personal_academico(tipa_id_tipo_personal_academico) ON UPDATE RESTRICT ON DELETE RESTRICT`, `fk_solicitu_pertenece_area_cer FOREIGN KEY (soli_id_area_certificacion) REFERENCES anfeca_modelo.area_certificacion(area_id_area_certificacion) ON UPDATE RESTRICT ON DELETE RESTRICT`, `fk_solicitu_puede_hac_persona FOREIGN KEY (soli_id_persona) REFERENCES anfeca_modelo.persona(pers_id_persona) ON UPDATE RESTRICT ON DELETE RESTRICT`, `fk_solicitu_relations_convocat FOREIGN KEY (soli_id_convocatoria) REFERENCES anfeca_modelo.convocatoria(conv_id_convocatoria) ON UPDATE RESTRICT ON DELETE RESTRICT`, `fk_solicitu_tiene_art_articulo FOREIGN KEY (soli_id_articulo) REFERENCES anfeca_modelo.articulo_subindice(arce_id_articulo) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_solicitud PRIMARY KEY (soli_id_solicitud)`.
- Índices. `CREATE INDEX esta_asociada_fk ON anfeca_modelo.solicitud USING btree (soli_id_tipo_personal_academico)`, `CREATE INDEX pertenece_a_area_fk ON anfeca_modelo.solicitud USING btree (soli_id_area_certificacion)`, `CREATE UNIQUE INDEX pk_solicitud ON anfeca_modelo.solicitud USING btree (soli_id_solicitud)`, `CREATE INDEX puede_hacer_fk ON anfeca_modelo.solicitud USING btree (soli_id_persona)`, `CREATE INDEX relationship_32_fk ON anfeca_modelo.solicitud USING btree (soli_id_convocatoria)`, `CREATE UNIQUE INDEX solicitud_pk ON anfeca_modelo.solicitud USING btree (soli_id_solicitud)`.
- Secuencia. `solicitud_soli_id_solicitud_seq → soli_id_solicitud`.

### `solicitud_documento`

- Columnas. `socu_id_documentacion integer ! = nextval('anfeca_modelo.solicitud_documento_socu_id_documentacion_seq'::regclass)`, `socu_id_solicitud integer !`, `socu_id_documento integer !`, `socu_fecha date !`, `socu_hora time without time zone !`.
- Llaves. `fk_solicitu_relations_document FOREIGN KEY (socu_id_documento) REFERENCES anfeca_modelo.documento(docu_id_documento) ON UPDATE RESTRICT ON DELETE RESTRICT`, `fk_solicitu_tiene_as_solicitu FOREIGN KEY (socu_id_solicitud) REFERENCES anfeca_modelo.solicitud(soli_id_solicitud) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_solicitud_documento PRIMARY KEY (socu_id_documentacion)`.
- Índices. `CREATE UNIQUE INDEX pk_solicitud_documento ON anfeca_modelo.solicitud_documento USING btree (socu_id_documentacion)`, `CREATE INDEX relationship_39_fk ON anfeca_modelo.solicitud_documento USING btree (socu_id_documento)`, `CREATE UNIQUE INDEX solicitud_documento_pk ON anfeca_modelo.solicitud_documento USING btree (socu_id_documentacion)`, `CREATE INDEX tiene_as_fk ON anfeca_modelo.solicitud_documento USING btree (socu_id_solicitud)`.
- Secuencia. `solicitud_documento_socu_id_documentacion_seq → socu_id_documentacion`.

### `tipo_comunicacion`

- Columnas. `tico_id_tipo_comunicacion integer ! = nextval('anfeca_modelo.tipo_comunicacion_tico_id_tipo_comunicacion_seq'::regclass)`, `tico_tipo_comunicacion character varying(50) !`, `tico_estado character varying(3) !`.
- Llaves. `pk_tipo_comunicacion PRIMARY KEY (tico_id_tipo_comunicacion)`.
- Índices. `CREATE UNIQUE INDEX pk_tipo_comunicacion ON anfeca_modelo.tipo_comunicacion USING btree (tico_id_tipo_comunicacion)`, `CREATE UNIQUE INDEX tipo_comunicacion_pk ON anfeca_modelo.tipo_comunicacion USING btree (tico_id_tipo_comunicacion)`.
- Secuencia. `tipo_comunicacion_tico_id_tipo_comunicacion_seq → tico_id_tipo_comunicacion`.

### `tipo_convocatoria`

- Columnas. `tico_id_tipo_convocatoria integer ! = nextval('anfeca_modelo.tipo_convocatoria_tico_id_tipo_convocatoria_seq'::regclass)`, `tico_tipo_convocatoria character varying(50) !`, `tico_estado character varying(3) !`, `tico_siglas character varying(65) !`.
- Llaves. `pk_tipo_convocatoria PRIMARY KEY (tico_id_tipo_convocatoria)`.
- Índices. `CREATE UNIQUE INDEX pk_tipo_convocatoria ON anfeca_modelo.tipo_convocatoria USING btree (tico_id_tipo_convocatoria)`, `CREATE UNIQUE INDEX tipo_convocatoria_pk ON anfeca_modelo.tipo_convocatoria USING btree (tico_id_tipo_convocatoria)`.
- Secuencia. `tipo_convocatoria_tico_id_tipo_convocatoria_seq → tico_id_tipo_convocatoria`.

### `tipo_dictamen`

- Columnas. `tidi_id_tipo_dictamen integer ! = nextval('anfeca_modelo.tipo_dictamen_tidi_id_tipo_dictamen_seq'::regclass)`, `tidi_descripcion character varying(120) !`, `tidi_tipo_dictamen_estado boolean !`.
- Llaves. `pk_tipo_dictamen PRIMARY KEY (tidi_id_tipo_dictamen)`.
- Índices. `CREATE UNIQUE INDEX pk_tipo_dictamen ON anfeca_modelo.tipo_dictamen USING btree (tidi_id_tipo_dictamen)`.
- Secuencia. `tipo_dictamen_tidi_id_tipo_dictamen_seq → tidi_id_tipo_dictamen`.

### `tipo_personal_academico`

- Columnas. `tipa_id_tipo_personal_academico integer ! = nextval('anfeca_modelo.tipo_personal_academico_tipa_id_tipo_personal_academico_seq'::regclass)`, `tipa_descripcion character varying(150) !`, `tipa_estado boolean !`.
- Llaves. `pk_tipo_personal_academico PRIMARY KEY (tipa_id_tipo_personal_academico)`.
- Índices. `CREATE UNIQUE INDEX pk_tipo_personal_academico ON anfeca_modelo.tipo_personal_academico USING btree (tipa_id_tipo_personal_academico)`, `CREATE UNIQUE INDEX tipo_personal_academico_pk ON anfeca_modelo.tipo_personal_academico USING btree (tipa_id_tipo_personal_academico)`.
- Secuencia. `tipo_personal_academico_tipa_id_tipo_personal_academico_seq → tipa_id_tipo_personal_academico`.

### `usuario`

- Columnas. `usua_id_usuario integer ! = nextval('anfeca_modelo.usuario_usua_id_usuario_seq'::regclass)`, `usua_id_persona integer !`, `usua_id_rol integer !`, `usua_nombre character varying(150) !`, `usua_contrasenia character varying(255) !`, `usua_estatus character varying(20) !`.
- Llaves. `fk_usuario_tiene_rol_rol FOREIGN KEY (usua_id_rol) REFERENCES anfeca_modelo.rol(rol_id_rol) ON UPDATE RESTRICT ON DELETE RESTRICT`, `fk_usuario_tiene_usu_persona FOREIGN KEY (usua_id_persona) REFERENCES anfeca_modelo.persona(pers_id_persona) ON UPDATE RESTRICT ON DELETE RESTRICT`, `pk_usuario PRIMARY KEY (usua_id_usuario)`.
- Índices. `CREATE UNIQUE INDEX pk_usuario ON anfeca_modelo.usuario USING btree (usua_id_usuario)`, `CREATE INDEX tiene_rol_fk ON anfeca_modelo.usuario USING btree (usua_id_rol)`, `CREATE INDEX tiene_usuario2_fk ON anfeca_modelo.usuario USING btree (usua_id_persona)`, `CREATE UNIQUE INDEX usuario_pk ON anfeca_modelo.usuario USING btree (usua_id_usuario)`.
- Secuencia. `usuario_usua_id_usuario_seq → usua_id_usuario`.


## Objetos incorporados por las extensiones operativas

La comparación se repitió antes y después de ejecutar `php artisan migrate --path=database/migrations_pdm --force --no-interaction`. La definición registrada de cada objeto preexistente se conservó. El esquema operativo tiene **44 tablas en `anfeca_modelo`**. Las novedades sobre tablas originales son aditivas y se detallan para que el revisor pueda decidir si admite esa capa operativa además de las 36 tablas de referencia.

| Inventario aparte de la base | Cantidad | Objetos |
| --- | ---: | --- |
| Tablas operativas y de control en `anfeca_modelo` | 8 | `coordinador_zona`, `documentos_solicitud`, `formacion_solicitud`, `historico_certificado_importacion`, `integracion_control`, `seleccion_certificacion`, `solicitud_refrendo`, `zona` |
| Secuencias de las tablas operativas en `anfeca_modelo` | 6 | `coordinador_zona_cozo_id_coordinador_zona_seq`, `documentos_solicitud_dosc_id_documentos_seq`, `formacion_solicitud_fosc_id_formacion_seq`, `seleccion_certificacion_secc_id_seleccion_seq`, `solicitud_refrendo_sore_id_solicitud_refrendo_seq`, `zona_zona_id_zona_seq` |
| Tablas auxiliares de compatibilidad en `anfeca_compat` | 5 | `alias_entidad`, `mapa_comunicacion`, `mapa_documento`, `mapa_estado_solicitud`, `parametro` |
| Vistas de compatibilidad en `anfeca_compat` | 9 | `certificados_historicos`, `datos_refrendo`, `datos_solicitantes`, `documentos_probatorios_solicitud`, `formaciones_academicas_solicitud`, `historial_documentos_academicos`, `instituciones_afiliadas`, `selecciones_certificacion`, `solicitudes_certificacion` |
| Funciones de compatibilidad en `anfeca_compat` | 35 | `a_local(p timestamp without time zone)`, `a_utc(p_fecha date, p_hora time without time zone)`, `ahora_app()`, `alinear_secuencia(p_tabla text, p_columna text, p_id bigint)`, `area_id(p text)`, `clave(p text)`, `comunicacion(p_persona integer, p_campo text)`, `comunicacion_set(p_persona integer, p_campo text, p_valor text)`, `documento_id(p_campo text)`, `documento_set(p_sol integer, p_campo text, p_url text, p_marca `, `documento_vigente(p_sol integer, p_campo text)`, `entidad_id(p text)`, `error(p_campo text, p_mensaje text)`, `estado_documento_id(p_nombre text)`, `estudio(p_sol integer, p_tipo text, p_dato text)`, `estudio_set(p_sol integer, p_persona integer, p_tipo text, p_ni`, `evento(p_sol integer, p_siglas text, p_marca timestamp without `, `evento_utc(p_sol integer, p_siglas text)`, `largo(p text, p_limite integer, p_campo text)`, `modo()`, `replicar_public()`, `rol_id(p_nombre text)`, `solicitud_existente(p_sol bigint)`, `tg_datos_refrendo()`, `tg_datos_solicitantes()`, `tg_documentos_probatorios_solicitud()`, `tg_formaciones_academicas_solicitud()`, `tg_guardia_public()`, `tg_historial_documentos_academicos()`, `tg_selecciones_certificacion()`, `tg_solicitudes_certificacion()`, `tg_solo_lectura()`, `tg_users_usuario()`, `tipo_personal_id(p text)`, `vacio(p text)` |
| Triggers de compatibilidad en `anfeca_compat` | 9 | `certificados_historicos.tg_solo_lectura`, `datos_refrendo.tg_datos_refrendo`, `datos_solicitantes.tg_datos_solicitantes`, `documentos_probatorios_solicitud.tg_documentos_probatorios_solicitud`, `formaciones_academicas_solicitud.tg_formaciones_academicas_solicitud`, `historial_documentos_academicos.tg_historial_documentos_academicos`, `instituciones_afiliadas.tg_solo_lectura`, `selecciones_certificacion.tg_selecciones_certificacion`, `solicitudes_certificacion.tg_solicitudes_certificacion` |
| Triggers añadidos sobre tablas Laravel en `public` | 10 | `certificados_historicos.anfeca_compat_guardia`, `datos_refrendo.anfeca_compat_guardia`, `datos_solicitantes.anfeca_compat_guardia`, `documentos_probatorios_solicitud.anfeca_compat_guardia`, `formaciones_academicas_solicitud.anfeca_compat_guardia`, `historial_documentos_academicos.anfeca_compat_guardia`, `instituciones_afiliadas.anfeca_compat_guardia`, `selecciones_certificacion.anfeca_compat_guardia`, `solicitudes_certificacion.anfeca_compat_guardia`, `users.anfeca_compat_usuario` |


Se agregan 20 columnas, 23 índices y 11 restricciones de catálogo sobre tablas originales. Las otras 73 columnas de extensión están en tablas adicionales. Las FK operativas suman 47 en `anfeca_modelo`; 35 proceden del original. La siguiente tabla solo incluye las 22 tablas originales que recibieron algún objeto adicional.

| Tabla original | Columnas añadidas | Índices añadidos | Restricciones añadidas |
| --- | --- | --- | --- |
| `area_certificacion` | — | `ext_area_certificacion_uk` | — |
| `articulo_subindice` | — | `ext_articulo_subindice_uk` | — |
| `c_estado_solicitud` | — | `ext_c_estado_solicitud_uk` | — |
| `comunicacion` | — | `ext_comunicacion_persona_tipo_uk` | — |
| `documento` | — | `ext_documento_uk` | — |
| `domicilio` | `domi_codigo_postal character varying(5)`, `domi_colonia character varying(150)`, `domi_municipio character varying(150)` | `ext_domicilio_persona_uk` | — |
| `entidad_federativa` | — | `ext_entidad_nombre_uk` | — |
| `estado_documento` | — | `ext_estado_documento_uk` | — |
| `estudio_profesional` | — | `ext_estudio_nivel_nombre_uk` | — |
| `historico_certificado` | `hice_certificacion character varying(20)`, `hice_id_importacion bigint` | `ext_hice_importacion_tipo_uk` | `fk_hice_ext_importacion FOREIGN KEY (hice_id_importacion) REFERENCES anfeca_modelo.historico_certificado_importacion(hcim_id_importacion) ON UPDATE RESTRICT ON DELETE RESTRICT` |
| `institucion` | `inst_campus character varying(200)`, `inst_id_zona integer` | `ext_institucion_afiliacion_uk` | `fk_instituc_ext_zona FOREIGN KEY (inst_id_zona) REFERENCES anfeca_modelo.zona(zona_id_zona) ON UPDATE RESTRICT ON DELETE RESTRICT` |
| `nivel_profesional` | — | `ext_nivel_profesional_uk` | — |
| `persona` | `pers_actualizado_en timestamp(0) without time zone`, `pers_cedula_profesional character varying(30)`, `pers_creado_en timestamp(0) without time zone`, `pers_datos_autorizados boolean`, `pers_fecha_autorizacion timestamp(0) without time zone` | `ext_persona_curp_uk` | `persona_pers_datos_autorizados_not_null NOT NULL pers_datos_autorizados` |
| `persona_estudio_profesional` | `pees_id_solicitud integer`, `pees_tipo character varying(10)` | `ext_pees_solicitud_tipo_uk` | `ck_pees_ext_tipo CHECK (((pees_tipo)::text = ANY ((ARRAY['ORIGEN'::character varying, 'ULTIMO'::character varying])::text[])))`, `fk_pees_ext_solicitud FOREIGN KEY (pees_id_solicitud) REFERENCES anfeca_modelo.solicitud(soli_id_solicitud) ON UPDATE RESTRICT ON DELETE RESTRICT` |
| `privilegio` | — | `ext_privilegio_nombre_uk` | — |
| `rol` | — | `ext_rol_nombre_uk` | — |
| `rol_privilegio` | — | `ext_rol_privilegio_uk` | — |
| `solicitud` | `soli_actualizado_en timestamp(0) without time zone`, `soli_certificacion character varying(20)`, `soli_creado_en timestamp(0) without time zone`, `soli_id_historico_importacion bigint`, `soli_tipo_tramite character varying(20)` | `ext_solicitud_folio_uk` | `ck_soli_ext_certificacion CHECK (((soli_certificacion)::text = ANY ((ARRAY['ANFECA'::character varying, 'CACSLA'::character varying, 'CACECA'::character varying])::text[])))`, `ck_soli_ext_tipo_tramite CHECK (((soli_tipo_tramite)::text = ANY ((ARRAY['inicial'::character varying, 'refrendo'::character varying])::text[])))`, `fk_soli_ext_historico FOREIGN KEY (soli_id_historico_importacion) REFERENCES anfeca_modelo.historico_certificado_importacion(hcim_id_importacion) ON UPDATE RESTRICT ON DELETE RESTRICT`, `solicitud_soli_certificacion_not_null NOT NULL soli_certificacion`, `solicitud_soli_tipo_tramite_not_null NOT NULL soli_tipo_tramite` |
| `solicitud_documento` | `socu_url_archivo character varying(500)` | `ext_socu_solicitud_documento_idx` | `solicitud_documento_socu_url_archivo_not_null NOT NULL socu_url_archivo` |
| `tipo_comunicacion` | — | `ext_tipo_comunicacion_uk` | — |
| `tipo_personal_academico` | — | `ext_tipo_personal_uk` | — |
| `usuario` | — | `ext_usuario_nombre_uk`, `ext_usuario_persona_uk` | — |


## Consultas empleadas

La comparación de inventario serializó un objeto por línea y ordenó por clase, esquema, tabla y nombre. Para repetir el inventario íntegro, ejecutar la siguiente consulta con `psql -XqAt -v ON_ERROR_STOP=1` en cada base aislada. Comparar ambos archivos con `cmp`.

<details>
<summary>Consulta de catálogo completa</summary>

```sql
set search_path = pg_catalog;
with relaciones as (
    select c.oid, n.nspname as esquema, c.relname as tabla, c.relkind, c.relpersistence, c.relrowsecurity, c.relforcerowsecurity
    from pg_class c join pg_namespace n on n.oid = c.relnamespace
    where n.nspname in ('anfeca_modelo', 'anfeca_compat', 'public') and c.relkind in ('r','v','S')
), objetos as (
    select 'tabla' as clase, esquema, tabla, tabla as nombre,
        jsonb_build_object('persistencia', relpersistence, 'rls', relrowsecurity, 'rls_forzada', relforcerowsecurity) as definicion
    from relaciones where relkind = 'r'
    union all
    select 'columna', r.esquema, r.tabla, a.attname,
        jsonb_build_object('posicion', a.attnum, 'tipo', format_type(a.atttypid, a.atttypmod), 'no_nulo', a.attnotnull,
            'defecto', pg_get_expr(d.adbin, d.adrelid), 'identidad', a.attidentity, 'generada', a.attgenerated,
            'colacion', co.collname, 'longitud', info.character_maximum_length, 'precision', info.numeric_precision,
            'escala', info.numeric_scale, 'precision_fecha', info.datetime_precision)
    from relaciones r join pg_attribute a on a.attrelid = r.oid
    left join pg_attrdef d on d.adrelid = a.attrelid and d.adnum = a.attnum
    left join pg_collation co on co.oid = a.attcollation
    left join information_schema.columns info on info.table_schema = r.esquema and info.table_name = r.tabla and info.column_name = a.attname
    where r.relkind in ('r','v') and a.attnum > 0 and not a.attisdropped
    union all
    select 'restriccion', r.esquema, r.tabla, k.conname,
        jsonb_build_object('tipo', k.contype, 'ddl', pg_get_constraintdef(k.oid),
            'validada', k.convalidated, 'diferible', k.condeferrable, 'diferida', k.condeferred)
    from relaciones r join pg_constraint k on k.conrelid = r.oid
    union all
    select 'indice', r.esquema, r.tabla, ix.relname,
        jsonb_build_object('ddl', pg_get_indexdef(i.indexrelid), 'valido', i.indisvalid, 'listo', i.indisready)
    from relaciones r join pg_index i on i.indrelid = r.oid join pg_class ix on ix.oid = i.indexrelid
    union all
    select 'secuencia', r.esquema, t.relname, r.tabla,
        jsonb_build_object('tipo', format_type(s.seqtypid,null), 'inicio', s.seqstart, 'incremento', s.seqincrement,
            'minimo', s.seqmin, 'maximo', s.seqmax, 'cache', s.seqcache, 'ciclo', s.seqcycle, 'columna', a.attname, 'dependencia', d.deptype)
    from relaciones r join pg_sequence s on s.seqrelid = r.oid
    left join pg_depend d on d.classid = 'pg_class'::regclass and d.objid = r.oid and d.refclassid = 'pg_class'::regclass and d.deptype in ('a','i')
    left join pg_class t on t.oid = d.refobjid
    left join pg_attribute a on a.attrelid = t.oid and a.attnum = d.refobjsubid
    union all
    select 'trigger', r.esquema, r.tabla, g.tgname,
        jsonb_build_object('ddl', pg_get_triggerdef(g.oid), 'habilitado', g.tgenabled)
    from relaciones r join pg_trigger g on g.tgrelid = r.oid where not g.tgisinternal
    union all
    select 'vista', r.esquema, r.tabla, r.tabla, to_jsonb(pg_get_viewdef(r.oid))
    from relaciones r where relkind='v'
    union all
    select 'funcion', n.nspname, '', p.proname || '(' || pg_get_function_identity_arguments(p.oid) || ')',
        to_jsonb(pg_get_functiondef(p.oid))
    from pg_proc p join pg_namespace n on n.oid = p.pronamespace
    where n.nspname in ('anfeca_modelo','anfeca_compat','public') and p.prokind in ('f','p')
)
select row_to_json(o) from objetos o order by clase,esquema,tabla,nombre;
```

</details>

## Revalidación con el nombre oficial

En esta fase se repitieron el cotejo SHA-256 de la fuente, la instalación mediante Laravel y el volcado sobre una base desechable llamada `certificacion_anfeca` en PostgreSQL 18.7. Las 36 filas de la matriz siguen aprobadas. El volcado normalizado y el inventario conservaron las huellas documentadas arriba. El directorio SQL de PowerDesigner quedó idéntico al del PR #12. Las guardas añadidas operan en Laravel y no cambian las definiciones de PostgreSQL.
