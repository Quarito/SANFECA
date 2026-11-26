# Estándares de interfaz (UI/UX) del sistema ANFECA

Estas reglas son **obligatorias** para los módulos actuales y futuros: coordinación, evaluación, actas, certificados, reportes y cualquier pantalla administrativa nueva.

Se aplican sin modificar el módulo de solicitantes. Ese módulo es la **referencia visual** y los demás se homologan con él, no al revés.

Cuando una regla no pueda cumplirse, la excepción debe quedar documentada en el `README` del módulo y autorizarse antes de publicar.

Términos: **DEBE** indica obligación; **DEBERÍA**, recomendación fuerte; **PUEDE**, opción.

---

## 1. Estructura común

### 1.1 Encabezado institucional
- **DEBE** usarse el componente `resources/views/components/anfeca/encabezado-institucional.blade.php`, con el mismo marcado y textos que las pantallas de solicitantes.
- **No DEBE** copiarse el encabezado dentro de una vista ni alterar el logotipo, los títulos o la franja.

### 1.2 Pie de página institucional
- **DEBE** usarse `resources/views/components/anfeca/pie-institucional.blade.php`: «Hecho en México», D.R., aviso de privacidad y administración del sitio.
- El pie se mantiene al fondo aunque el contenido sea corto (columna flexible con `min-height: 100vh`).

### 1.3 Layout administrativo reutilizable
- Toda vista administrativa **DEBE** extender un layout común. En coordinación es `resources/views/coordinacion/layout.blade.php`.
- La vista solo define `@section('titulo')` y `@section('contenido')`. **PUEDE** añadir `@push('estilos')` y `@push('scripts')`.
- El layout aporta la barra de usuario, con nombre, rol, alcance y «Cerrar sesión», y el menú lateral, configurado en `config/anfeca_coordinacion.php`.
- Las opciones del menú aún no implementadas se muestran deshabilitadas con la insignia «PENDIENTE» y no ejecutan ninguna operación.
- Las hojas institucionales se cargan en el mismo orden que en solicitantes y después la hoja propia del módulo.
- Cada cambio de una hoja propia **DEBE** incrementar su parámetro `?v=` para invalidar la caché.

## 2. Identidad gráfica y tipografía

### 2.1 Paleta
Se usan **solo** los tokens institucionales (`--anfeca-*`) o sus equivalentes del módulo (`--coord-*` en `public/css/coordinacion.css`):

| Token | Valor | Uso |
|---|---|---|
| `--coord-rojo` | `#bd2434` | Acciones principales, enlaces, títulos de página |
| `--coord-rojo-hover` | `#a71e2d` | Estado *hover* |
| `--coord-rosa` / `--coord-rosa-activo` | `#fcf3f3` / `#f7dfe1` | Fondos de menú y elemento activo |
| `--coord-texto` / `--coord-texto-suave` | `#26313c` / `#66717d` | Texto principal y secundario |
| `--coord-borde` | `#e6d8da` | Bordes de tarjetas y tablas |

- **No DEBEN** usarse colores fuera de la paleta en elementos estructurales.
- Los colores de estado (sección 6) son la única excepción.

### 2.2 Tipografía legible
- Fuente institucional heredada de `tipografia-global-anfeca.css`.
- Tamaños: texto general 12–13 px, títulos de página ≈ 22 px, títulos de sección 15–17 px, etiquetas secundarias ≥ 11 px.
- **No DEBE** usarse texto menor de 11 px ni gris con contraste inferior a 4.5:1 sobre blanco.
- Las cifras en tablas e indicadores usan `font-variant-numeric: tabular-nums`.

## 3. Diseño responsivo y aprovechamiento del espacio

