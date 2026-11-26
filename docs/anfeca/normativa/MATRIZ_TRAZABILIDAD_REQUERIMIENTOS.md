# Matriz de trazabilidad de requerimientos normativos

**Fuentes:** inventario `INVENTARIO_DOCUMENTAL.md` (D01–D18). **Corte:** 2026-11-25. **Sistema:** rama `desarrollo/modulo-coordinacion`.

**Estados:** Cumple · Cumple parcialmente · No implementado · Requiere aclaración · No aplica · Pendiente de fuente oficial.

Las cifras 2026 (fechas, cuota, ponderaciones, puntajes y periodos) se registran como **referencia histórica**. Ningún parámetro 2026 se activa automáticamente para 2027: hacerlo requiere la convocatoria oficial correspondiente.

**Abreviaturas:**
- **Mód.**: SOL (solicitantes), COO (coordinación), REV (revisión documental), SEG (seguimiento), EVA (evaluación, fase 6.4), DIC (dictamen y actas), CER (certificados), CNV (administración de convocatorias), ADM (recepción física y pagos), REP (reportes), PDM (modelo PowerDesigner).
- **Pruebas:** `Coord` = CoordinacionTest, `Rev` = RevisionDocumentalTest, `Esq` = RevisionesDocumentalesEsquemaTest, `Conc` = RevisionDocumentalConcurrenciaPgTest, `Zon` = ZonasAnfecaTest, `Seg` = SeguimientoRegistrosTest, `Reg` = suites de solicitantes (Flujo, Regresión, Seguridad).

## 1. Participantes y elegibilidad

| ID | Fuente | Año | Apartado | Requerimiento | Modalidad | Mód. | Regla de negocio | Estado actual | Brecha | Fase | Prueba | Decisión pendiente |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| ELE-01 | D01 / D07 | 2026 | Base I.1; Reg. art. 14 | Solo puede participar quien no tenga certificación vigente; el refrendo exige certificación previa | PTC/MT | SOL/CNV | Primera vez: sin certificado vigente; refrendo: con certificado previo identificado | Cumple parcialmente: el refrendo exige validar un certificado histórico; no se verifica la vigencia | No hay cálculo de vigencia ni regla contra participar con un certificado vigente | CNV / antes de 6.4 | `Reg` (validación de certificado) | Regla de vigencia 2027 y datos históricos autoritativos |
| ELE-02 | D02 | 2026 | Base I.1 | PAES: solo primera vez | PAES | SOL/CNV | Personal de asignatura (PAES) sin refrendo | **No implementado**: «Asignatura de Educación Superior» permite refrendo y cualquier área | El sistema no distingue la convocatoria PAES | CNV | — | ¿2027 mantiene PAES solo primera vez? |
| ELE-03 | D02 | 2026 | Encabezado | PAES: solo «Disciplinas vinculadas al ámbito económico-administrativo» | PAES | SOL/CNV | Área única para PAES | No implementado | Combinación tipo de personal / área no restringida | CNV | — | Confirmar para 2027 |
| ELE-04 | D01, D07 | 2026 | Base I.2; art. 4.2 | Pertenecer a una institución afiliada y acreditarlo con documento oficial | Todas | SOL | Institución del catálogo de afiliadas | Cumple: la institución se elige del catálogo; la constancia es obligatoria | La afiliación vigente no se valida contra un padrón oficial | Mejora | `Reg` | Fuente oficial del padrón de afiliadas |
| ELE-05 | D01, D07, D06 | 2026 | Base I.2; art. 4.2; glosario | Antigüedad mínima de 3 años como personal académico | Todas | REV | Verificación documental de la constancia | Cumple parcialmente: la constancia es obligatoria; su contenido no se verifica | Falta la guía de revisión (`ANALISIS_BRECHAS` §6) | REV | — | Validación humana (no automática) |
| ELE-06 | D01 | 2026 | Base I.2 | Constancia expedida con antigüedad no mayor a 3 meses al presentarla | Todas | REV | Fecha de expedición ≤ 3 meses antes de la presentación | No implementado (sin fecha de expedición capturada) | Requiere captura o criterio del revisor | REV | — | ¿Capturar la fecha de expedición? |
| ELE-07 | D01, D07 | 2026 | Base I.2; art. 4.2 | Categoría predominante si hubo varias en el periodo; asignatura con 8 a 19 h/semana-mes; «asignatura de 20 horas» | Todas | REV/EVA | Categoría de mayor tiempo en el periodo | No implementado | Redacción ambigua en el reglamento (20 h frente a 8–19 h) | Aclaración | — | **Requiere aclaración oficial** |
| ELE-08 | D06 | 2026 | Requisitos de procedencia | Licenciatura o posgrado de los aceptados; dedicación; antigüedad; institución afiliada | Todas | ADM/REV | Lista de cotejo de procedencia | No implementado | La lista de cotejo no está publicada | ADM | — | Obtener la lista de cotejo |
| ELE-09 | D09 | 2026 | Sección 2 | Seleccionar la certificación (por licenciatura) y la **participación por ANFECA, CACECA o CACSLA** | Todas | SOL | `certificacion` = organismo (participación); `area_certificacion` = licenciatura certificada | Cumple: campos `certificacion` (ANFECA/CACSLA/CACECA) y `area_certificacion` (5 áreas) | En la interfaz «Certificación» rotula el organismo; el instructivo lo llama «participación» | Mejora | `Reg` | Homologar rótulos («Participación») sin cambiar datos |
| ELE-10 | D01 | 2026 | Encabezado y Base IV | 5 áreas de certificación | Todas | SOL | Catálogo de 5 áreas | Cumple | — | — | `Reg` | — |
| ELE-11 | D01, D06 | 2026 | Encabezado; glosario | Tipos de personal: TC (≥ 40 h), MT (≥ 20 h), asignatura | Todas | SOL | Catálogo de 3 tipos | Cumple (catálogo); sin verificación de horas | Verificación documental | REV | — | — |

