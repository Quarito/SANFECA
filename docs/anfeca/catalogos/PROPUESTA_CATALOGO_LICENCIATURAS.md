# Catálogo de licenciaturas (Formación Académica de Origen): investigación, catálogo preliminar y propuesta

**Fecha:** 2026-11-26.

**Estado: INVESTIGACIÓN Y CATÁLOGO PRELIMINAR.**
- No integrado ni instalado en 8086.
- Sin migraciones.
- Sin cambios en las equivalencias.
- Bandera propuesta `ANFECA_CATALOGO_LICENCIATURAS=false` (no creada).

> La clasificación por área es **administrativa y preliminar**. No es una equivalencia normativa ni un criterio de elegibilidad. «Otro» **no** significa «no elegible». La formación académica real se mantiene separada del área de certificación solicitada.

## 1. Fuentes consultadas

| Fuente | Resultado |
|---|---|
| **Anuario Estadístico ANUIES** — https://anuario.anuies.mx/ | ✔ **Fuente principal.** Consulta pública con filtros por ciclo (2000-2001 a 2025-2026), nivel, campos CMPE (amplio, específico, detallado y unitario), institución, escuela, modalidad y programa. La exportación de la página usa el servicio `POST action=exportReportRows` (NDJSON); el Excel se arma en el navegador |
| Anuario histórico — https://anuario.anuies.mx/historico.php | ✔ Consulta por **rango** de ciclos (acción `historico`). Para obtener denominaciones por ciclo basta el selector de ciclo del Anuario principal, que ya ofrece los 26 ciclos con la dimensión de programa (verificado: 2005-2006 → 2154 filas; 2015-2016 → 3230 filas) |
| ANUIES — https://www.anuies.mx/ | Consultada (accesible); remite al Anuario. No ofrece un catálogo descargable de licenciaturas distinto |
| ANFECA — https://www.anfeca.unam.mx/c_certificacion_academica.php | Consultada. **No** publica una lista de licenciaturas aceptadas; solo las cinco áreas de certificación y los documentos normativos ya inventariados |
| SEP · SIRVOES — https://sirvoes.sep.gob.mx/ | ✘ **Sin respuesta** desde el servidor (tiempo agotado, 25 s). No se intentó por otras vías. Complemento recomendado para los RVOE de particulares, mediante consulta manual del responsable |
| Documentos normativos ANFECA 2026 (inventario D01–D18) | Revisados (§11). **No existen documentos 2027** publicados en el inventario |

No se usaron portales comerciales ni catálogos de terceros. No se inventaron enlaces.

## 2. URL y método de descarga

- **URL:** https://anuario.anuies.mx/ (POST con los mismos parámetros que envía la interfaz).
- **Script reproducible:** `scripts/anfeca/catalogos/descargar_anuario_licenciaturas.py`. Hace una solicitud por ejecución; no sobrescribe; exige ≥ 5 GB libres; valida el cierre `{"type":"done","rows":N}`.
- **Reproducibilidad:** una segunda descarga del mismo ciclo produjo un archivo **idéntico byte a byte**.
- **Filtros:**
  - niveles «LICENCIATURA UNIVERSITARIA Y TECNOLÓGICA» y «LICENCIATURA EN EDUCACIÓN NORMAL»;
  - sin filtro (todo) en entidades, municipios, sostenimiento, subsistema, asociación a ANUIES, modalidad y campos;
  - variables: matrícula, nuevo ingreso, egresados y titulados;
  - **excluidos** por nivel: TSU, especialidad, maestría y doctorado.
- **Ciclo:** **2025-2026**, el más reciente; se obtuvo directamente.

## 3. Archivos obtenidos (privados, en `~/ANFECA_FUENTES/licenciaturas/`, permisos 600)