- Las distribuciones **DEBEN** depender del espacio real del área de contenido mediante *container queries* (`container-name: contenido`), no solo del ancho de la ventana.
- Resoluciones de validación obligatorias: **1366×768**, **1920×1080**, tableta (≈ 820 px) y móvil (≈ 390 px).
- Toda entrega visual incluye capturas de esas cuatro resoluciones.
- Sin desplazamiento horizontal de página en móvil. Las tablas anchas se desplazan dentro de su contenedor (`.coord-tabla-contenedor`).
- Las cuadrículas **DEBEN** repartir los elementos en filas completas. Nunca 5 + 1 tarjetas ni 3 + 1 paneles: se eligen 6, 3 × 2, 2 × 3 o 1 columna.
- **DEBERÍA** evitarse el espacio vacío permanente. La información secundaria, como historiales, observaciones extensas o auditoría, va en paneles desplegables (`<details>`), diálogos modales o pestañas.

## 4. Fichas y componentes

### 4.1 Fichas equilibradas
- Dos fichas lado a lado **DEBEN** compartir ancho (50/50) y altura en escritorio (`align-items: stretch`), alineadas arriba y abajo.
- En pantallas estrechas se apilan y va primero la ficha principal (por ejemplo, la del solicitante).
- Dentro de una ficha:
  - los campos cortos se distribuyen en dos columnas;
  - los largos (institución, área, tipo de personal) ocupan el ancho completo.

### 4.2 Componentes compactos
- Tarjetas de documento o elemento: alto de 55 a 70 px, con icono, nombre, insignia de estado y acción («Ver», «Revisar»).
- Las leyendas comunes a una sección se muestran **una sola vez** en el encabezado de la sección, no en cada tarjeta.
- Botones: estilos `coord-boton`, en variantes primaria, `secundario` y `pequeno`. Una acción no disponible se muestra deshabilitada (`aria-disabled="true"`), sin ocultarla cuando su ausencia confundiría al usuario.

## 5. Tablas de registros

### 5.1 Numeración consecutiva visual («Núm.»)
Toda tabla que liste registros **DEBE** tener como **primera columna** «Núm.», con estas reglas:

| Regla | Detalle |
|---|---|
| Inicio | Comienza en 1 |
| Continuidad | Continúa entre páginas: la página *p* con *n* por página inicia en `(p − 1)·n + 1` |
| Recálculo | Se recalcula con búsqueda, filtros y ordenamiento, porque deriva de la posición en el resultado y no del registro |
| Alcance | Solo numera lo que el usuario puede ver: un coordinador de zona numera desde 1 dentro de sus zonas |
| Origen | Lo calcula el **servidor** (`paginator->firstItem() + índice`) y viaja en el campo `num` |
| Persistencia | **Nunca** se guarda en la base ni se agrega una columna para ello |
| Presentación | Compacta, centrada, `tabular-nums`, **no ordenable** (sin botón de orden) |
| Semántica | `<th scope="col" class="col-num" title="Consecutivo visual; no es el folio">Núm.</th>` |

- **No DEBE** usarse el número como referencia para operaciones. Para enlaces, acciones y auditoría se usa el folio o el identificador.

### 5.2 Diferenciación de identificadores
| Concepto | Qué es | Dónde se usa | Ejemplo |
|---|---|---|---|
| Consecutivo visual («Núm.») | Posición en el listado actual | Solo en pantalla y exportaciones | 1, 2, 3… |
| Folio | Identificador público de la solicitud, estable | Enlaces, comunicación con el solicitante, búsqueda | Folio 27 |
| Identificador interno (`id`) | Llave primaria técnica | Código, API y auditoría; no se rotula como «Núm.» | `solicitudes_certificacion.id` |
| Certificado histórico | Número de certificado previo (refrendo) | Búsqueda y ficha | — |

El encabezado de cada columna **DEBE** decir exactamente qué contiene. «Núm.» nunca rotula un folio ni un id.

### 5.3 Búsqueda, filtros y paginación
- Búsqueda de texto y filtros en una barra superior (`.coord-filtros`), con la opción «Limpiar filtros».
- Los filtros se aplican en el **servidor**, con validación de valores permitidos y del alcance. Un filtro fuera de alcance responde 403 y no amplía resultados.
- Paginación en el servidor con 10, 25 o 50 registros por página. Se muestran «Mostrando x–y de N» y «Página p de P» con los botones Anterior y Siguiente.
- Al cambiar un filtro, la búsqueda o el orden, se vuelve a la página 1.
- El ordenamiento se activa con el botón del encabezado, que indica la dirección (▲/▼) y expone `aria-sort`.
- La tabla sin resultados muestra un mensaje que ocupa todas las columnas, nunca una tabla vacía sin explicación.

