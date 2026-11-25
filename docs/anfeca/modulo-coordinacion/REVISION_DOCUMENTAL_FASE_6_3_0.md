# Fase 6.3.0 · Análisis y diseño de la revisión documental

**Solo análisis, diseño y prototipo aislado.**
- No se crearon tablas, no se ejecutaron migraciones, no se activó el PDM y no se guardó ninguna revisión.
- Ambiente analizado: `bd_anfeca_ensayo_pruebas_pg`, con consultas de solo lectura.
- Fecha: 2026-11-25.

## 1. Objetivo y principios

La revisión se hará **en la misma pantalla de consulta de solicitud**, sobre las tarjetas de documento existentes, sin un flujo aparte.

Principios:
1. El **estado de carga** («Cargado», «No cargado», «Registrado, archivo no localizado», «No aplica») y el **resultado de revisión** son independientes. Un documento cargado nunca equivale a uno validado.
2. La revisión se guarda **separada** de la solicitud. No modifica archivos, datos personales, formación académica ni datos de certificación.
3. Cada revisión queda ligada a la **versión documental revisada**.
4. La revisión documental no es calificación ni dictamen. Ninguna regla la convierte en una decisión sobre la solicitud.

## 2. Estados de revisión y transiciones

| Estado | Descripción | ¿Lo elige el coordinador? |
|---|---|---|
| Por revisar | Sin revisión registrada para la versión vigente | No: es el estado inicial (sin registro) |
| Validado | Cumple los requisitos de revisión | Sí |
| Con observaciones | Hay una inconsistencia que requiere aclaración | Sí, con observación obligatoria |
| No válido | No cumple los requisitos | Sí, con observación obligatoria |
| No aplica | El documento no corresponde al trámite | **No**: se calcula con las reglas del trámite y no se usa para omitir documentos obligatorios |

```
                 ┌─────────────── (nueva revisión con justificación) ──────────────┐
                 ▼                                                                 │
  Por revisar ──► Validado ◄──────────► Con observaciones ◄──────────► No válido ──┘
       │             ▲                        ▲     │                      ▲
       └─────────────┴────────────────────────┘     └──────────────────────┘
  No aplica: fuera del ciclo (calculado). Nueva versión del documento ⇒ «Por revisar» para ESA versión.
```

**Transiciones permitidas:**
- Desde «Por revisar» a «Validado», «Con observaciones» o «No válido».
- Entre «Validado», «Con observaciones» y «No válido», en cualquier sentido.
  - Cada cambio es una **revisión nueva**; la anterior se conserva en el historial.
  - Pasar a «Con observaciones» o «No válido» exige observación.
  - Cambiar un resultado ya registrado exige una justificación breve, guardada en el mismo campo de observaciones.

**Transiciones no permitidas:**
- Volver a «Por revisar». El historial no se borra.
- Elegir «No aplica» libremente.
- «Validado» sobre un archivo «No cargado» o «Registrado, archivo no localizado».
- Cualquier revisión sobre una solicitud que no esté `enviado`.

## 3. Reglas para la implementación funcional

- **Guardado por documento:** el avance es parcial y no exige revisar todo el expediente en una sesión. El avance se reconstruye desde el historial.
- **Observaciones:** obligatorias para «Con observaciones» y «No válido», de 10 a 1000 caracteres. Son de uso interno; no se envían al solicitante en esta fase.
- **Datos de auditoría:** coordinador responsable (`users.id` y nombre al momento de revisar) y fecha y hora en UTC, mostradas en America/Mexico_City.
- **Historial:** tabla de solo inserción. Nunca se actualizan ni borran revisiones.
- **Archivos inexistentes:** no se permite «Validado» si el archivo no existe en el almacenamiento.
- **Solicitudes no enviadas:** no se revisan.
- **Sin efectos automáticos:** la revisión no genera dictamen, calificación ni cambio de estado de la solicitud.
- **Versión revisada:** cada revisión guarda la ruta del archivo y su huella SHA-256 al momento de revisar. Si el archivo cambia (sustitución futura autorizada), la revisión anterior queda ligada a la versión anterior y la nueva versión inicia en «Por revisar». **La validación no se traslada.**