## 2. Documentación básica, evidencias y recepción

| ID | Fuente | Año | Apartado | Requerimiento | Modalidad | Mód. | Regla de negocio | Estado actual | Brecha | Fase | Prueba | Decisión pendiente |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| DOC-01 | D01, D07 | 2026 | Base I.3; art. 4.1 | Título y cédula de licenciatura | Todas | SOL/REV | Obligatorios | Cumple (obligatorios y revisables) | — | — | `Reg`, `Rev` | — |
| DOC-02 | D01 | 2026 | Base I.4 | Grado de maestría, especialidad o doctorado y cédula, **«en caso de poseerlo»** | Todas | SOL | Opcional según la convocatoria | **Discrepancia**: el sistema exige título y cédula del último grado para todos (decisión aprobada de la etapa 1) | Posgrado opcional en 2026 frente a obligatorio en el sistema | Aclaración | `Reg` | **Decisión del responsable** (se conserva lo aprobado) |
| DOC-03 | D01, D03 | 2026 | Base I.4 *; instrumento 1.1 | Posgrado en área económico-administrativa; si es distinto, la Comisión Regional dictamina su procedencia | Todas | REV/EVA | Decisión de la comisión regional | No implementado (sin registro de esa decisión) | Falta campo o flujo de dictamen de procedencia | 6.4 | — | Diseño con la comisión |
| DOC-04 | D01, D07 | 2026 | Base I.2; art. 4.2 | Documento que acredite la adscripción y la antigüedad | Todas | SOL/REV | Obligatorio | Cumple (constancia de antigüedad obligatoria y revisable) | Contenido no verificado (ELE-05/06/07) | REV | `Rev` | — |
| DOC-05 | D01 | 2026 | Base I.6; V.3.d | Comprobante de depósito o transferencia | Todas | SOL/ADM | Obligatorio | Cumple (archivo obligatorio) | Sin validación administrativa del pago (PAG-01) | ADM | `Rev` | — |
| DOC-06 | D01, D09, D10 | 2026 | V «Documentación comprobatoria»; instructivos | Evidencias de evaluación en carpeta digital (Google Drive) con **relación detallada** como primer documento | PTC/MT/PAES | SOL/EVA | Enlace obligatorio, salvo art. 14 | Cumple parcialmente: el enlace es obligatorio (no para art. 14) en `documentos_probatorios_solicitud.link`; PDM: documento 9 «Enlace de evidencias» | El enlace no se valida ni se revisa; sin acceso del sistema a carpetas de terceros (correcto) | EVA | `Reg` | Procedimiento de recepción de evidencias |
| DOC-07 | D17 | 2026 | Instructivo art. 14 | El art. 14 no envía documentos probatorios de evaluación | Refrendo art. 14 | SOL | Sin enlace de evidencias | Cumple (enlace excluido para art. 14) | — | — | `Reg` | — |
| DOC-08 | D09, D17 | 2026 | Documentos básicos | PDF de hasta 5 MB por documento | Todas | SOL | `max:5120`; PDF | Cumple parcialmente: el sistema acepta PDF, **JPG y PNG** | Formatos adicionales frente al instructivo | Aclaración | `Reg` | ¿Restringir a PDF? |
| DOC-09 | D01 | 2026 | V.3.b | Comprobante original y 3 fotografías ovaladas de 6 × 9 cm, enviados a la coordinación regional | Todas | ADM | Recepción física registrada por la coordinación regional | No implementado (solo el compromiso aceptado) | Falta el registro de recepción física (`ANALISIS_BRECHAS` §9) | ADM | — | Autorizar la estructura |
| DOC-10 | D01 | 2026 | V.3.c y final | La solicitud no se acepta si falta algún dato o documento, o si está fuera de periodo | Todas | SOL/CNV | Completitud y periodo | Cumple parcialmente: la completitud se exige para el envío; **sin periodo de registro** | Falta calendario de convocatoria (CNV-03) | CNV | `Seg` (listo para envío) | — |
| DOC-11 | D06, D07 | 2026 | Glosario; art. 4.7 | La revisión documental **no** equivale a autenticidad: la CNCA se reserva la verificación | Todas | REV | Sin decisiones automáticas de autenticidad | Cumple (resultados humanos; sin autenticidad automática) | Guías de revisión | REV | `Rev` | — |
| DOC-12 | D07 | 2026 | Art. 4.8 | Documentación presuntamente apócrifa: informar, recibir argumentos, informar a la institución, cancelar o anular | Todas | REV/DIC | Procedimiento con audiencia | No implementado | Flujo de presunción y audiencia | Posterior | — | Diseño con la CNCA |
| DOC-13 | D06 | 2026 | Glosario | Estados de recepción: **solicitud improcedente** y **solicitud rechazada** (coordinador regional, lista de cotejo) | Todas | ADM | Estados normativos de recepción | No implementado | Faltan los estados y la lista de cotejo | ADM | — | Obtener la lista de cotejo |

