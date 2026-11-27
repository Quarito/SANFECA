# Laravel sobre el modelo aprobado de 36 tablas · matriz de incompatibilidades y avance · 2026-11-27

**Decisión vigente:** preservar el modelo aprobado y mantener la funcionalidad, sin modificar todavía 8086. No se eliminan campos ni reglas de los formularios y no se agregan columnas sin una decisión expresa. **8086 activo sin cambios.**

## 1. Modelo usado (no modificado)

`App\Services\Pdm36\EsquemaPdm36` instala el **PDM literal** (`anfeca_certifica_1.original.sql`):
- sin los DROP iniciales;
- sin el índice inválido `TIENE_ARTICULO_FK`;
- con **solo** las 2 correcciones autorizadas (`comu_id_persona`, `socu_id_documento`).

Los 3 renombres pendientes **no** se aplican. Resultado verificado: 36 tablas, 173 columnas, 36 PK, 35 FK, 96 índices, 36 secuencias y 0 tablas fuera del modelo.

El archivo original tiene **codificación mixta**: encabezado UTF-8 y cuerpo Windows-1252, con «definirá» en el comentario de PERSONA. Se decodifica línea por línea, sin pérdida; el comentario queda íntegro en la base.

## 2. Representación válida dentro del modelo (sin truncar, concatenar ni perder información)

| Dato actual | Tabla y atributo | Fundamento |
|---|---|---|
| Acceso (correo y contraseña) | `usuario.usua_nombre` (correo), `usuario.usua_contrasenia` (bcrypt, 255) | **Implementado** (`UsuarioPdm36`, `ProveedorUsuarioPdm36`) |
| Rol (solicitante, coordinación nacional, coordinación de zona n) | `usuario.usua_id_rol` → `rol.rol_nombre` (`coordinador_zona_1…7`) | **Implementado** |
| Datos autorizados (sí/no) | `usuario.usua_estatus` (`REGISTRADO` / `DATOS_AUTORIZADOS`) | **Implementado** |
| Borrador o enviada, compromiso, formación autorizada (con fecha y hora) | `estado_solicitud` (BO, FA, CA, EN; `esso_fecha`, `esso_hora`) | **Implementado** para la navegación y el acceso |
| Nombre, CURP, RFC, género (Femenino/Masculino) | `persona` (`pers_nombre` 150, `pers_curp` 18, `pers_rfc` 13, `pers_genero` F/M) | Correspondencia biunívoca |
| Correo, teléfonos particular y celular | `comunicacion` + `tipo_comunicacion` | 150 caracteres para los teléfonos (10 dígitos) |
| Entidad federativa | `domicilio.domi_id_entidad_federativa` → `entidad_federativa` | Catálogo INEGI |
| Certificación ANFECA / CACSLA / CACECA | `solicitud.soli_id_convocatoria` → `convocatoria` → `tipo_convocatoria.tico_siglas` | Requiere registros reales de convocatoria (datos institucionales) |
| Tipo de personal y área de certificación | `solicitud.soli_id_tipo_personal_academico`, `soli_id_area_certificacion` | FK a catálogos |
| Trámite de primera vez o refrendo | Derivado de `solicitud.soli_numero_certificado` (nulo o con valor) | — |
| Artículo 13/14 e inciso | `solicitud.solo_art13`, `soli_art14`, `soli_id_articulo` → `articulo_subindice` | — |
| Certificado histórico (número, CURP, año, área) | `historico_certificado` (`hice_folio_certificado` 7, `hice_curp`, `hice_anio_certificacion`, `hice_area_certificacion`) | La regla de 3 años usa el año |
| Institución afiliada, facultad, zona y campus | `persona.pers_id_institucion` → `institucion` (`inst_facultad`, `inst_zona`) y `sede.sede_descripcion` | Requiere el padrón oficial (D-11) |
| Títulos y cédulas (archivos y número de cédula) | `persona_estudio_profesional` (`pees_url_titulo` 300, `pees_url_cedula` 180, `pees_cedula` 20) → `estudio_profesional` → `nivel_profesional` | Licenciatura y posgrado se distinguen por el nivel. `cuenta_con_posgrado` se deriva de que exista un estudio de posgrado |
| Documentos probatorios y sus versiones | `solicitud_documento` (uno por versión) + `documento` + `estado_solicitud_documento` (Cargado/Sustituido); comprobante en `pago.pago_url_comprobante` | La ruta del archivo se deriva de `socu_id_documentacion` |
| Sesiones, caché, cola y recuperación de contraseña | Archivo / caché (sin tablas) | Fuera de la base |

## 3. Incompatibilidades reales (sin representación válida)

