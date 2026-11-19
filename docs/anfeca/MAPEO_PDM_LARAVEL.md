# Mapeo PDM (PowerDesigner) ↔ Laravel — ANFECA

> Rama `ANFECA_BD_final` · 20/11/2026 · Fuente PDM: `database/sql/anfeca_modelo/anfeca_certifica_1.original.sql`
> Inventario completo de columnas y FK del PDM: `database/sql/anfeca_modelo/ANFECA_PDM_CATALOGO_TABLAS.json`.
> El mapeo ejecutable está en `config/anfeca_modelo.php` y se contrasta contra la BD con `php artisan anfeca:modelo-verificar`.

## Principios

1. **Laravel sigue siendo la fuente de verdad** de la aplicación (`public`). El PDM vive en el esquema aislado `anfeca_modelo` solo para consulta y comparación.
2. **No se renombran** tablas ni columnas Laravel para parecerse al PDM. Tampoco se corrigen las erratas del PDM: se documentan.
3. Cualquier convergencia futura se hace con **migraciones Laravel aditivas y reversibles**, una por iteración, con `migrate --pretend` y respaldo previo.
4. La identidad se mantiene en `user_id` y el trámite en `solicitud_id`. La autenticación sigue en `users.password`, con hash.

## Tabla a tabla

Orígenes:
- **repo**: existe en las migraciones del repositorio.
- **servidor**: documentada en `/var/www/ANFECA_PRUEBAS`, aún no subida al repo.
- **futuro**: módulo administrativo de etapa posterior.

| PDM (`anfeca_modelo`) | Laravel (`public`) | Origen | Estrategia |
|---|---|---|---|
| `PERSONA` | `datos_solicitantes` | repo | Campos 1:1 (nombre, apellidos, CURP, RFC, género). `PERS_ID_INSTITUCION` y `PERS_ACTIVO` sin equivalente. |
| `USUARIO` | `users` | repo | Mantener la autenticación de Laravel. `USUA_CONTRASENIA` **nunca** en texto plano; `USUA_ID_ROL` ≈ `users.rol` + `roles`. |
| `ROL`, `PRIVILEGIO`, `ROL_PRIVILEGIO` | `roles`, `privilegios`, `rol_privilegio` | servidor | Mapear IDs; preferir las tablas Laravel activas. |
| `DOMICILIO`, `COLONIA`, `ALCALDIA_MUNICIPIO`, `ENTIDAD_FEDERATIVA` | columnas de domicilio en `datos_solicitantes` | repo | Laravel guarda **texto libre**; el PDM usa catálogos normalizados. Normalizar sería la fase 3, sin borrar columnas. |
| `COMUNICACION`, `TIPO_COMUNICACION` | `telefono_particular`, `telefono_celular`, `correo_electronico` | repo | El PDM usa filas tipo-valor; Laravel usa columnas fijas. Adaptador futuro. |
| `INSTITUCION`, `SEDE` | `instituciones_afiliadas` (+ `zonas`, campus en servidor) | repo / servidor | `INST_FACULTAD` sin equivalente. `SEDE` ≈ `sede`/campus. |
| `SOLICITUD` | `solicitudes_certificacion` | servidor | Respetar `user_id`, `tipo_tramite`, `estado`, `fecha_envio` y `certificado_historico_id`. |
| `ESTADO_SOLICITUD`, `C_ESTADO_SOLICITUD` | `estado` (texto: borrador/enviado) | servidor | El PDM guarda **historial** de estados con fecha y hora; Laravel solo el estado actual. Tabla de historial aditiva futura. |
| `AREA_CERTIFICACION`, `TIPO_PERSONAL_ACADEMICO`, `ARTICULO_SUBINDICE` | texto en `selecciones_certificacion` y en la solicitud | repo | Catálogos antes que FK restrictivas; revisar `config/anfeca_equivalencias.php` del servidor. |
| `HISTORICO_CERTIFICADO` | `certificados_historicos` | repo | El PDM tiene PK compuesta (id, folio). Laravel usa `id`. `HICE_FOLIO_CERTIFICADO` y `HICE_AREA_CERTIFICACION` sin equivalente directo. |
| `CERTIFICADO`, `REFRENDO_CERTIFICADO` | `datos_refrendo` y la solicitud | servidor | Separar el antecedente histórico del nuevo certificado o refrendo. |
| `NIVEL_PROFESIONAL`, `ESTUDIO_PROFESIONAL`, `PERSONA_ESTUDIO_PROFESIONAL` | `formaciones_academicas`, `formaciones_academicas_solicitud` | repo / servidor | Preservar los 4 archivos académicos, los campos «Otro» y el historial. El PDM solo tiene `PEES_URL_TITULO` y `PEES_URL_CEDULA`. |
| `DOCUMENTO`, `SOLICITUD_DOCUMENTO`, `ESTADO_DOCUMENTO`, `ESTADO_SOLICITUD_DOCUMENTO` | `documentos_probatorios`, `documentos_probatorios_solicitud` | repo / servidor | El PDM modela catálogo + revisión por documento. Laravel modela columnas por archivo. |
| `PAGO` | `comprobante_pago_archivo` | repo | El comprobante **digital** no es el comprobante **original** de entrega física. |
| `CONVOCATORIA`, `TIPO_CONVOCATORIA` | — | futuro | Sin contraparte. Útil para fechas y montos. |
| `EVALUACION`, `DICTAMEN`, `TIPO_DICTAMEN`, `ACTA` | — | futuro | Módulos administrativos de la etapa posterior. |

