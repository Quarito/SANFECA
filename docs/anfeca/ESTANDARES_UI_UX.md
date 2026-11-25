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