## 4. Indicadores de revisión (por solicitud)

| Indicador | Cálculo |
|---|---|
| Documentos aplicables | Documentos del trámite (6, más 2 en refrendo por art. 13/14); **excluye «No aplica»** |
| Cargados | Aplicables con archivo existente |
| Faltantes | Aplicables «No cargado» o «Registrado, archivo no localizado» |
| Por revisar | Aplicables sin revisión de su versión vigente (con o sin archivo). Se cumple: por revisar + validados + con observaciones + no válidos = aplicables |
| Validados / Con observaciones / No válidos | Último resultado de la versión vigente |
| **Avance de revisión** | (validados + con observaciones + no válidos) ÷ aplicables |
| **Documentos validados** | validados ÷ aplicables (porcentaje distinto del avance) |

En el ensayo no se generan indicadores ficticios. El prototipo calcula sus indicadores solo con datos demostrativos.

## 5. Análisis de `anfeca_modelo.ESTADO_SOLICITUD_DOCUMENTO`

```
ESTADO_SOLICITUD_DOCUMENTO
  ESDO_ID_ESTADO_SOLICITUD_DOCUME  SERIAL        PK
  ESDO_ID_DOCUMENTACION            INT4  NN      FK → SOLICITUD_DOCUMENTO.SOCU_ID_DOCUMENTACION
  ESDO_ID_ESTADO_DOCUMENTO         INT4  NN      FK → ESTADO_DOCUMENTO.ESDO_ID_ESTADO_DOCUMENTO
  ESDO_OBSERVACION                 VARCHAR(300) NN
  ESDO_FECHA                       DATE  NN
  ESDO_HORA                        TIME  NN
SOLICITUD_DOCUMENTO   (una fila por archivo cargado = versión)
  SOCU_ID_DOCUMENTACION SERIAL PK · SOCU_ID_SOLICITUD → SOLICITUD · DOCU_ID_DOCUMENTO → DOCUMENTO
  SOCU_FECHA, SOCU_HORA · SOCU_URL_ARCHIVO VARCHAR(500) NN (extensión ANFECA)
ESTADO_DOCUMENTO      catálogo: 1 «Cargado», 2 «Sustituido» (únicos valores sembrados)
DOCUMENTO             catálogo: 9 tipos (los 8 documentos más «Enlace de evidencias»)
```

En el ensayo, `SOLICITUD_DOCUMENTO` y `ESTADO_SOLICITUD_DOCUMENTO` tienen **0 filas**, porque el PDM está apagado.

| Aspecto | Situación en el PDM | Suficiente para 6.3 |
|---|---|---|
| Relación con solicitud | Indirecta, vía `SOLICITUD_DOCUMENTO.SOCU_ID_SOLICITUD` | Sí |
| Relación con documento | `SOLICITUD_DOCUMENTO.DOCU_ID_DOCUMENTO` | Sí |
| Versión documental | Una fila de `SOLICITUD_DOCUMENTO` por archivo; el estado se liga a esa fila | **Sí (fortaleza)** |
| Estados disponibles | Solo «Cargado» y «Sustituido», que son estados de **carga** | No: faltan los estados de revisión y no se distinguen de los de carga |
| Observaciones | `VARCHAR(300) NOT NULL` | Parcial: 300 caracteres, y obligatoria incluso cuando no aplica |
| Evaluador | **No existe** | No: requiere ampliación |
| Fecha y hora | `DATE` + `TIME` sin zona | Parcial: sin zona horaria |
| Historial | Varias filas por versión (bitácora) | Sí |
| Integridad del archivo | No guarda huella | No: requiere ampliación |

**Conclusión:** la tabla sirve como **bitácora por versión**, pero necesita:
- distinguir el tipo de evento (carga o revisión);
- registrar al evaluador;
- ampliar las observaciones;
- guardar la huella del archivo revisado.

Esto confirma el diagnóstico de `ANALISIS_PDM_PREVIO_FASE_6_4.md`, punto 2.2.

