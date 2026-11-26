# Catálogo de posgrados: trazabilidad, verificaciones y reutilización

**Fecha:** 2026-11-26. **Commit de referencia:** `9b21c42` (rama `desarrollo/modulo-coordinacion`).

**Estado en 8086:**
- bandera **desactivada**;
- migración **no aplicada**;
- JSON **no instalado**;
- compilación servida `app-JYtMCHvV.js` (anterior al catálogo).

Las pruebas se ejecutaron solo en árboles aislados, en SQLite y en PostgreSQL desechables, y en la demostración de `127.0.0.1:8099`.

## 1. Trazabilidad integral del título del último grado

Significado de los campos:

| Campo | Contenido |
|---|---|
| `nivel_ultimo_grado` | Nivel |
| `titulo_ultimo_grado` | **Área académica**, no el nombre del título |
| `titulo_ultimo_grado_otro` | Título libre del área «Otro» |
| Columnas nuevas | `titulo_ultimo_grado_especifico`, `origen_titulo_ultimo_grado`, `clave_posgrado_catalogo`, `version_catalogo_posgrados` |

### Matriz de impacto

| Pantalla o proceso | Componente | Usa hoy | ¿Debe leer las columnas nuevas? | Estado |
|---|---|---|---|---|
| Formación académica (captura, retorno al borrador, autorización y bloqueo) | `FormacionAcademicaController`, `FormacionAcademica.vue`, `SelectorBuscable.vue`, `formacion-academica.blade.php` | nivel, área, `_otro` | Sí (las 4) | **Compatible**: implementado y probado |
| Validación y persistencia | `TituloUltimoGrado`, `CatalogoPosgrados`, `FormacionAcademicaSolicitud` (`$fillable`) | nivel, área, `_otro` | Sí | **Compatible** |
| Confirmación de la formación («Autorizar y continuar») | `FormacionAcademicaController::autorizar` | Solo la fecha de autorización | No | **Sin cambios**: bloquea todo cambio posterior (probado) |
| Finalización y confirmación de envío | `RegistroCompletadoController` (requisitos de envío) | nivel y área obligatorios | No: el título específico lo exige el formulario cuando el catálogo está activo; los registros históricos siguen siendo válidos | **Sin cambios** |
| Mis datos / Datos personales | `DatosSolicitanteController` | — | No | **Sin cambios** |
| Certificación y refrendo | `SeleccionCertificacionController`, `DatoRefrendoController` | — | No | **Sin cambios** |
| Documentos probatorios | `DocumentoProbatorioController` | — | No | **Sin cambios** |
| Acuse (HTML y PDF: misma vista) | `acuse-recepcion.blade.php`, `AcuseRecepcionController` | área y `_otro` | Sí (título exacto) | **Compatible**: muestra título y área. Ajuste recomendado: §2 |
| Ficha del solicitante en el expediente de coordinación | `ConsultaSolicitudes::detalle`, `coordinacion/expediente.blade.php` | nivel, área y `_otro` | Sí (título y origen) | **Compatible**: muestra título, origen y área; lee las columnas solo si existen. Ajuste recomendado: §2 |
| Bandeja de solicitudes y tablero | `SolicitudesController`, `ConsultaSolicitudes::columnas`, `inicio.blade.php` | No muestran el título | No | **Sin cambios** |
| Seguimiento de registros | `SeguimientoRegistros::CAMPOS_FORMACION` | nivel y área (completitud) | No | **Sin cambios** |
| Solicitudes de primera vez y refrendos (13, 14.x) | Mismo flujo de Formación | — | — | **Compatible**: probado de inicio a fin, incluido el art. 14 |
| Reportes y exportaciones | No existen (fase 6.6) | — | Sí, cuando se creen | **Pendiente futuro**: deben usar el presentador central (§2) |
| PDM (apagado) | `EquivalenciaPdm`, `PlanMigracionPdm`, `anfeca_compat_vistas.sql`, `anfeca_compat_escritura.sql` | nivel, área y `_otro` | **Sí, antes de activar el PDM** | **Requiere ajuste** (ver nota) |
| Modelo antiguo `FormacionAcademica` (tabla `formaciones_academicas`) | Sin uso en el flujo actual | nivel y área | No | **Sin cambios** |