## 6. Estados identificables mediante texto y color

- Todo estado **DEBE** comunicarse con **texto** y **color**, nunca solo con color, mediante la insignia `coord-insignia <estado>`.
- Paleta de estados:

| Familia | Fondo / borde / texto | Ejemplos |
|---|---|---|
| Correcto | `#f4faf5` / `#cfe6d3` / `#256029` | Cargado, Validado, Enviada |
| Advertencia | `#fffaf0` / `#ecd9a8` / `#5c4400` | Registrado, archivo no localizado · Con observaciones · Pendiente de clasificación |
| Error | Rojo institucional atenuado | No válido |
| Neutro | `#f4f4f4` / `#e2e2e2` / `#555` | No cargado, Iniciada, Por revisar |
| No aplica | Transparente / `#e2e2e2` / `#8a8a8a` | No aplica |

- Insignias de **resultado de revisión** (clase `coord-insignia rev rev-<estado>`): Por revisar (neutro), Validado (verde), Con observaciones (ámbar), No válido (rojo atenuado), No aplica (transparente). Van **junto a** la insignia de carga, nunca en su lugar.
- Hay que distinguir siempre el **estado de carga** (Cargado / No cargado / No localizado / No aplica) del **resultado de revisión** (Por revisar / Validado / Con observaciones / No válido). No se mezclan en una misma insignia.

## 7. Exportaciones de listados (Excel, PDF, CSV)

Las exportaciones, cuando se implementen en la fase 6.6 de reportes, **DEBEN**:
- incluir «Núm.» como primera columna, numerada de 1 a N sobre **todo** el resultado filtrado, no solo la página visible;
- respetar los mismos filtros, orden y alcance que la pantalla desde la que se exporta;
- incluir folio e identificadores en columnas propias, con encabezados explícitos;
- registrar en la cabecera o en la hoja de metadatos: fecha y hora de generación, usuario, alcance y filtros aplicados;
- en CSV, usar UTF-8 con BOM y separador «,», sin columnas ocultas;
- en PDF, usar el encabezado y pie institucionales y repetir el encabezado de la tabla en cada página;
- no incluir datos personales que la pantalla de origen no muestre.

## 7 bis. Componentes reutilizables y presentación de datos (2026-11-25)

### 7 bis.1 Visor documental
- Todo documento del expediente se muestra en `resources/js/components/VisorDocumento.vue`: ventana modal de ≈ 94 % × 90 % con encabezado institucional. **No** se descarga automáticamente ni abre pestañas nuevas.
- Usa PDF.js local (compilación «legacy», cargada solo al abrir un documento) e imágenes JPG/PNG. Sin CDN ni servicios externos.
- Controles: página anterior y siguiente, página actual y total, zoom, ajuste al ancho, pantalla completa, descarga opcional (solo si la ruta lo permite), cierre con botón y Escape.
- Accesibilidad: foco atrapado y devuelto al origen; teclado (flechas, RePag/AvPag, + y −).
- Conserva la posición de desplazamiento de la página de origen.
- Errores claros: sin permiso (403), no disponible (404), versión cambiada (409), PDF dañado, archivo inexistente con su motivo.
- Con panel lateral: documento al 70 % y panel al 30 % en escritorio; en móvil, primero el documento y después el panel. Advierte si hay cambios sin guardar.
- El enlace original se conserva en el HTML como alternativa sin JavaScript.

### 7 bis.2 Usuarios frente a trámites
- Los indicadores **DEBEN** indicar si cuentan **personas** o **trámites** y su criterio (denominador).
- Una persona con varios trámites se cuenta una vez como persona y una vez por cada trámite.
- Cada tarjeta de indicador **DEBERÍA** enlazar a la bandeja o al seguimiento filtrados cuando sea viable.