**En Laravel (`public`):**
- La ruta vigente del archivo está en las columnas de `formaciones_academicas_solicitud` y `documentos_probatorios_solicitud`.
- `historial_documentos_academicos` guarda versiones anteriores solo de los documentos académicos (0 filas en el ensayo).
- Los documentos probatorios se bloquean tras el primer guardado, así que tienen una sola versión.
- No existe ninguna estructura de revisión.

## 6. Alternativas de persistencia

| | A. PDM directo | B. Laravel compatible | C. Híbrida (**recomendada**) |
|---|---|---|---|
| Tablas | `SOLICITUD_DOCUMENTO`, `ESTADO_SOLICITUD_DOCUMENTO`, `ESTADO_DOCUMENTO` (+ extensiones) | Nueva `public.revisiones_documentales` (+ catálogo `resultados_revision`) | Nueva `public.revisiones_documentales` (solo inserción). Al activar el PDM se espeja en `ESTADO_SOLICITUD_DOCUMENTO`, ampliada con extensiones |
| Campos | Extensión: `ESDO_ID_USUARIO`, `ESDO_TIPO_EVENTO` (CARGA/REVISION), `ESDO_HUELLA`; observación a 1000; nuevos valores de `ESTADO_DOCUMENTO` | `solicitud_id`, `campo_documento`, `ruta_archivo`, `huella_sha256`, `resultado`, `observaciones`, `revisado_por`, `revisado_en`, `revision_anterior_id` | Los de B, más una correspondencia `campo_documento → DOCU_ID_DOCUMENTO` y `ruta_archivo → SOCU_ID_DOCUMENTACION` |
| Relaciones | Versión → estado; usuario técnico del PDM | `solicitudes_certificacion`, `users` | Las de B, más un espejo en el PDM por la misma capa F3 que ya usan las solicitudes |
| Impacto | Requiere **activar el PDM** (migración F2, decisiones D-11, zonas) | Independiente del PDM | Funciona hoy con el PDM apagado y se integra después sin transformaciones |
| Ventajas | Una sola fuente, sin duplicar | Rápida, probada con las herramientas actuales | Avance inmediato y compatibilidad futura; reutiliza la versión documental del PDM |
| Desventajas | Bloqueada por los pendientes del PDM; el modelo original no tiene evaluador | Duplicará datos cuando se active el PDM | Requiere mantener el espejo y su conciliación |
| Riesgos | Retrasa la fase 6.3; extensiones sobre el PDM | Divergencia de modelos | Equivalencia por mantener, mitigada con pruebas de conciliación como en F3 |
| Evaluación, dictamen y actas | Directa | Requiere mapeo posterior | Directa: `EVALUACION` y `DICTAMEN` se apoyan en `SOLICITUD` y el historial queda espejado |
| Esfuerzo | Alto | Bajo | Medio |

**Recomendación:** alternativa **C**.
- Es la única que permite implementar la revisión con el PDM apagado sin cerrar el camino a la integración.
- Conserva la versión documental como eje: ruta y huella en Laravel; `SOCU_ID` en el PDM.
- Añade lo que falta al PDM (evaluador, tipo de evento, huella, observación de 1000) como **extensiones** documentadas, igual que en F1–F5.

Antes de implementarla requiere su autorización para crear la tabla y la migración.

## 7. Propuesta de rutas, controladores y servicios

Todas las rutas van dentro del grupo existente `auth` + `coordinacion`, con verificación de alcance por zona en cada operación.

| Método | Ruta | Acción | Autorización |
|---|---|---|---|
| GET | `/coordinacion/solicitudes/{s}/revision` | Estado de revisión e indicadores (JSON) | Policy `consultarComoCoordinacion` |
| GET | `/coordinacion/solicitudes/{s}/documentos/{campo}/revisiones` | Historial del documento | Policy `consultarComoCoordinacion` |
| POST | `/coordinacion/solicitudes/{s}/documentos/{campo}/revisiones` | Registrar resultado | **Nueva** Policy `revisarDocumentoComoCoordinacion`: alcance por zona, solicitud `enviado` y documento aplicable |