## 3. Artículos 13 y 14

| ID | Fuente | Año | Apartado | Requerimiento | Modalidad | Mód. | Regla de negocio | Estado actual | Brecha | Fase | Prueba | Decisión pendiente |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| ART-01 | D07 | 2026 | Art. 13.1 | **Certificado de primera vez** para integrantes del CND, titulares de SEP o de IES, con ≥ 5 años en el cargo | Primera vez | SOL | 13.1 es primera vez, no refrendo | **Discrepancia**: el sistema ofrece 13.1 como inciso del refrendo (DatosRefrendo.vue, requisitos(), PDM `articulo_subindice`) | Modalidad distinta | Aclaración | — | **Requiere aclaración oficial** |
| ART-02 | D07, D18 | 2026 | Art. 14, párr. 2; formato | Maestros eméritos y doctores honoris causa: certificación permanente (inciso «14» del formato) | Refrendo | SOL | Inciso «14» | **Cumple parcialmente**: el backend acepta «14», pero **la interfaz no lo ofrece** (DatosRefrendo.vue: 13.1, 14.1, 14.2, 14.3) y el PDM no lo tiene en `articulo_subindice` | Inciso oficial no seleccionable | Antes de lanzamiento | — | Autorizar el cambio en el módulo de solicitantes |
| ART-03 | D07, D18 | 2026 | Art. 14.1 | Titulares de SEP o IES conservan la certificación en funciones y hasta 2 años después; constancias con aval de la Secretaría Académica | Refrendo | SOL/REV | Constancia avalada | Cumple parcialmente: el inciso existe; el aval no se verifica | Guía de revisión | REV | — | — |
| ART-04 | D07, D18 | 2026 | Art. 14.2 | Perfil deseable PRODEP **vigente** | Refrendo | REV | Vigencia de la evidencia | Cumple parcialmente (sin verificación de vigencia) | Guía de revisión | REV | — | — |
| ART-05 | D07, D18 | 2026 | Art. 14.3 | Pertenencia vigente al SNII | Refrendo | REV | Vigencia | Cumple parcialmente | Guía de revisión | REV | — | — |
| ART-06 | Código | — | Acuse `acuse-recepcion.blade.php:349` | El acuse reconoce el inciso **14.4** | — | SOL | — | **Sin respaldo normativo**: el reglamento y el formato no tienen 14.4 | Valor no normativo en el acuse | Aclaración | — | No se elimina ni se valida sin decisión |
| ART-07 | D07 | 2026 | Art. 14 (cierre) | En todos los supuestos se presenta la solicitud y se cumple el procedimiento administrativo | Refrendo art. 14 | SOL | Formato de solicitud + documento probatorio | Cumple (formato Word descargable y 2 documentos art. 14 obligatorios) | — | — | `Reg` | — |
| ART-08 | Decisión etapa 1 | — | — | Todos los trámites integran formación académica, incluidos los art. 13/14 | Todas | SOL | Formación obligatoria | Cumple (decisión aprobada) | La reutilización de documentos históricos queda para la etapa 2 | Etapa 2 | `Reg` | — |