| Archivo | Bytes | SHA-256 | Filas |
|---|---:|---|---:|
| `anuies_exportReportRows_2025-2026_programa_nivel_campos.json` (original) | 1 807 475 | `1514672dc721066de168c4ea08233c6e59d605b9cf7def51d27d72f6e90fbe39` | 5 330 |
| `anuies_exportReportRows_2025-2026_institucion_programa_nivel_campos.json` (original) | 10 621 717 | `242fe5dbd25a9b677ee1a9440d8dc2006f158554a590c75cd9672fec83520ccc` | 27 269 |
| `licenciaturas_anuies_2025-2026.json` (derivado) | 2 158 750 | `491c4e4f108b8e6934a6859cea8c9d3b8af867b4e3230b765c75762b7a43fc76` | 5 271 títulos |
| `matriz_clasificacion_licenciaturas.xlsx` (derivado) | ≈ 3.1 MB | ver `manifiesto_licenciaturas.json` | — |
| `manifiesto_licenciaturas.json` y `REGISTRO_FUENTE.md` | — | — | — |

- **Fecha de consulta y descarga:** 2026-11-26, 16:58 (hora del centro de México).
- **Condiciones:** «© ANUIES 2025 — Todos los derechos reservados». Uso interno; **no se publica** en GitHub.
- **Espacio:** 5 214 MB libres antes y ≈ 5 197 MB después (≈ 17 MB entre originales y derivados).

## 4. Conteos

| Concepto | Valor |
|---|---:|
| Filas de origen agrupadas (nivel × campos × carrera) | 5 330 (5 259 universitaria y tecnológica · 71 normal) |
| Filas de origen por institución | 27 269 |
| Denominaciones exactas distintas | 5 288 |
| **Denominaciones únicas** (sin acentos, mayúsculas, puntuación ni espacios extremos) | **5 271** |
| Variantes de escritura agrupadas | 17 (conservadas como «variantes» en Observaciones) |
| Denominaciones sin matrícula ni egresos en el ciclo (conservadas) | 834 |
| Denominaciones con **conflicto de campos** ANUIES (más de una combinación) | 26 |

Nunca se fusionan denominaciones distintas. Por ejemplo, Ingeniería en Computación, Ingeniería en Sistemas Computacionales, Ingeniería en Tecnologías de la Información, Licenciatura en Informática y Licenciatura en Informática Administrativa se conservan por separado.

## 5. Clasificación administrativa preliminar (reglas `lic-reglas-1.2`)

| Área propuesta | Denominaciones | Alta | Media | Baja | Requieren revisión |
|---|---:|---:|---:|---:|---:|
| Administración | 768 | 83 | 408 | 277 | 685 |
| Contaduría | 122 | 78 | 21 | 23 | 44 |
| Informática | 367 | 273 | 68 | 26 | 94 |
| Negocios Internacionales | 114 | 45 | 33 | 36 | 69 |
| Otro | 3 900 | 3 375 | 493 | 32 | 525 |
| **Total** | **5 271** | 3 854 | 1 023 | 394 | **1 417** |

Cifras del catálogo final (reglas 1.2), idénticas a la hoja «Resumen» de la matriz.

**Método:** el **nombre** es la señal principal y el **campo detallado ANUIES** sirve de contexto y desempate.

**Reglas explícitas:**
- Contaduría, contabilidad y auditoría → Contaduría.
- Informática administrativa, computación, sistemas computacionales, TI y software → Informática.
- Negocios internacionales, comercio internacional y comercio exterior → Negocios Internacionales.
- Administración general o de empresas → Administración.

**Restricciones solicitadas, verificadas con pruebas (20/20, `probar_clasificacion_licenciaturas.py`):**
- **No** todas las ingenierías van a Informática: civil, industrial, mecatrónica, etc. → Otro. Robótica y sistemas embebidos «computacionales» → revisión.
- **No** todo «Administración y negocios» es Administración segura: mercadotecnia, turística, pública y logística → revisión.
- **No** se asignan Finanzas, Economía ni Actuaría a Contaduría: Finanzas → Administración (baja, alternativa Contaduría); Economía y Actuaría → Otro con revisión.
- Derecho, Psicología, Pedagogía, Sociología, Ciencias de la comunicación, Medicina, etc. → Otro.

**Casos para revisión (1 417), por tema:**