### 7 bis.3 Seguimiento de etapas y avance
- Las etapas reproducen el flujo real del solicitante, con cuatro estados con texto y color: **Completada**, **En proceso**, **Pendiente**, **No aplica**.
- La etapa actual es el **siguiente requisito pendiente**, no la última pantalla abierta.
- **Avance de captura** = etapas aplicables completadas ÷ etapas aplicables, **sin contar el envío**. 100 % significa «listo para envío», no «enviada».
- «Último avance registrado» = fecha de la última captura guardada. Nunca es un inicio de sesión ni implica abandono.
- «Sin folio» cuando aún no existe una solicitud. **Nunca** se muestra el id interno del usuario.

### 7 bis.4 Zonas
- Toda pantalla **DEBE** obtener la zona de `App\Services\Zonas\ZonasAnfeca` y mostrar la denominación oficial («Zona 6 - Sur»).
- Cuando la zona no puede identificarse se muestra «Sin clasificar». Ya no se usa «Pendiente de clasificación».
- Las contradicciones se muestran con una advertencia.
- La clasificación visual **no** concede acceso; la autorización es independiente.

### 7 bis.5 Estados por dominio (no mezclar)
| Dominio | Ejemplos | Insignia |
|---|---|---|
| Administrativo (trámite) | Sin iniciar, En proceso sin solicitud, Borrador, Listo para envío, Enviada | `seg-*` |
| Documental: carga | Cargado, No cargado, Registrado sin archivo | `cargado`, `no-cargado`, `no-localizado` |
| Documental: revisión | Por revisar, Validado, Con observaciones, No válido, No aplica | `rev-*` |
| Evaluación (fase 6.4) | Se definirán con el instrumento oficial | — |

### 7 bis.6 Protección de información sensible
- Los documentos se sirven solo por rutas protegidas, con `Content-Disposition: inline`, `Cache-Control: private, no-store` y sin exponer rutas físicas.
- Nunca se mueven a `public/` ni se envían a terceros.
- No se consultan documentos de solicitudes no enviadas.
- Las tablas muestran solo los datos administrativos necesarios. CURP y domicilio aparecen únicamente en la ficha del expediente.

### 7 bis.7 Catálogos extensos (selector con búsqueda integrada) — 2026-11-25
Aplica a toda lista de más de ≈ 30 opciones (primer uso: títulos de posgrado del «Último grado de estudios»).
- **Componente único:** `resources/js/components/SelectorBuscable.vue`. No se usa un `<select>` nativo con cientos de opciones ni se incrustan catálogos completos en la página.
- **Datos:** el navegador recibe **solo** las opciones del filtro vigente (p. ej., nivel + área) desde un endpoint autenticado, limitado y con datos mínimos (`clave`, `título`). El servidor es la fuente única: valida la clave y determina el texto que se guarda.
- **Disparador:** ancho completo de su fila, 44–48 px, **máximo 2 líneas** visibles con «…»; el título completo en `title` y en la lista. No se limita por el ancho máximo de los `select` generales (80 % / 360 px).
- **Lista flotante:** `position: fixed` (no altera la altura del documento ni desplaza el formulario), ≈ 200 px con desplazamiento interno, se abre **hacia arriba** si no hay espacio abajo, nunca sale de la pantalla en móvil.
- **Búsqueda:** parcial, por palabras, sin acentos ni mayúsculas; orden alfabético por palabras (se ignora la puntuación).
- **Teclado y accesibilidad:** `role="combobox"`/`listbox`/`option`, `aria-expanded`, `aria-activedescendant`; ↑/↓, Inicio/Fin, RePág/AvPág, Intro, Escape; cierre al seleccionar, con Escape o con clic fuera; el foco vuelve al disparador.
- **Opción de escape:** cuando el catálogo puede no contener la respuesta, la última opción («No encuentro mi título») cambia **en el mismo espacio** a captura manual, con «Volver al catálogo» sin perder el texto no guardado. Una opción «Otro» o la captura manual **no** implican inelegibilidad.
- **Protección de cambios:** el componente escribe en campos con `name` y emite `input`, para que la advertencia de cambios pendientes funcione.
- **Bandera y degradación:** si el catálogo no está disponible (bandera, archivo, huella o migración), se muestra el formulario anterior con un aviso; nunca una lista vacía en silencio.
- **Verificación:** altura del documento con la lista abierta y cerrada, título más largo, apertura hacia arriba, móvil sin desplazamiento horizontal, sin errores de consola.