## 4. Instrumento, evaluación y resultados (preparación de la fase 6.4)

| ID | Fuente | Año | Apartado | Requerimiento | Modalidad | Mód. | Regla de negocio | Estado actual | Brecha | Fase | Prueba | Decisión pendiente |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| EVA-01 | D01–D05 | 2026 | Base III | 6 factores con ponderación por modalidad (PTC primera vez 10/50/15/5/15/5; refrendo 10/40/30/10/5/5; PAES 17/50/8/4/15/6) | Por modalidad | EVA/CNV | Instrumento versionado por convocatoria | No implementado (correcto: fase 6.4) | Sin instrumento parametrizable | 6.4 | — | Instrumentos 2027 |
| EVA-02 | D01, D03 | 2026 | Condición A | Máximo 5000 puntos | Todas | EVA/CNV | Parámetro del ciclo | No implementado. El PDM tiene `convocatoria.conv_puntuacion_max` | — | 6.4 | — | Confirmar 2027 |
| EVA-03 | D01, D07 | 2026 | Condición B; art. 12 | Mínimo 2000 puntos; si no se alcanza, puede participar el siguiente ciclo | Todas | EVA/DIC | Umbral del ciclo | No implementado. PDM: `conv_puntuacion_min` | — | 6.4 | — | Confirmar 2027 |
| EVA-04 | D01, D07 | 2026 | C–D; arts. 4.5–4.6 | Periodos: primera vez, últimos 5 años (2021–2025); refrendo, últimos 3 (2023–2025) | Por modalidad | EVA/CNV | Periodo configurable por convocatoria | No implementado | Definir años naturales frente a fechas | 6.4 | — | Unidad del periodo |
| EVA-05 | D03 | 2026 | Consideraciones 1–5 | Constancias firmadas, foliadas y selladas; copias escaneadas; pertinencia a juicio del comité; publicaciones con portada e índice; constancias con antigüedad máxima de 5 años | Todas | EVA | Criterios de evidencia | No implementado | Guías de evaluación | 6.4 | — | — |
| EVA-06 | D14–D16 | 2026 | Software | Hoja Excel de cálculo con celdas del evaluador | Todas | EVA | Referencia del cálculo oficial | No implementado (el sistema no evalúa) | Contrastar fórmulas antes de programar | 6.4 | — | Validar el software como fuente |
| EVA-07 | D07 | 2026 | Art. 7 | Comisiones regionales de **≥ 5 evaluadores certificados**; **sin evaluadores de la misma institución del aspirante** | Todas | EVA | Asignación con conflicto de interés | No implementado (sin perfil de evaluador) | Roles y asignación | 6.4 | — | Autorizar perfiles |
| EVA-08 | D07 | 2026 | Art. 9 | Evaluador con experiencia, certificación vigente y requisitos de la CNCA | Todas | EVA | Elegibilidad del evaluador | No implementado | — | 6.4 | — | — |
| EVA-09 | D07 | 2026 | Arts. 6.f y 7 | Las comisiones presentan resultados; la CNCA **aprueba** y emite el certificado | Todas | DIC | Separación de funciones evaluación / aprobación | No implementado | — | 6.4–6.7 | — | — |
| EVA-10 | D01 | 2026 | Condición E | Reconocimiento a la puntuación más alta de cada una de las 7 zonas | Todas | REP | Máximo por zona | No implementado | Criterio de desempate no publicado | 6.6 | — | Criterio de desempate |
| EVA-11 | D01 | 2026 | Condición F | Primera vez aprobada: reconocimiento como integrante de la Academia ANFECA | Primera vez | REP/CER | — | No implementado | Reglamento de la Academia no localizado | 6.7 | — | Obtener el reglamento |
| EVA-12 | D07 | 2026 | Art. 10 | Las comisiones se reúnen al menos una vez al año; el secretario técnico regional levanta actas | Todas | DIC | Actas regionales | No implementado | Formatos de acta no localizados | 6.7 | — | Obtener formatos |
| EVA-13 | D01, D07 | 2026 | VIII; Reg. VII | Situaciones no previstas: resueltas por la CNCA con aprobación del CND | Todas | DIC | Casos especiales con justificación y autoridad | No implementado | — | 6.4 | — | — |

