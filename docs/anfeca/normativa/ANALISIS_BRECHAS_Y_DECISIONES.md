# Análisis de brechas normativas y decisiones pendientes

**Corte:** 2026-11-25.

**Insumos:**
- `INVENTARIO_DOCUMENTAL.md`, documentos D01–D18;
- `MATRIZ_TRAZABILIDAD_REQUERIMIENTOS.md`;
- revisión del código de la rama `desarrollo/modulo-coordinacion`;
- revisión del PDM `anfeca_modelo`, en solo lectura.

**Regla de cierre:** un requerimiento solo se cierra con una **regla verificable** y **evidencia** de su cumplimiento (prueba automatizada o acta de aceptación). Que exista una pantalla relacionada no basta.

## 1. Clasificación de hallazgos

Responsables propuestos:
- **RP**: responsable del proyecto;
- **CNCA**: Coordinación Nacional de Certificación Académica;
- **DEV**: equipo de desarrollo;
- **DBA**: administración de PostgreSQL.

| # | Hallazgo | Clasificación | Responsable propuesto | Dependencia | Criterio de aceptación |
|---|---|---|---|---|---|
| H01 | No existe una convocatoria 2027 publicada (ni preliminar) | **Requiere aclaración oficial** | CNCA | — | Convocatoria aprobada por el CND y registrada en el inventario con su SHA-256 |
| H02 | Laravel no relaciona solicitud con convocatoria; cada trámite debe conservar las reglas de su ciclo | **Bloqueante para lanzamiento** | RP / DEV | H01 | Cada solicitud nueva queda ligada a una convocatoria versionada y no cambia retroactivamente (prueba) |
| H03 | Sin periodo de registro ni cierre: la convocatoria exige rechazar solicitudes fuera de periodo | **Bloqueante para lanzamiento** | RP / DEV | H02 | Envío bloqueado fuera de periodo, con zona horaria oficial; casos de borradores al cierre definidos |
| H04 | Catálogo `zonas` vacío, `zona_id` nulo y sin asignaciones de coordinadores: los coordinadores regionales no ven nada | **Bloqueante para lanzamiento** | RP / DBA | Autorización de la propuesta Z | Normalización aplicada con respaldo; cada coordinador regional ve exactamente su zona (prueba `Zon` sobre la base real) |
| H05 | Inciso «14» (eméritos y honoris causa) del formato oficial **no seleccionable** en la interfaz; tampoco existe en el PDM `articulo_subindice` | **Bloqueante para lanzamiento** | RP | Autorización de cambio en solicitantes | El solicitante puede elegir 14, 14.1, 14.2 o 14.3, y la prueba lo confirma |
| H06 | **13.1** se ofrece como refrendo; el reglamento lo define como **certificado de primera vez** | **Requiere aclaración oficial** | CNCA | — | Decisión documentada y regla ajustada |
| H07 | El acuse reconoce el inciso **14.4**, que no tiene respaldo en el reglamento ni en el formato | **Requiere aclaración oficial** | CNCA / RP | — | Eliminado o justificado con fuente |
| H08 | PAES (2026): solo primera vez y solo «Disciplinas vinculadas»; el sistema no lo restringe | **Necesario antes de fase 6.4** | CNCA / RP | H01, H02 | Reglas por convocatoria activadas solo con la convocatoria 2027 aprobada |
| H09 | Posgrado «en caso de poseerlo» (2026) frente a obligatorio en el sistema (decisión de la etapa 1) | **Requiere aclaración oficial** | RP / CNCA | — | Decisión registrada; **se conserva lo aprobado** mientras tanto |
| H10 | Constancia de antigüedad: 3 años, expedición ≤ 3 meses, categoría predominante, horas; sin verificación | **Necesario para registro administrativo** | CNCA / DEV | Guía §6 | Guía de revisión aprobada e incorporada al panel |
| H11 | Estados oficiales de recepción («improcedente», «rechazada») y **lista de cotejo** no publicada | **Necesario para registro administrativo** | CNCA | Obtener la lista de cotejo | Estados implementados según la lista oficial |
| H12 | Recepción física (comprobante original y 3 fotografías) sin registro | **Necesario para registro administrativo** | RP / DEV | Autorización de estructura (§9) | Registro por coordinador, con fecha, faltantes e historial |
| H13 | Pago: sin importe por convocatoria ni validación administrativa | **Necesario para registro administrativo** | RP / DEV | H02 | Estados de pago (§10) con historial |
| H14 | Comisiones de evaluación: ≥ 5 evaluadores certificados, **sin evaluadores de la misma institución**, aprobación de la CNCA | **Necesario para evaluación** | CNCA / DEV | Fase 6.4 | Asignación con bloqueo de conflicto de interés (prueba) |
| H15 | Instrumentos y periodos por modalidad (5000 máx., 2000 mín. y periodos de 5 o 3 años en 2026) | **Necesario para evaluación** | CNCA | H01 | Instrumento versionado por convocatoria; parámetros no codificados |
| H16 | Vigencia trienal sin fechas autoritativas (los históricos guardan años) | **Necesario antes de fase 6.4** | CNCA / RP | Datos históricos | Regla de vigencia confirmada y probada con casos límite |
| H17 | Documentación presuntamente apócrifa (art. 4.8): procedimiento con audiencia | **Necesario para evaluación** | CNCA | — | Flujo con notificación, argumentos y resolución |
| H18 | Reconocimientos (máximo por zona; Academia ANFECA) sin criterios de desempate | **Necesario para reportes** | CNCA | Reglamento de la Academia | Criterios oficiales documentados |
| H19 | Formatos de actas, dictámenes y certificados no localizados | **Necesario para actas y certificados** | CNCA | — | Formatos oficiales en el inventario |
| H20 | El sistema acepta JPG y PNG; el instructivo pide PDF ≤ 5 MB | **Requiere aclaración oficial** | RP | — | Decisión registrada |
| H21 | Rótulo «Certificación» (ANFECA/CACECA/CACSLA) frente a «Participación» del instructivo oficial | **Mejora posterior** | RP | — | Rótulos homologados sin cambiar datos |
| H22 | Documentos idénticos (misma SHA-256) en expedientes distintos de 8086 (p. ej., `5bdd00ec…` en los folios 3 y 5) | **Mejora posterior** (datos de prueba) | DEV | — | Indicador opcional de duplicidad en las guías de revisión (§7), sin decisión automática |
| H23 | Enlace de evidencias (Google Drive) obligatorio pero no validado ni revisado | **Necesario para evaluación** | CNCA / DEV | — | Procedimiento de recepción de evidencias aprobado; sin acceso automático a carpetas de terceros |
| H24 | Directorio de coordinaciones regionales de certificación (404) | **Requiere aclaración oficial** | CNCA | — | Directorio vigente para la entrega física |
| H25 | Migración ajena `escuela_facultad` pendiente en 8086 | **Necesario antes de fase 6.4** | RP | Autorización | Analizada y aplicada solo con `--path` y respaldo |