> **Nota PDM (bloqueante solo para activar el PDM).** El disparador `anfeca_compat_escritura.sql` (línea 592) inserta en `public.formaciones_academicas_solicitud` con una **lista explícita** de columnas. En modo PDM, los títulos del catálogo **no se guardarían**.
> - Antes de activar el PDM con el catálogo activo, hay que extender la capa de compatibilidad y el modelo PDM a las 4 columnas.
> - El reflejo en `_otro` (área «Otro») conserva el texto, pero no la clave ni la versión.

Los registros históricos no se modifican en ningún componente:
- sin título específico, se muestran igual que antes;
- consultar no escribe (probado);
- las 20 huellas documentales de los folios 3, 4 y 5 son idénticas al punto de recuperación (verificadas dos veces el 26-nov, por HTTP y en solo lectura).

## 2. Presentación centralizada del título

**Recomendación:** un único presentador, `App\Services\Catalogos\TituloUltimoGrado::presentar()` (ya existe), con este orden:
1. Título específico registrado (`titulo_ultimo_grado_especifico`).
2. Título libre histórico (`titulo_ultimo_grado_otro`) cuando el área es «Otro».
3. Valor histórico del área (`titulo_ultimo_grado`).

**El área se muestra aparte**, nunca como si fuera el nombre del título.

**Duplicaciones detectadas:**
- `expediente.blade.php` usa una función local `$titulo()` cuando no hay título específico;
- `acuse-recepcion.blade.php` repite la regla con `@if`.

Las dos producen el mismo resultado que el presentador. Hoy no hay ningún error, pero son tres copias de la misma regla.

**Cambio propuesto (pendiente; bajo riesgo; requiere regresión del acuse y del expediente):**
- agregar `TituloUltimoGrado::describir($f)`, que devuelva `['titulo' => …, 'area' => …, 'origen' => catalogo|manual|historico|otro_historico]`;
- usarlo en las dos vistas y en los futuros reportes y exportaciones.

No se aplicó en esta entrega porque las vistas del sitio 8086 se sirven desde el mismo directorio y el resultado actual ya es correcto.

## 3. Refrendos (pruebas aisladas, `CatalogoPosgradosIntegralTest`)

| Escenario | Resultado |
|---|---|
| Usuario con certificado histórico → refrendo nuevo → Formación con título del catálogo | ✔ La etapa 1 exige Formación antes de documentos probatorios |
| Usuario con **dos** certificados históricos | ✔ El refrendo queda vinculado al certificado elegido; una sola formación |
| Retorno a la solicitud en borrador | ✔ Se muestra el título, la clave y el texto guardados; guardar sin cambios no altera nada (ni documentos ni historial) |
| Refrendo art. 14 (14.1) y art. 13 (ordinario) | ✔ Ambos exigen Formación y sus documentos (regla de la etapa 1, sin excepciones anticipadas) |
| Formación previamente registrada en un refrendo | ✔ El título de origen histórico **no** se vuelve a enviar (`prohibited`, regla vigente) |
| Sustitución de documentos del último grado | ✔ Las rutas anteriores pasan a `historial_documentos_academicos`; los archivos anteriores siguen en disco; los de origen no cambian |
| Formación autorizada | ✔ Cualquier cambio (catálogo o manual) se rechaza y la fila no cambia |
| Solicitud enviada | ✔ Se rechaza y no cambia; el acuse PDF se genera |
| Sin documentos del último grado en el primer guardado | ✔ Se rechaza con errores por documento; no se guarda nada |

No se inician refrendos automáticamente.

## 4. Integridad documental

**Seleccionar, cambiar o capturar manualmente el título:**
- **no** modifica las rutas de los 4 documentos;
- **no** crea filas de historial;
- **no** elimina ni toca archivos físicos.

Solo una **sustitución explícita** de documentos agrega historial (probado). Las solicitudes enviadas y las formaciones autorizadas no cambian (probado). En 8086 las 20 huellas documentales son idénticas (`huellas_documentos.sh`, solo lectura).

## 5. Experiencia del solicitante