| # | Campo del formulario | Tabla y atributo de destino | Límite actual | Regla vigente | Cambio mínimo (PENDIENTE DE AUTORIZACIÓN) | Impacto si no se resuelve |
|---|---|---|---|---|---|---|
| 1 | Código postal, colonia, municipio (obligatorios) | `domicilio`: no hay atributos ni FK a `colonia`/`alcaldia_municipio` | — | Obligatorios | **1 FK** `domicilio.domi_id_colonia → colonia` (CP y municipio se obtienen de `colonia` → `alcaldia_municipio`) más el catálogo SEPOMEX; o 3 columnas de texto | No se puede guardar el registro de datos personales: **bloquea todo el flujo** |
| 2 | Enlace de evidencias (URL obligatoria) | Sin atributo (`pago_url_comprobante` es el comprobante de pago) | — | Obligatorio sin artículo 14 | 1 columna URL (p. ej., en `solicitud`) | No se pueden guardar los documentos probatorios |
| 3 | Número exterior | `domicilio.domi_numero_ext` | 6 | Hasta 20 | Ampliar a 20, o ajustar la regla (cambio funcional) | Truncamiento de valores de 7 a 20 caracteres |
| 4 | Número interior | `domicilio.domi_numero_int` | 4 | Hasta 20 | Ampliar a 20 | Ídem |
| 5 | Calle | `domicilio.domi_calle` | 170 | Hasta 200 | Ampliar a 200 | Ídem |
| 6 | Apellidos paterno y materno | `persona.pers_apellido_*` | 60 | Hasta 100 | Ampliar a 100 | Ídem |
| 7 | Correo (acceso y contacto) | `usuario.usua_nombre`, `comunicacion.comu_valor` | 150 | Hasta 255 | Ampliar a 255 | Ídem |
| 8 | Cédula profesional | `persona_estudio_profesional.pees_cedula` | 20 | Hasta 30 | Ampliar a 30 | Ídem |
| 9 | Título de licenciatura o posgrado | `estudio_profesional.espr_nombre` | 150 | Catálogo hasta 233 (1 de 9 386); captura manual hasta 200 | Ampliar a 300 | Truncamiento de 1 título del catálogo y de capturas manuales de 151 a 200 |
| 10 | Clave y versión del catálogo; origen del título (catálogo/manual) | Sin atributos | — | Se registran hoy | 2–3 columnas; o aceptar derivarlas del nombre del título (pierde la trazabilidad de la versión) | Pérdida de trazabilidad |
| 11 | Fecha de autorización de los datos personales | `usuario.usua_estatus` solo guarda el estado | — | Se registra la fecha | 1 columna de fecha; o aceptar su pérdida (no se muestra en pantalla) | Pérdida del dato de auditoría |
| 12 | Usuarios de Coordinación | `usuario.usua_id_persona` es NOT NULL → `persona` exige CURP y RFC | — | Hoy no se piden | Hacer `usua_id_persona` opcional, o registrar el CURP y RFC reales de cada coordinador (dato institucional) | Sin CURP/RFC reales no se pueden dar de alta coordinadores |

**Datos institucionales indispensables** (no son cambios de modelo):
- padrón oficial de instituciones (`institucion`: número de afiliación ≤7, facultad, zona; D-11);
- convocatorias vigentes por certificación (`convocatoria` / `tipo_convocatoria`);
- catálogo SEPOMEX, si se elige la FK de la fila 1.

## 4. Avance real de la adaptación (`ANFECA_PDM36`; desactivado por omisión)

| Módulo | Estado | Prueba |
|---|---|---|
| Esquema de 36 tablas e instalación verificada | **Hecho** | `AutenticacionPdm36Test`: inventario exacto, correcciones autorizadas sí, renombres pendientes no |
| Catálogos y roles del modelo | **Hecho** (`CatalogosPdm36Seeder`) | Ídem |
| Autenticación, roles, zona del coordinador, cierre de sesión y cuenta de baja | **Hecho** | 5/5 pruebas, 54 aserciones; **todo el SQL registrado queda dentro del modelo** (ninguna consulta a public, anfeca_compat ni extensiones) |
| Navegación tras el acceso, ruta `/` y autorización de datos (middleware) | **Hecho** (servicio `EstadoSolicitante` con implementación vigente y de 36 tablas) | Ídem; con la bandera apagada, suites vigentes 205/79/22 sin cambios |
| Datos personales (alta, edición, autorización) | **Bloqueado** por las filas 1, 3–7 y 11 | — |
| Certificación y refrendo | Representable (requiere convocatorias) | Pendiente de implementar |
| Formación académica | Representable salvo las filas 8–10 | Pendiente |
| Documentos probatorios, compromiso, envío y acuse | Bloqueado por la fila 2; el resto es representable | Pendiente |
| Coordinación (bandeja, expedientes, seguimiento, indicadores) | Representable (zona por `institucion.inst_zona` y rol) | Pendiente |

Dependencias restantes (`scripts/anfeca/pdm36/inventario_dependencias.sh`, al cierre de este avance):

| Tipo de referencia | Referencias | Archivos |
|---|---:|---:|
| Tablas operativas de public | 70 | 25 |
| Modelos Eloquent de public | 63 | 26 |
| `anfeca_compat` | 29 | 11 |
| Extensiones del PDM | 95 | 21 |

Incluyen la capa de migración y espejo del PDM, que con la base de 36 tablas deja de ser necesaria. Se seguirá midiendo con el mismo script.

## 5. Qué impide terminar 8086 y avanzar a 8008

1. Las filas **1 y 2** (domicilio CP/colonia/municipio y enlace de evidencias) impiden guardar el registro y los documentos sin cambiar el modelo o las reglas. Son **indispensables**.
2. Las filas **3–9** solo afectan valores largos. Sin una decisión, la aplicación tendría que rechazarlos (cambio de regla) o truncarlos (pérdida de datos), y ninguna de las dos está autorizada.
3. Faltan los datos institucionales del §3 (padrón, convocatorias y, si se elige la FK, SEPOMEX).
4. 8008 depende de que 8086 quede aprobado con el modelo y de la decisión de alcance ya registrada en `DESPLIEGUE_8008_DESDE_8086_VALIDADO.md`.