## 2. Discrepancias entre la normativa 2026 y el sistema

1. **13.1**: primera vez según el reglamento; refrendo en el sistema (H06).
2. **Inciso 14** (eméritos): oficial, pero no seleccionable (H05).
3. **14.4** en el acuse sin respaldo normativo (H07).
4. **Posgrado** opcional frente a obligatorio (H09, decisión de la etapa 1 vigente).
5. **PAES**: el sistema no restringe la modalidad ni el área (H08).
6. **Formatos de archivo**: PDF frente a PDF, JPG y PNG (H20).
7. **Reglamento, art. 4.2**: «asignatura de 20 horas» frente a «8 a 19 horas»; la redacción es ambigua (ELE-07).
8. **Zona**: el reglamento la define como la de la institución; el sistema solo guardaba el texto (`zona_anfeca`). La resolución ya está implementada; la normalización está pendiente (H04).

**Ninguna discrepancia modificó el comportamiento aprobado.** Se informan para que se decidan.

## 3. Significado funcional de los campos de clasificación

| Campo del sistema | Valores | Significado según la fuente oficial | Observación |
|---|---|---|---|
| `certificacion` | ANFECA, CACECA, CACSLA | **Participación** (organismo por el que participa), instructivo D09, sección 2 | No equivale a primera vez, refrendo ni PAES. Propuesta: rotularlo «Participación» |
| `area_certificacion` | 5 áreas | **Certificación académica a la que aspira**, según la licenciatura (D01 IV; D11) | Determina la denominación del certificado |
| `tipo_personal` | TC, MT, asignatura | Dedicación (glosario) | «Asignatura de Educación Superior» = **PAES** en 2026 (convocatoria propia) |
| `tipo_tramite` | inicial, refrendo | Primera vez o refrendo (D01) | 13.1 en discusión (H06) |