## Campos Laravel que el PDM **no** tiene (no perder en ninguna convergencia)

- `datos_solicitantes`:
  - `datos_autorizados` y `fecha_autorizacion`: autorización de datos.
  - `cedula_profesional`.
  - `correo_electronico`, que en el PDM vive en `COMUNICACION`.
- Solicitud, según el servidor:
  - `compromiso_aceptado` y su fecha (aceptación del 19/nov).
  - `tipo_tramite` (inicial o refrendo).
  - `fecha_envio`.
  - Documentos del artículo 14.
- Formación, según el servidor:
  - `titulo_origen_otro` y `titulo_ultimo_grado_otro`.
  - `formacion_completada_at`.
  - Tabla `historial_documentos_academicos`.
- Documentos: `link`, y las rutas de Storage de cada archivo. El PDM no modela hash, tamaño, versión ni autorización de descarga.
- Trazabilidad de la importación histórica: `lote_importacion`, `fila_origen` y `estado_vinculacion`.

## Correcciones aplicadas al SQL del PDM (solo en `anfeca_modelo.sql`)

| Problema en la fuente | Tratamiento |
|---|---|
| 97 `DROP INDEX`/`DROP TABLE` al inicio | Eliminados |
| Objetos sin esquema, que caían en `public` | Todos calificados con `anfeca_modelo.`; no depende de `search_path` |
| Índice `TIENE_ARTICULO_FK` sobre `EVALUACION.SOLI_ID_ARTICULO`, columna inexistente | Omitido |
| `RELATIONSHIP_27_FK` UNIQUE: impedía que dos personas tuvieran el mismo estudio | Índice no único |
| `CREATE SCHEMA IF NOT EXISTS` | `CREATE SCHEMA` sin `IF NOT EXISTS`: si el esquema existe, falla sin tocar nada |
| `BEGIN/COMMIT` dentro del script | Quitados: la transacción la abre el comando o `psql -1` |

## Observaciones del PDM que conviene llevar a PowerDesigner (no aplicadas)

- **Erratas en nombres**:
  - `CUMU_ID_PERSONA`
  - `PEES_ID_ESTUIDO_PROFESIONAL`
  - `ESSO_ID__ESTADO_SOLICITUD`
  - `SOLO_ART13`
  - `INS_COORD_REGIONAL_CERT_ACADEMI`
  - `CON_LUGAR_ENTREGA` / `CON_EVENTO_ENTREGA`
  - `TICO_` usado como prefijo en dos tablas distintas.
- **Índices `*_PK` duplicados** de las PK: el script final tiene 96 índices, de los cuales 31 son redundantes. No causan error, pero ocupan espacio y hacen más lentas las escrituras.
- **Tipo de `CONV_MONTO_RECUPERACION`**: es `MONEY`, que depende del locale. Se recomienda `NUMERIC(12,2)`.
- **Fechas y horas**: `DATE` y `TIME` van separados. Se recomienda `TIMESTAMP WITH TIME ZONE`.
- **PK compuestas** con `SERIAL` (`COMUNICACION`, `PERSONA_ESTUDIO_PROFESIONAL`, `HISTORICO_CERTIFICADO`): no encajan con Eloquent; basta la PK simple.
- **Unicidad faltante**: no hay restricción única en CURP, RFC, `USUA_NOMBRE` ni folios.
- **Tablas sin FK** hacia el resto: `DOCUMENTO`, `NIVEL_PROFESIONAL`, `TIPO_DICTAMEN` e `HISTORICO_CERTIFICADO` no tienen FK hacia `PERSONA`.
- **Índice faltante en `PAGO`**: no hay índice sobre `PAGO_ID_SOLICITUD`.
- **Codificación**: el archivo original viene en Latin-1 con CRLF y tiene caracteres anómalos en un comentario. Conviene exportar en UTF-8.