| Tema | Denominaciones |
|---|---:|
| Administración turística | 200 |
| Mercadotecnia | 175 |
| Finanzas | 103 |
| Ingeniería industrial o en gestión empresarial | 101 |
| Logística | 82 |
| Economía | 39 |
| Datos o inteligencia artificial | 39 |
| Administración pública | 34 |
| Derecho fiscal | 6 |
| Administración en otro dominio | 6 |
| Actuaría | 4 |

El resto son conflictos entre el nombre y el campo, conflictos de campo ANUIES o señales débiles.

**«Otro» con relación razonable a un área base:** 395 denominaciones (alternativa Administración 320, Negocios Internacionales 64, Contaduría 9, Informática 2).

## 6. Matriz de clasificación

Archivo: `~/ANFECA_FUENTES/licenciaturas/matriz_clasificacion_licenciaturas.xlsx` (privado).

**Hojas:**
- Resumen;
- Matriz completa;
- una por área: Administración, Contaduría, Informática, Negocios Internacionales y Otro;
- Casos ambiguos;
- Equivalencias actuales;
- Fuentes;
- Registros por institución (27 269 filas; trazabilidad).

**Columnas:** las 18 solicitadas (desde «Núm.» hasta «Referencia al registro original»), más instituciones, matrícula, egresados, titulados y el criterio aplicado. **«Clasificación autorizada» se entrega vacía.**

## 7. Catálogo JSON preliminar

`licenciaturas_anuies_2025-2026.json`:
- versión `ANUIES-2025-2026-LIC-PRELIMINAR`;
- por título: `clave` (`LIC-` + 12 hexadecimales del SHA-256 del nombre normalizado), `titulo` (original), `niveles`, `area`, `confianza`, `requiere_revision` y `campos`.

Mismo formato y validaciones que el catálogo de posgrados. **No instalado.**

## 8. Comparación con las equivalencias actuales

`config/anfeca_equivalencias.php` (sin cambios) asocia cada área de certificación con **un** valor del campo `titulo_origen`:

| Área de certificación | Área del título admitida hoy | Denominaciones ANUIES propuestas en esa área |
|---|---|---:|
| Contaduría Pública | Contaduría | 122 |
| Administración | Administración | 768 |
| Informática Administrativa | Informática | 367 |
| Negocios Internacionales | Negocios Internacionales | 114 |
| Disciplinas vinculadas al ámbito económico-administrativo | Otro (y nombre libre en `titulo_origen_otro`) | 3 900 |

**Hallazgos:**
- **Títulos existentes en el sistema.** Hoy no existe ninguna denominación de licenciatura: solo cinco **áreas**. El nombre exacto se captura únicamente como texto libre cuando el área es «Otro» y la certificación es «Disciplinas vinculadas».
- **Respaldados por ANUIES.** 5 271 denominaciones reales del ciclo 2025-2026.
- **Respaldados por la normativa ANFECA.** Ninguno por denominación. La normativa solo define las cinco áreas (§11).
- **Restricción potencialmente rígida.** La regla 1:1 (área del título = área de certificación) se apoya en la convocatoria («certificación … en alguna de las licenciaturas convocadas»). En cambio, licenciaturas interdisciplinarias (Contaduría y Finanzas, Administración y Mercadotecnia, Informática Administrativa frente a Ingeniería en Sistemas) pueden no encajar en una sola área. **Revisión institucional** (CNCA).
- **Riesgo si se integra el catálogo.** Si la lista de títulos se filtrara solo por el área propuesta, la clasificación preliminar se convertiría en un filtro de facto. **Mitigación propuesta:** §9 (búsqueda en todas las áreas, sin bloqueo).

## 9. Propuesta de integración visual (etapa 1; NO implementada)