## 4. Administración de convocatorias (propuesta, sin implementar)

**Situación actual:**
- **Laravel:** no existe una entidad de convocatoria.
- **PDM:** `anfeca_modelo.convocatoria` tiene identificador, tipo, nombre, monto de recuperación, fechas de inicio y fin de registro, de resultados y de entrega, puntuaciones máxima y mínima, lugar y evento de entrega. `tipo_convocatoria` está vacío y `solicitud.soli_id_convocatoria` existe.

**Faltan en el PDM:**
- versión y estado (borrador, aprobada, publicada, cerrada);
- modalidad (PTC/MT, PAES, art. 14);
- periodos de evidencias;
- referencia al instrumento y al reglamento aplicables;
- cuota con moneda;
- zona horaria;
- calendario de revisión y evaluación;
- autoridad que aprueba y fecha de aprobación;
- huella del documento oficial.

**Propuesta para Laravel** (alineada con el PDM, para revisión):
- `convocatorias`: ciclo, año, nombre, versión, modalidad, estado y fechas.
- `convocatoria_parametros`: ponderaciones, mínimo y máximo, periodos, cuota, áreas y tipos de personal permitidos, requisitos documentales y su obligatoriedad.
- `convocatoria_documentos`: referencias a los documentos del inventario con su SHA-256.

**Reglas propuestas:**
- **Relación:** `solicitudes_certificacion.convocatoria_id`, fijado al crear el borrador; las reglas se leen siempre de esa convocatoria.
- **Inmutabilidad:** cuando una convocatoria se publica, sus parámetros no se editan; un cambio crea una nueva versión. Las solicitudes no cambian retroactivamente.
- **Fechas:** apertura y cierre en `America/Mexico_City`.
- **Borradores al cierre:** quedan en solo lectura para el solicitante. No hay envío por parte de la coordinación ni prórroga sin una regla aprobada.

**Pendiente:** autorización y convocatoria 2027.

## 5. Elegibilidad (análisis; sin reglas nuevas)

**Reglas 2026 identificadas:**
- institución afiliada;
- 3 años de antigüedad;
- constancia expedida en los últimos 3 meses;
- categoría predominante y horas;
- sin certificación vigente para primera vez;
- certificación previa para refrendo;
- PAES solo primera vez y una sola área;
- posgrado en área económico-administrativa, o dictamen de procedencia de la comisión.

**Lo que hoy implementa el sistema:**
- catálogos de institución, tipo de personal y área;
- validación de certificado histórico para refrendo;
- documentos obligatorios.

**Qué no se implementa sin la convocatoria 2027 aprobada:**
- combinaciones válidas de tipo de personal, área y modalidad;
- verificación de la vigencia del certificado;
- límites de horas.