| Aspecto | Estado | Observación |
|---|---|---|
| Mensajes del catálogo | ✔ En español: «Seleccione el nombre del título o elija “No encuentro mi título”», «El título no corresponde al nivel y al área seleccionados», «Escriba el nombre de su título» | — |
| Mensajes genéricos de Laravel | ⚠ **En inglés en 8086** (`app.locale = en`; `APP_LOCALE` no definido). Ejemplo: «The titulo origen field is prohibited.» | Preexistente. Propuesta: `APP_LOCALE=es` y completar `lang/es/validation.php` (109 líneas; faltan reglas y atributos). Requiere autorización (`.env` de 8086) |
| Campos obligatorios | ✔ «Nombre del título *» y la captura manual | Nivel y Área no llevan asterisco (igual que el formulario anterior); se recomienda homologar |
| Conservación tras un error | ✔ `old()` de nivel, área, modo, clave y texto manual | — |
| Estado de guardado | ✔ Aviso «Cambios pendientes de guardar» al seleccionar (verificado en la demo) | — |
| Bloqueo tras autorizar | ✔ Selector deshabilitado con el título visible; el servidor rechaza los cambios | — |
| Teclado y accesibilidad | ✔ `combobox`/`listbox`, flechas, Inicio/Fin, RePág/AvPág, Intro, Escape, foco de vuelta al disparador | — |
| Diseño responsivo | ✔ Sin desplazamiento horizontal a 390 px; lista dentro de la pantalla | En < 480 px, Nivel y Área se apilan |
| Menú lateral y etapas en verde | ✔ Sin cambios: la completitud sigue dependiendo de nivel y área | — |
| Catálogo no disponible (bandera sin migración, archivo ausente, huella o versión distinta, colisión de claves) | ✔ Aviso visible y formulario anterior; endpoint 404; motivo en el registro | Nunca una lista vacía silenciosa |
| Falla de red al cargar títulos | ✔ Mensaje y opción «No encuentro mi título»; el título guardado sigue visible | — |
| Título guardado retirado en una versión nueva | ✔ **Corregido en `6187c91`**: se conserva con aviso; antes la interfaz lo borraba y obligaba a recapturar | — |

## 6. Seguridad y concurrencia

| Verificación | Resultado |
|---|---|
| Endpoint sin sesión | 401 (JSON) o redirección a `login` |
| Rol de coordinación en el endpoint o en el POST de Formación | Redirección a `coordinacion.inicio`; no se guarda nada |
| Claves manipuladas (otra área, otro nivel, inexistente, en minúsculas, con formato inválido) | Rechazadas; la fila no cambia |
| Nivel o área manipulados | Rechazados |
| Nombre, origen o versión impuestos por POST | **Ignorados**: el servidor toma el título y la versión del catálogo |
| Texto libre (`_otro`) enviado con el catálogo activo | `prohibited` |
| Versión configurada inexistente | Catálogo en estado de error, formulario anterior; guardar sin cambios conserva los datos |
| Formación autorizada o solicitud enviada | Rechazo; la fila no cambia |
| Dos pestañas | Gana el último guardado, **completo y coherente**: nunca mezcla la clave de una pestaña con el área de otra; una sola fila por solicitud |
| Guardados simultáneos | `lockForUpdate` sobre la solicitud y la formación; una sola sentencia `updateOrCreate` con las 4 columnas; el CHECK de PostgreSQL impide combinaciones incoherentes |
| Exposición del JSON | Fuera de `public/`; ninguna ruta lo sirve (404; `/storage/…` responde 403, ruta firmada); el endpoint solo entrega `clave` y `título` |
| Fuentes privadas | `~/ANFECA_FUENTES` con permisos 700 y archivos 600; Apache no puede leerlas (HOME 700) |

**Riesgo residual (bajo).** Con la bandera desactivada, `protegerSinCatalogo()` compara con la formación leída **antes** del bloqueo. Dos guardados exactamente simultáneos desde el formulario anterior podrían evaluar un estado previo. La fila resultante siempre es coherente gracias al CHECK. Mejora propuesta: evaluar la protección con la fila bloqueada dentro de la transacción.

## 7. Versionado y actualización futura

Prueba con dos versiones ficticias (`posgrados_ficticio.json` V1 y `posgrados_ficticio_v2.json` V2):

| Cambio en V2 | Registro guardado con V1 | Nueva selección |
|---|---|---|
| Alta de títulos | — | ✔ Se guarda con la versión `FICTICIO-2` |
| Título retirado | ✔ Se conserva intacto (título, clave y versión V1) si no se modifica | ✔ Ya no se ofrece; la clave retirada se rechaza |
| Reclasificación (Contaduría → Administración) | ✔ Se conserva con su área original | ✔ Seleccionarlo en la nueva área guarda V2 |
| Cambio de denominación con la **misma** clave (acentos, mayúsculas) | ✔ El texto original **no** se reescribe | — |
| Cambio de denominación con clave **nueva** | ✔ Se conserva el anterior | ✔ La clave nueva guarda el nombre nuevo |
| Colisión de claves | El catálogo **completo** se rechaza (estado de error); los datos se conservan; el clasificador ahora se detiene con el detalle (`c9a4d68`) | — |
| Consulta por versión de origen | ✔ `version_catalogo_posgrados` permite contar y consultar por versión | — |