**Ficha «Formación Académica de Origen»** (izquierda, 50 %):
- **Fila 1:** «Nivel» fijo en *Licenciatura* (solo lectura) y «Área académica», 50/50.
- **Fila 2:** «Nombre del título» al 100 % con `SelectorBuscable.vue` (44–48 px, 2 líneas, búsqueda sin acentos, lista flotante de 200 px).
  - La lista muestra primero los títulos del área elegida y ofrece «Buscar en todas las áreas».
  - Elegir un título de otra área **no bloquea**: se guarda y se marca para revisión. Así la clasificación preliminar no actúa como validación normativa.
  - La opción «No encuentro mi título» abre la captura manual en el mismo espacio (títulos históricos, extranjeros o no localizados).
- Título profesional y cédula profesional: documentos sin cambios.
- La regla vigente área ↔ certificación (`anfeca_equivalencias`) se conserva; no se modifica en esta etapa.

**Ficha «Último Grado de Estudios»** (derecha, 50 %):
- Casilla «Cuento con estudios de posgrado concluidos».
  - **Marcada:** nivel (Especialidad, Maestría o Doctorado), área y título con el catálogo de posgrados ya activo, más sus documentos.
  - **Sin marcar:** la licenciatura **es** el último grado. No se duplica la captura ni se piden de nuevo los documentos de licenciatura.
- **Decisión pendiente (DOC-02):** la convocatoria 2026 pide el posgrado «en caso de poseerlo» (base I.4), pero la decisión de la etapa 1 exige título y cédula del último grado para todos. La casilla aplica la convocatoria. **No se elimina ningún requisito sin esa decisión expresa.**
- Compactación: la casilla ocupa una línea; con ella sin marcar, la ficha **reduce** su altura.

## 10. Diagnóstico de persistencia y alternativas

**Campos actuales de origen:**

| Campo | Contenido |
|---|---|
| `nivel_origen` | Solo «Licenciatura» |
| `titulo_origen` | **Área**, validada contra el área de certificación |
| `titulo_origen_otro` | Texto libre, solo con «Disciplinas vinculadas» + «Otro», hasta 200 caracteres |

En **refrendo**, `titulo_origen` y `_otro` quedan `prohibited` una vez registrados (se conserva el histórico).

| Alt. | Descripción | Evaluación |
|---|---|---|
| **A (recomendada)** | Columnas **aditivas** nulas: `titulo_origen_especifico` varchar(300), `origen_titulo_origen` (`catalogo`/`manual`), `clave_licenciatura_catalogo` varchar(40), `version_catalogo_licenciaturas` varchar(60), con un CHECK de coherencia (con `coalesce`), igual que en posgrados; el área sigue en `titulo_origen` | Conserva todo; reversible por bandera; mismo patrón ya probado y aplicado |
| B | Guardar el nombre en `titulo_origen_otro` | ✘ Solo se admite con «Otro» y el controlador lo borra en los demás casos |
| C | Guardar el nombre en `titulo_origen` | ✘ Rompe la regla de equivalencias y los datos históricos |
| D | Tabla 1:N de grados | ✘ Es la etapa 2 (multigrado); fuera de alcance |

- **Las cuatro columnas del catálogo de posgrados se conservan íntegras.**
- **Refrendo:** propuesta de **completar** el nombre específico una sola vez si está vacío, sin cambiar el área histórica. Decisión pendiente.

## 11. Fundamento normativo (documentos 2026; no hay documentos 2027 publicados)

| Documento | Página / apartado | Contenido |
|---|---|---|
| D01 Convocatoria 2026 PTC/MT | p. 1, encabezado | Certificación en Administración, Contaduría Pública, Informática Administrativa, Negocios Internacionales y Disciplinas vinculadas al ámbito económico-administrativo |
| D01 | p. 3, Bases I.1 | Refrendo: certificación previa «en alguna de las licenciaturas convocadas» |
| D01 | p. 3, Bases I.3 | «Título de Licenciatura… expedido por una Institución de Educación Superior y cédula profesional» (**sin** lista de licenciaturas) |
| D01 | p. 3, Bases I.4 | Posgrado «en un área de las ciencias económico administrativas*» |
| D02 Convocatoria 2026 PAES | p. 1–3 | Solo «Disciplinas vinculadas»; mismo requisito de licenciatura |
| D06 Glosario 2026 | p. 9, «Requisitos de procedencia» 1 | «Que la licenciatura o posgrado acreditados en el Título… sea de las aceptadas en la convocatoria respectiva» |
| D07 Reglamento vigente | p. 1 (título), p. 3 (art. 5) | Certificación en las cinco áreas |
| D03 Instrumento Primera vez 2026 | p. 5, 1.1 | Posgrado: identificado con la disciplina o con el área educativa; para otras profesiones, «posgrado en áreas afines a las licenciaturas que se imparten en las escuelas de negocios» |