Los certificados históricos múltiples se identifican **por certificado** (`certificado_historico_id`), no por usuario. Una solicitud anterior enviada no impide un nuevo refrendo; el seguimiento lo distingue (caso 12).

## 6. Constancia de antigüedad: guía de revisión propuesta

Se distinguen cuatro niveles:
1. **Presencia del archivo:** automático; hoy es el estado de carga.
2. **Revisión formal del documento:** humana; es el resultado de la revisión documental.
3. **Cumplimiento de requisitos:** humano, con lista de verificación.
4. **Elegibilidad del solicitante:** decisión de la comisión o de la CNCA.

| Aspecto | Verificable automáticamente | Requiere criterio humano |
|---|---|---|
| Archivo existe, se abre y pesa ≤ 5 MB | Sí | — |
| Corresponde al solicitante (nombre o CURP) | No (sin OCR autorizado) | Sí |
| Institución emisora afiliada | Parcial (institución declarada) | Sí |
| Antigüedad ≥ 3 años | No | Sí |
| Expedida hace ≤ 3 meses | Solo si se captura la fecha de expedición | Sí |
| Categoría predominante y horas | No | Sí |
| Firma, sello y folio (instrumento, consideración 1) | No | Sí |

**Propuesta de incorporación:**
- una lista de verificación por documento en el panel de revisión, como casillas informativas guardadas junto con la revisión;
- sin decisiones automáticas de autenticidad;
- la existencia de un PDF **no** demuestra su autenticidad ni su cumplimiento.

## 7. Guías de revisión por documento (propuesta)

**Aspectos comunes:**
- correspondencia con el solicitante;
- institución emisora;
- tipo de documento;
- integridad y legibilidad;
- fecha de expedición y vigencia;
- coincidencia con los datos declarados;
- requisitos específicos de la convocatoria.

**Aspectos específicos por documento:**
- **Título y cédula:** área conforme a la certificación; cédula de la DGP.
- **Grado y cédula:** área económico-administrativa o dictamen de procedencia de la comisión.
- **Comprobante de pago:** importe de la convocatoria, referencia y fecha. El original físico se registra aparte (§9).
- **Artículo 14:** formato oficial firmado e inciso marcado; documento probatorio vigente (PRODEP, SNII), con aval de la Secretaría Académica para 14.1.

**Señales sin decisión automática:**
- archivo idéntico (misma SHA-256) en otro expediente (H22);
- PDF dañado;
- archivo no localizado.

## 8. Documentación básica, evidencias, instrumento, entregables y pago

| Categoría | Qué es | Dónde está hoy | Quién decide |
|---|---|---|---|
| A. Documentación básica del expediente | Título, cédulas, constancia, comprobante y documentos art. 14 | Archivos en el sistema y revisión documental | Coordinación (revisión formal) |
| B. Evidencias académicas de evaluación | Constancias por factor y subfactor en carpeta digital con relación detallada | **Enlace de Google Drive** (`link`; PDM: documento 9 «Enlace de evidencias») | Comisión de evaluación |
| C. Instrumento de evaluación | Factores, puntajes y ponderaciones; software Excel | No implementado (fase 6.4) | Comisión y CNCA |
| D. Entregables físicos | Comprobante original y 3 fotografías | Solo el compromiso aceptado | Coordinación regional (recepción) |
| E. Comprobante y validación administrativa del pago | Archivo digital y validación | Solo el archivo | Coordinación (validación administrativa) |

**Una revisión documental satisfactoria no es una calificación aprobatoria.**

No se implementan integraciones con carpetas de terceros, accesos automatizados ni cargas masivas.

## 9. Recepción física: propuesta de estados y reglas (sin tablas)