### 7 bis.8 Datos de catálogo versionados y funciones con bandera (2026-11-26)
- **El servidor determina el texto.** El navegador solo envía la clave; nombres, orígenes o versiones enviados por POST se ignoran.
- **Lo registrado no se reescribe.** Una selección guardada conserva su texto y su versión de origen. Si la versión vigente la retira o la cambia:
  - se muestra con un aviso;
  - guardar sin cambios la conserva;
  - nunca se borra ni se obliga a recapturar en silencio.
- **Presentación única.** Cada dato con varias fuentes (título específico, texto libre histórico, área) se muestra mediante **un** presentador de servidor (p. ej., `TituloUltimoGrado::presentar()`), no con reglas repetidas en cada vista. El **área** se muestra aparte del nombre.
- **Estados de una función con bandera.** `desactivado` · `activo` · `error`. En `error` se muestra el formulario anterior con un aviso visible y el motivo queda en el registro; nunca una lista vacía.
- **Mensajes en español.** Las reglas propias llevan mensajes explícitos. El sitio debe ejecutarse con `APP_LOCALE=es` y traducciones completas: los mensajes genéricos en inglés no son aceptables para el solicitante.
- **Los errores de sesión deben verse.** Toda pantalla destino de una redirección con `with('error', …)` debe mostrar `session('error')` con el aviso homologado. Un bloqueo sin explicación visible es un defecto.

## 8. Accesibilidad mínima
- Etiquetas `<label>` en todos los campos, `role="search"` en la barra de filtros y `aria-live="polite"` en resúmenes que cambian.
- Las acciones deshabilitadas usan `aria-disabled`. Los enlaces que abren pestaña nueva usan `rel="noopener"`.
- Los diálogos modales llevan foco inicial, cierre con Esc y retorno del foco.

## 9. Verificación antes de publicar
1. Capturas en 1366×768, 1920×1080, tableta y móvil.
2. Medición de altura cuando el cambio pretende compactar.
3. Pruebas automáticas de la regla (por ejemplo, numeración continua entre páginas y con filtros).
4. Sin errores de JavaScript en la consola.
5. Sin cambios en vistas, hojas ni componentes del módulo de solicitantes.

## 10. Estado de aplicación en el módulo de coordinación

| Regla | Estado |
|---|---|
| Encabezado, pie y layout comunes | Aplicado (6.2.3) |
| Fichas 50/50 de igual altura; componentes compactos | Aplicado (6.2.4) |
| «Núm.» en la bandeja de solicitudes | Aplicado: continuo entre páginas, filtros, orden y alcance |
| «Núm.» en «Solicitudes recientes» del tablero | Aplicado |
| Búsqueda, filtros y paginación en el servidor | Aplicado; se añadió «Mostrando x–y de N» |
| Estados con texto y color | Aplicado (insignias de carga y de solicitud) |
| Exportaciones | No existen aún (fase 6.6); esta norma aplica cuando se implementen |
| Visor documental modal | Aplicado (bloque C) |
| Seguimiento de etapas y avance; personas frente a trámites | Aplicado (bloque D) |
| Zonas homogéneas («Sin clasificar») | Aplicado (bloque B) |
| «Núm.» en bandeja de seguimiento, historial de revisiones y otros trámites | Aplicado |
| Catálogos extensos con `SelectorBuscable` | Aplicado en «Último grado de estudios» (módulo de solicitantes). **Activado en el ensayo 8086 el 2026-11-26** para aceptación (bandera `ANFECA_CATALOGO_POSGRADOS`); clasificación provisional |