**Conclusión:** **no existe una lista oficial de licenciaturas aceptadas ni equivalencias por denominación.** La clasificación de este catálogo es una propuesta administrativa; la procedencia corresponde a la CNCA y a las comisiones.

## 12. Compatibilidad histórica y PDM

- **Registros históricos:** sin cambios; las columnas nuevas quedarían nulas. La presentación usaría un presentador central (pendiente CAT-07), con este orden: título específico, `_otro` y área.
- **PDM (apagado):** `anfeca_compat` y `EquivalenciaPdm` mapean `titulo_origen` y `_otro`. Las columnas nuevas requerirían la misma extensión pendiente que en posgrados (CAT-09) **antes** de activar el PDM.

## 13. Estrategia de recuperación (rollback)

Idéntica a la de posgrados:
1. Bandera `ANFECA_CATALOGO_LICENCIATURAS=false`: vuelve al formulario anterior y se conservan los datos.
2. Reversión de código conservando la persistencia.
3. Base de datos solo con autorización, con `down()` que se niega con datos.

Se reutilizan los scripts de aplicación, verificación y despliegue de la compilación.

## 14. Denominaciones históricas (estrategia; no ejecutada)

1. **Prioridad:** el ciclo más reciente (hecho).
2. **Siguiente paso** (con autorización): descargar, **de uno en uno**, los ciclos 2024-2025 a 2000-2001 con el mismo script. Solo la variante agrupada, sin institución: ≈ 1–1.8 MB y 2 000–5 300 filas por ciclo; ≈ 25 MB en total.
   - Comprobar el espacio antes de cada ciclo.
   - Fusionar por nombre normalizado y conservar «primer ciclo» y «último ciclo» en que aparece cada denominación.
3. **Taxonomía:** los ciclos antiguos pueden usar otra versión de los campos de formación. La clasificación de las denominaciones históricas usará sobre todo el nombre y marcará para revisión las que solo tengan campos antiguos.
4. La **captura manual** se conserva para títulos históricos no reportados, extranjeros o de instituciones ya inexistentes.

## 15. Etapa 2 (fuera de alcance; registrado)

- Captura multigrado: varias licenciaturas y varios posgrados.
- Relaciones 1:N y documentación individual por grado adicional.
- Menú flotante «Otros grados (N)»: lista flotante que **no incrementa la altura** de la pantalla (mismo patrón de `SelectorBuscable`).

## 16. Limitaciones

- Solo denominaciones **reportadas** al formato 911 en 2025-2026. Faltan las históricas (§14) y las no reportadas.
- SIRVOES no respondió.
- 1 417 clasificaciones requieren revisión; la columna «Clasificación autorizada» está vacía.
- Variantes ortográficas de la fuente (p. ej., «ADIMINSITRACIÓN») se conservan tal como aparecen, sin corregir.

## 17. Decisiones pendientes

1. Aprobar la clasificación preliminar, por bloques o individualmente.
2. Autorizar la descarga de los ciclos históricos (§14).
3. Elegir la alternativa de persistencia (A recomendada) y autorizar su migración.
4. Aprobar el diseño de §9, incluida la búsqueda en todas las áreas sin bloqueo.
5. Decidir sobre DOC-02: casilla de posgrado y documentos del último grado.
6. Revisión institucional de la regla 1:1 área ↔ certificación para licenciaturas interdisciplinarias.
7. Tratamiento en refrendos (completar el nombre específico sin cambiar el área histórica).