- **Elementos:** comprobante de pago original y fotografías (cantidad esperada, 3 en 2026; especificaciones: ovaladas, color, 6 × 9 cm, fondo blanco y nombre al reverso).
- **Estados por elemento:** Pendiente de recepción → Recibido conforme · Recibido con observaciones · Faltante · No aplica (justificado).
- **Datos a registrar:** cantidad recibida, cumplimiento de especificaciones, fecha de recepción, coordinador receptor (de la sesión), observaciones e historial de solo inserción, como la revisión documental.

**Reglas:**
- Aceptar el compromiso **no** marca nada como recibido.
- El archivo digital cargado ≠ documento revisado ≠ entregable físico recibido ≠ pago validado.
- Solo registran recepción la coordinación de la zona de la institución y la nacional.

## 10. Validación administrativa de pagos: propuesta

| Concepto | Detalle |
|---|---|
| Datos | Importe esperado (de la convocatoria), importe declarado o observado, fecha de pago, referencia, recepción del original (§9), validación, discrepancias e historial |
| Estados | Pendiente · Validado · Con discrepancia · No válido |
| Reglas | Sin integración bancaria ni facturación (la factura es un proceso externo por correo); sin datos fiscales; sin modificar datos financieros del solicitante |

## 11. Preparación de la fase 6.4 (análisis; sin implementación)

- **Instrumentos:** uno por convocatoria y modalidad, con factores, subfactores, indicadores, valores, máximos por subfactor y ponderaciones; mínimo y máximo; reglas de redondeo y criterios excluyentes. **No** se codifican los porcentajes de 2026.
  - El software Excel (D14–D16) es la referencia del cálculo. Sus fórmulas deben validarse contra los instrumentos antes de programarlas.
- **Periodos:** configurables por convocatoria (en 2026, 5 años para primera vez y 3 para refrendo). Falta definir si se trata de años naturales o de fechas, y cómo tratar evidencias fuera de periodo, duplicadas o aplicables a varios rubros.
- **Responsabilidades:**
  - la **comisión regional** evalúa (≥ 5 evaluadores certificados, sin evaluadores de la institución del aspirante);
  - la **CNCA** aprueba y emite los certificados;
  - el **CND** resuelve los casos no previstos;
  - el **secretario técnico regional** levanta las actas.
- **Roles hoy:** Laravel solo tiene `coordinador_nacional`, `coordinador_zona` y `solicitante`. En el PDM, `rol` y `privilegio` existen pero están vacíos.
  - Se requieren perfiles de evaluador y de secretario técnico, asignación por comisión, separación de funciones y auditoría.
  - **No se asume que coordinador = evaluador = autoridad de aprobación.**
- **Dictámenes:** los estados definitivos deben derivarse de formatos oficiales que aún no se tienen. En el PDM, `dictamen` y `tipo_dictamen` existen y `tipo_dictamen` está vacío. No se inventan estados ni se emiten dictámenes automáticos.
- **Resultados y reconocimientos:** publicación aprobada, listados por zona y protección de datos. Faltan los criterios de desempate (H18).
- **Actas y certificados:**
  - PDM: `acta` (coordinador, secretario, sede); `certificado` (folio, año, estado, ligado a la evaluación); `refrendo_certificado` (número y año de refrendo, art. 13 y 14).
  - Faltan vigencia, área certificada, firmas, entrega y acuse de recepción.
- **Calendario:** se vincula con el seguimiento (borradores al cierre). El coordinador **no** envía en nombre del solicitante.
- **Acuses y comunicación:**
  - El acuse actual distingue recepción de aprobación. Falta revisar su texto y el inciso 14.4.
  - No hay correos automáticos en esta intervención.

## 12. Comparación con PowerDesigner (solo lectura)