**Comportamiento definido del formulario cuando el título guardado ya no está en la versión vigente:**
- se muestra el título guardado con el aviso «Título registrado con la versión X del catálogo; ya no figura en la versión vigente. Se conserva sin cambios; puede elegir otro si lo desea»;
- guardar sin cambiar clave, nivel ni área **conserva** el registro;
- elegir otro título o cambiar nivel o área exige una selección válida en la versión vigente;
- si el solicitante vuelve al nivel y al área guardados, se recupera la selección guardada;
- **nunca** se modifica automáticamente lo registrado.

## 8. Clasificaciones ambiguas: propuestas por bloques

Archivo: `~/ANFECA_FUENTES/resumen_revision_posgrados.xlsx`, fuera de GitHub. La matriz original no cambió (`ef1fedec…`). Hojas nuevas:
- «Bloques propuestos»;
- «Contradicciones»;
- «Otro relacionable»;
- «Multiárea».

| Tipo de propuesta | Títulos | Criterio |
|---|---:|---|
| **A. Aprobar el área propuesta en bloque** | 182 | Subgrupo de ≥ 3 títulos con ≥ 80 % del grupo en la misma área y mayoría sin confianza baja. Ejemplos: Biblioteconomía → Otro (45); Ciencias de la computación → Informática (22); Administración de negocios internacionales → Negocios Internacionales (18); Auditoría → Contaduría (14) |
| **B. Contradicción: decidir el área del grupo** | 219 | 11 grupos con dos o más áreas relevantes. Los mayores: Actuaría (91: Administración / Contaduría); Abogacía internacional (30); Ciencias de la comunicación (28); Gestión de empresas turísticas (23 y 11: Administración / Otro) |
| **C. Revisión individual** | 171 | Subgrupos pequeños o con confianza baja mayoritaria |

**Otros hallazgos:**
- **«Otro» con relación razonable a un área base:** 119 títulos (área alternativa: Administración 84, Informática 23, Contaduría 11, Negocios Internacionales 1).
- **Multiárea** (propuesta y alternativa en áreas base distintas): 163 títulos.

Nada se aprobó ni se reclasificó.

## 9. Componentes reutilizables

| Componente | Reutilización | Condiciones |
|---|---|---|
| `SelectorBuscable.vue` | Cualquier catálogo de más de ≈ 30 opciones (instituciones, carreras, municipios) | Recibe `opciones [{clave, titulo}]`; la opción manual es opcional; escribir en campos con `name` y emitir `input` |
| `CatalogoPosgrados` como modelo de «catálogo versionado en archivo» | Otros catálogos oficiales en JSON | Validación de estructura, versión, huella SHA-256 opcional, claves únicas y error explícito (`CatalogoNoDisponible`) |
| Validación por clave en el servidor | Cualquier selección de catálogo | El texto lo determina el servidor; el navegador solo envía la clave |
| Clave estable `PREFIJO-12 hex` (SHA-256 del texto normalizado) | Catálogos sin identificador oficial | Detectar colisiones al generar (el script se detiene) |
| Captura manual controlada | Opción «No encuentro…» | Límites de longitud, sin `<` ni `>`, origen `manual` y sin clave ni versión |
| Bandera con estado `desactivado` · `activo` · `error` | Funciones nuevas con dependencias (migración, archivo) | Con error: formulario anterior y aviso, nunca falla silenciosa |
| Migración aditiva propuesta | Columnas nuevas en tablas con datos | Fuera de la ruta automática; CHECK con `coalesce`; `down()` que se niega con datos |
| Aplicación, verificación y reversión controladas | Toda migración en 8086 | Autorización, respaldo verificado reciente, código igual a HEAD, huellas de tablas, datos y documentos (`aplicar_/verificar_/revertir_catalogo_posgrados.sh`) |
| Huellas documentales por HTTP | Antes y después de cualquier intervención | `huellas_documentos.sh` (solo lectura, coordinación nacional) |
| Demostración aislada | Aceptación visual de cualquier pantalla | `demo_catalogo/iniciar.sh` como plantilla: `git archive`, compilación propia, SQLite ficticio, 127.0.0.1 y contraseña de un solo uso |

**No se hicieron refactorizaciones generales.** Los criterios se incorporaron a `ESTANDARES_UI_UX.md` §7 bis.7 y §7 bis.8.