Componentes propuestos:
- `Coordinacion\RevisionDocumentalController`: solo orquesta.
- `RegistrarRevisionRequest`: valida el resultado (sin «Por revisar» ni «No aplica»), la observación obligatoria, el campo en la lista blanca y el `revision_vigente_id` para la concurrencia.
- `Services\Coordinacion\DocumentosAplicables`: extrae de `ExpedienteController` la regla de aplicabilidad y los estados de carga, para que la consulta y la revisión compartan una sola fuente.
- `Services\Coordinacion\RevisionDocumental`:
  - transiciones;
  - bloqueo de «Validado» sin archivo;
  - huella SHA-256;
  - indicadores;
  - transacción con `lockForUpdate()` sobre la solicitud.
- `Models\RevisionDocumental` (solo inserción) y el enum `ResultadoRevision`.
- **Concurrencia (control optimista):** el cliente envía el `id` de la última revisión que vio. Si ya existe otra más reciente, la respuesta es **409** con la revisión del otro coordinador y no se sobrescribe nada.
- **Interfaz:** componente Vue sobre las tarjetas actuales (prototipo `RevisionDocumentalPrototipo.vue`). El detalle de auditoría es desplegable y las observaciones van en un panel que no ocupa espacio permanente.

## 8. Prototipo visual aislado

- Ruta `/coordinacion/prototipos/revision-documental`. Solo existe cuando `ANFECA_COORDINACION_PROTOTIPOS=true` (`config/anfeca_coordinacion.php`); por omisión está **desactivada**, así que en el 8086 responde 404.
- **No consulta ni escribe la base de datos.** Usa documentos ficticios fijos y un estado en memoria del navegador, con el aviso visible «PROTOTIPO DEMOSTRATIVO · datos ficticios · nada se guarda».
- Muestra:
  - los indicadores compactos;
  - las tarjetas con estado de carga y resultado de revisión;
  - el panel de revisión con observación obligatoria;
  - el bloqueo de «Validado» sin archivo;
  - «No aplica» no seleccionable;
  - el detalle desplegable de auditoría e historial.
- Solo se ejecuta en entornos aislados, como la demostración desechable de Podman.

## 9. Matriz de pruebas para la fase 6.3

| # | Caso | Resultado esperado |
|---|---|---|
| 1 | Revisar un documento cargado como «Validado» | Se inserta la revisión con coordinador, fecha, ruta y huella; los indicadores se actualizan |
| 2 | «Validado» sobre un archivo no cargado o no localizado | 422; no se inserta nada |
| 3 | «Con observaciones» o «No válido» sin observación | 422 en `observaciones` |
| 4 | Guardado parcial (2 de 6) y nueva sesión | Avance 2/6 recuperado desde el historial |
| 5 | Historial | Todas las revisiones, en orden, sin actualizaciones ni borrados |
| 6 | Cambio de resultado (Validado → No válido) | Revisión nueva con justificación; la anterior se conserva |
| 7 | Documento no aplicable (art. 14 en primera vez) | 422 o 404; no se puede revisar ni se cuenta en el denominador |
| 8 | Documentos adicionales del artículo 13/14 | Revisables solo en refrendo con `aplica_articulo_14` |
| 9 | Nueva versión documental (huella distinta) | La nueva versión queda «Por revisar»; la validación anterior no se traslada |
| 10 | Aislamiento por zona (POST y GET de otra zona) | 404, sin inserción |
| 11 | Acceso nacional | Revisa cualquier zona, incluidas las pendientes de clasificación |
| 12 | Solicitante sobre rutas de revisión | 403 |
| 13 | Solicitud no enviada (borrador) | 422 o 409; no se revisa |
| 14 | Concurrencia: dos coordinadores con el mismo `revision_vigente_id` | La primera se registra y la segunda recibe 409 con la revisión vigente |
| 15 | Integridad de originales | Huella de archivos y filas de solicitud, formación y documentos idénticas antes y después |
| 16 | Parámetros manipulados (resultado «no_aplica» o «por_revisar», campo fuera de lista) | 422 o 404 |
| 17 | Regresión del módulo de solicitantes y de la suite PDM | Sin cambios |