| Entidad PDM | Cobertura | Faltante o relación insuficiente | Laravel |
|---|---|---|---|
| CONVOCATORIA | Parcial (fechas, monto, puntajes) | Versión, estado, modalidad, periodos, instrumento, reglamento, zona horaria | No existe |
| SOLICITUD | Buena (convocatoria, artículo, tipo de trámite, certificación) | — | `solicitudes_certificacion`, sin `convocatoria_id` |
| DOCUMENTO | 9 documentos, incluido «Enlace de evidencias» | Obligatoriedad por convocatoria | Columnas por documento |
| SOLICITUD_DOCUMENTO / ESTADO_SOLICITUD_DOCUMENTO | Versión por archivo y bitácora de estados | Evaluador, huella, tipo de evento (propuestos en 6.3.0) | `revisiones_documentales` |
| EVALUACION | Solo puntuación total y fecha | Detalle por factor y subfactor, evaluador, comisión, versión del instrumento | No existe |
| DICTAMEN / TIPO_DICTAMEN | Estructura sin catálogo | Estados oficiales, autoridad, justificación | No existe |
| ACTA | Coordinador, secretario, sede | Zona, fecha, comisión, integrantes, solicitudes incluidas | No existe |
| CERTIFICADO / REFRENDO_CERTIFICADO | Folio, año, estado; número de refrendo y art. 13/14 | Vigencia, área, firmas, entrega | `certificados_historicos` (importación) |
| ARTICULO_SUBINDICE | 13.1, 14.1, 14.2, 14.3 | **Falta «14»** (eméritos); 13.1 en discusión | Lista en código |
| ZONA / COORDINADOR_ZONA | Estructura | Catálogo vacío en el ensayo | `zonas` vacía; resolución por `zona_anfeca` |
| PAGO | URL del comprobante | Importe, validación, recepción física | Archivo en documentos probatorios |
| ROL / PRIVILEGIO | Estructura vacía | Perfiles de evaluación | `users.rol` |

**Todas estas son propuestas para revisión.** No se activó ni se modificó el PDM.

## 13. Plan de fases actualizado

| Fase | Contenido | Estado |
|---|---|---|
| 6.1–6.2.4 | Acceso, bandeja, expediente, homologación y compactación | Hecho |
| 6.3.0–6.3.1 | Revisión documental (migración aplicada en 8086, **bandera apagada**) | Hecho; **activación pendiente** |
| **6.3.2** (esta intervención) | Zonas resueltas, visor PDF, revisión 70/30, seguimiento de registros, indicadores e inventario normativo | Hecho; aceptación pendiente |
| 6.3.3 | Normalización de zonas y asignación de coordinadores; inciso 14; textos del acuse | Pendiente de autorización |
| 6.3.4 | Administración de convocatorias (estructura y relación con solicitudes) | Pendiente de convocatoria 2027 y autorización |
| 6.3.5 | Recepción física y validación administrativa de pagos | Pendiente de autorización |
| 6.4 | Evaluación (instrumentos versionados, comisiones, evaluadores) | **No iniciar** hasta tener los instrumentos 2027 |
| 6.5–6.8 | Nuevo registro, reportes, actas y certificados, historial | Pendiente de formatos oficiales |

## 14. Decisiones que requieren autorización

1. Activar la revisión documental en 8086 (procedimiento en `PERSISTENCIA_REVISION_DOCUMENTAL_FASE_6_3_1.md` §13).
2. Normalizar zonas y asignar coordinadores (`ZONAS_DIAGNOSTICO_Y_NORMALIZACION.md`).
3. Habilitar el inciso «14» (eméritos) en el módulo de solicitantes.
4. Tratamiento de 13.1 y 14.4 (aclaración de la CNCA).
5. Posgrado obligatorio u opcional (se conserva la decisión de la etapa 1).
6. Formatos admitidos: solo PDF o también imágenes.
7. Diseño del módulo de convocatorias y la relación con solicitudes.
8. Estructuras de recepción física y de validación de pagos.
9. Migración ajena `escuela_facultad`.
10. Sustituir el paso de pruebas dentro del sitio en `desplegar_f45.sh` por el árbol aislado.
11. Rótulo «Participación» para ANFECA, CACECA y CACSLA.