## 5. Certificados, vigencia y refrendos

| ID | Fuente | Año | Apartado | Requerimiento | Modalidad | Mód. | Regla de negocio | Estado actual | Brecha | Fase | Prueba | Decisión pendiente |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| CER-01 | D01, D07 | 2026 | Base IV; art. 14 | Vigencia de 3 años; después debe refrendarse | Todas | CER/SOL | Vigencia por certificado | No implementado (sin fechas de emisión ni vigencia autoritativas) | `certificados_historicos` guarda años (primera_vez, refrendo_1…7), no fechas | CER | — | Regla 2027 y datos autoritativos |
| CER-02 | D01 | 2026 | Base IV | Denominación del certificado por área y género gramatical | Todas | CER | Texto oficial | No implementado | Formato oficial no localizado | 6.7 | — | Obtener el formato |
| CER-03 | D01 | 2026 | Base VII | Entrega en la Asamblea Nacional o posterior con la coordinación regional | Todas | CER/ADM | Registro de entrega y acuse | No implementado | — | 6.7 | — | — |
| CER-04 | D07 | 2026 | Art. 6.e | Registro actualizado de certificados, consultable previa solicitud escrita | Todas | CER/REP | Padrón con protección de datos | Parcial: `certificados_historicos` importado | Sin consulta formal ni bitácora | 6.7 | — | — |
| CER-05 | Sistema | — | — | Identificación por certificado (no solo por usuario); certificados múltiples; no excluir refrendos por solicitudes previas | Refrendo | SOL/SEG | Clave por certificado | Cumple parcialmente: el refrendo se vincula a `certificado_historico_id`; el seguimiento muestra varios trámites por persona | Historial de refrendos por certificado | CER | `Seg` (caso 12) | — |

## 6. Convocatoria, calendario, cuota y comunicación

| ID | Fuente | Año | Apartado | Requerimiento | Modalidad | Mód. | Regla de negocio | Estado actual | Brecha | Fase | Prueba | Decisión pendiente |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| CNV-01 | D01, D02 | 2026 | Toda la convocatoria | Cada ciclo tiene su convocatoria por modalidad (PTC/MT y PAES) | Todas | CNV | Trámite ligado a una convocatoria versionada | **No implementado** en Laravel. PDM: `convocatoria` y `solicitud.soli_id_convocatoria` | Falta la relación convocatoria–solicitud | Antes de lanzamiento | — | Autorizar el módulo CNV |
| CNV-02 | D01 | 2026 | Base II | Registro del 14-nov-2025 al 27-feb-2026; resultados a partir del 11-abr (PAES 13-abr); entrega el 12-jun | Todas | CNV/SOL | Periodo de registro con zona horaria oficial | No implementado | Sin apertura ni cierre | Antes de lanzamiento | — | Calendario 2027 |
| CNV-03 | D01 | 2026 | V final | Fuera del periodo, la solicitud no se acepta | Todas | CNV/SOL | Bloqueo de envío fuera de periodo | No implementado | Borradores al cierre | Antes de lanzamiento | — | Reglas de cierre y prórroga |
| PAG-01 | D01 | 2026 | Base VI | Cuota de recuperación (2026: $900 MXN) por depósito o transferencia | Todas | ADM/CNV | Importe por convocatoria | No implementado. PDM: `conv_monto_recuperacion`, `pago` | Validación administrativa | ADM | — | Importe 2027 |
| PAG-02 | D01 | 2026 | Base VI | Facturación mediante correo independiente, a más tardar 5 días antes del fin del mes del pago | Todas | — | Fuera del sistema | No aplica (proceso externo) | No almacenar datos fiscales | — | — | — |
| COM-01 | D01 | 2026 | V; VIII | Publicación de resultados en la página de la ANFECA | Todas | REP | Publicación aprobada | No implementado | Aprobación previa y protección de datos | 6.6 | — | — |
| COM-02 | Sistema | — | — | El acuse identifica folio, fecha, modalidad, institución y zona; **recepción ≠ validación ≠ aprobación** | Todas | SOL | Texto del acuse | Cumple parcialmente: el acuse muestra la zona desde `zona_anfeca` | Revisar redacción e inciso 14.4 | Antes de lanzamiento | `Reg` | Revisión del texto |

## 7. Coordinación, zonas y seguridad (implementado en esta rama)

| ID | Fuente | Año | Apartado | Requerimiento | Modalidad | Mód. | Regla de negocio | Estado actual | Brecha | Fase | Prueba | Decisión pendiente |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| ZON-01 | D07 | 2026 | Arts. 3–5 | La solicitud se presenta ante la coordinación regional de la zona de la institución; hay 7 zonas | Todas | COO | Zona derivada de la institución | Cumple: resolución centralizada (ZonasAnfeca) con las 7 zonas oficiales | `zonas` vacía; `zona_id` nulo (propuesta de normalización) | Antes de lanzamiento | `Zon` | Autorizar la normalización |
| ZON-02 | D09 | 2026 | Ejercicio académico | El número de afiliación determina la institución y la zona | Todas | SOL/COO | Afiliación → institución → zona | Cumple parcialmente (selección por catálogo de instituciones) | Padrón oficial | Mejora | `Zon` | — |
| SEG-01 | Instrucción | 2026-11 | Bloque D | Seguimiento previo al envío, solo lectura | — | SEG | Sin escritura ni PDF de borradores | Cumple | — | — | `Seg` | — |
| REV-01 | D06, D07 | 2026 | Art. 4.7 | Revisión documental con historial, sin dictamen automático | — | REV | Solo inserción | Cumple (desactivada hasta autorización) | Guías por documento | REV | `Rev`, `Esq`, `Conc` | Activación |

## 8. Matriz de pruebas de aceptación normativa

Cada requisito tiene al menos un criterio verificable. Los casos marcados **«⏸ fuente»** dependen de decisiones o documentos oficiales pendientes y **no se activan**.

| Prueba | Requisito | Caso positivo | Caso negativo o límite | Estado |
|---|---|---|---|---|
| PA-ELE-01 | ELE-01 | Refrendo con certificado histórico validado → se crea el borrador | Refrendo sin certificado → «revisión pendiente» | Automatizada (`Reg`) |
| PA-ELE-02 | ELE-02/03 | PAES: primera vez en «Disciplinas vinculadas» | PAES en refrendo o en otra área → rechazo | ⏸ fuente (convocatoria 2027) |
| PA-ELE-06 | ELE-06 | Constancia expedida 89 días antes | 92 días antes → observación del revisor | ⏸ decisión (captura de fecha) |
| PA-DOC-02 | DOC-02 | Con posgrado: título y cédula | Sin posgrado → hoy se bloquea (etapa 1) | ⏸ decisión |
| PA-DOC-08 | DOC-08 | PDF de 5 MB | PDF de 5.1 MB → 422; JPG → hoy se acepta | Parcial (`Reg`); ⏸ formatos |
| PA-ART-01 | ART-01 | 13.1 como primera vez | 13.1 como refrendo → hoy se acepta | ⏸ aclaración |
| PA-ART-02 | ART-02 | Inciso «14» (emérito) seleccionable | — | ⏸ autorización |
| PA-REV-01 | REV-01 | Validado con archivo | Validado sin archivo → 422 | Automatizada (`Rev`) |
| PA-ZON-01 | ZON-01 | Coordinador regional ve su zona | Zona solo textual → sin acceso regional | Automatizada (`Zon`) |
| PA-SEG-01 | SEG-01 | Seguimiento de borradores | PDF de borrador → 403 | Automatizada (`Seg`) |
| PA-CNV-03 | CNV-03 | Envío dentro del periodo | Envío fuera del periodo → bloqueo | ⏸ calendario |
| PA-EVA-03 | EVA-03 | 2000 puntos → procede la certificación | 1999 → no procede | ⏸ instrumento 2027 |
| PA-EVA-07 | EVA-07 | Evaluador de otra institución | Evaluador de la misma institución → bloqueo | ⏸ fase 6.4 |
| PA-CER-01 | CER-01 | Certificado dentro de vigencia | Certificado vencido → refrendo | ⏸ regla 2027 |
| PA-PAG-01 | PAG-01 | Pago validado por la coordinación | Importe distinto → discrepancia registrada | ⏸ autorización ADM |
