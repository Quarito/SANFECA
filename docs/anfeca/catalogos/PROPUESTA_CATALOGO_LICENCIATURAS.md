# Catálogo de licenciaturas (Formación Académica de Origen): investigación, catálogo preliminar y propuesta

**Fecha:** 2026-11-26 (Parte II el mismo día).

**Estado:**

| Aspecto | Estado |
|---|---|
| Investigación y catálogo | Hecho: ciclo 2025-2026 y consolidado 2000-2001 a 2025-2026 |
| Integración en Formación Académica | **Implementada y probada en aislamiento** (Parte II) |
| 8086 | **Sin cambios**: no desplegada, migración no aplicada, banderas no creadas en `.env` |
| Clasificación | **Preliminar**: 1 417 (ciclo actual) / 2 319 (consolidado) por revisar; nada aprobado |
| Equivalencias normativas | Sin cambios |

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

---

# Parte II · Integración en Formación Académica (etapa 1) — desarrollo aislado, 2026-11-26

## 18. Alcance y diseño conservado

**Solo dos escenarios:**
1. Solo licenciatura.
2. Licenciatura con posgrado.

No hay multigrado, segunda licenciatura, varios posgrados ni relaciones 1:N.

**Diseño validado conservado:**
- encabezado, menú lateral, pie, fichas 50/50, Institución de Procedencia, tarjetas documentales y botones.

**Únicos cambios visibles:**
- **Formación de origen:** el **mismo** campo «Nombre del Título» usa ahora `SelectorBuscable` en modo remoto. Tiene búsqueda parcial sin acentos ni mayúsculas y lista flotante de ≈ 200 px. La opción «No encuentro mi título» abre la captura manual en el mismo espacio. **No** hay desplegable de Área: el área se deriva internamente.
- **Último grado:** la casilla autorizada «Cuento con estudios de posgrado concluidos».
  - **Desmarcada** (escenario 1): se ocultan nivel, área, título y los documentos del posgrado. Se muestran dos líneas: «Su formación académica de origen corresponde a su último grado de estudios. / No es necesario registrar nuevamente el título ni la cédula profesional.»
  - **Marcada** (escenario 2): la interfaz de posgrados vigente, con nivel **solo** Especialidad, Maestría o Doctorado.

**Comparación de alturas** con la pantalla activa (`app-CYBBpN_p.js`, usuario de aceptación):

| Vista | 8086 actual | Desarrollo | Diferencia |
|---|---:|---:|---|
| Escritorio 1366 px | 974 px | 984 px | +10 (selector de 46 px frente a select de 36 px) |
| Tableta 820 px | 1 359 px | 1 376 px | +17 |
| Móvil 390 px | 2 271 px | 2 183 px | −88 (sin posgrado) |

Sin desplazamiento horizontal. La lista abierta **no** cambia la altura del documento.

**Controles visibles:**
- 8086: `nivel_origen_visible, titulo_origen, titulo_origen_archivo, cedula_origen_archivo, nivel_ultimo_grado, titulo_ultimo_grado, titulo_ultimo_grado_archivo, cedula_ultimo_grado_archivo, institucion_afiliada_id`.
- Desarrollo, escenario 1: los mismos de origen **+ `cuenta_con_posgrado_casilla`** (único control nuevo), sin los 4 del posgrado.
- Desarrollo, escenario 2: los 9 anteriores + la casilla.

## 19. Persistencia (PostgreSQL) — diagnóstico y columnas

- **Tablas revisadas:**
  - `formaciones_academicas_solicitud` (1 por solicitud; fuente de la formación del trámite);
  - `formaciones_academicas` (modelo antiguo sin uso en el flujo);
  - `historial_documentos_academicos` (versiones anteriores de los documentos del último grado);
  - `solicitudes_certificacion` (área de certificación);
  - `certificados_historicos` (antecedente: solo número, CURP y certificación; no guarda la licenciatura).
- **No** se crea ninguna tabla multigrado.

**Migración PROPUESTA** `2026_11_28_000000_add_titulo_origen_especifico_y_declaracion_posgrado`:
- aditiva y fuera de la ruta automática;
- 5 columnas nulas y 2 CHECK;
- las 4 columnas del catálogo de posgrados, **intactas**.

| Columna | Tipo | Justificación |
|---|---|---|
| `titulo_origen_especifico` | varchar(300) | Nombre exacto de la licenciatura. `titulo_origen` guarda el ÁREA y `titulo_origen_otro` solo admite «Otro» (≤ 200) |
| `origen_titulo_origen` | varchar(20) | `catalogo` o `manual`: distingue la selección verificada de la captura sujeta a revisión |
| `clave_licenciatura_catalogo` | varchar(40) | Clave estable `LIC-…`, solo para selecciones reales del catálogo |
| `version_catalogo_licenciaturas` | varchar(60) | Versión de origen; permite conservar lo registrado aunque el catálogo cambie |
| `cuenta_con_posgrado` | boolean NULL | Declaración explícita del escenario. **No** es derivable: `nivel_ultimo_grado` NULL también significa «histórico incompleto», y el valor «Licenciatura» del formulario anterior tiene otro significado. NULL = histórico o no declarado (no es un tercer escenario) |

**SQL equivalente (PostgreSQL):**

```sql
BEGIN;
ALTER TABLE public.formaciones_academicas_solicitud
  ADD COLUMN titulo_origen_especifico       varchar(300) NULL,
  ADD COLUMN origen_titulo_origen           varchar(20)  NULL,
  ADD COLUMN clave_licenciatura_catalogo    varchar(40)  NULL,
  ADD COLUMN version_catalogo_licenciaturas varchar(60)  NULL,
  ADD COLUMN cuenta_con_posgrado            boolean      NULL;
ALTER TABLE public.formaciones_academicas_solicitud
  ADD CONSTRAINT fas_titulo_origen_coherente_ck CHECK (
       (origen_titulo_origen IS NULL AND titulo_origen_especifico IS NULL
            AND clave_licenciatura_catalogo IS NULL AND version_catalogo_licenciaturas IS NULL)
    OR (coalesce(origen_titulo_origen, '') = 'catalogo' AND coalesce(btrim(titulo_origen_especifico), '') <> ''
            AND coalesce(clave_licenciatura_catalogo, '') ~ '^LIC-[0-9A-F]{12}$'
            AND coalesce(btrim(version_catalogo_licenciaturas), '') <> '')
    OR (coalesce(origen_titulo_origen, '') = 'manual' AND coalesce(btrim(titulo_origen_especifico), '') <> ''
            AND clave_licenciatura_catalogo IS NULL AND version_catalogo_licenciaturas IS NULL));
ALTER TABLE public.formaciones_academicas_solicitud
  ADD CONSTRAINT fas_declaracion_posgrado_ck CHECK (
       cuenta_con_posgrado IS DISTINCT FROM true
    OR (coalesce(nivel_ultimo_grado, '') IN ('Especialidad', 'Maestría', 'Doctorado')
            AND coalesce(btrim(titulo_ultimo_grado), '') <> ''));
COMMIT;
```

- **Impacto:** las filas existentes no cambian (columnas nuevas en NULL) y no hay actualizaciones retrospectivas.
- **Recuperación estructural:** `down()` se **niega** si alguna fila tiene datos en las 5 columnas.

**Procedimientos** (probados en PostgreSQL desechable, 18/18):

```sh
bash scripts/anfeca/respaldo_verificado.sh bd_anfeca_ensayo_pruebas_pg desarrollo/modulo-coordinacion <commit>
ANFECA_AUTORIZO_MIGRACION=licenciaturas_posgrado ANFECA_URL_DOCUMENTOS=http://192.168.7.130:8086 \
  bash scripts/anfeca/aplicar_licenciaturas_posgrado.sh bd_anfeca_ensayo_pruebas_pg <commit> <respaldo>
bash scripts/anfeca/verificar_licenciaturas_posgrado.sh
# Solo con autorización y columnas vacías:
ANFECA_AUTORIZO_REVERSION=licenciaturas_posgrado … bash scripts/anfeca/revertir_licenciaturas_posgrado.sh <base> <commit> <respaldo>
```

El catálogo de posgrados puede seguir **activo** durante la aplicación. Se exige que las banderas de licenciaturas y de declaración estén desactivadas.

## 20. Almacenamiento — Escenario 1: solo licenciatura

Ejemplo: «Ingeniería en Computación», certificación en Informática Administrativa.

| Campo | Valor físico | Observación |
|---|---|---|
| `nivel_origen` | `Licenciatura` | Fijo |
| `titulo_origen` | `Informática` | Área derivada de la clasificación del catálogo (compatible con la certificación) |
| `titulo_origen_otro` | NULL | Solo se usa con el área «Otro» (refleja el nombre exacto) |
| `titulo_origen_especifico` | `INGENIERÍA EN COMPUTACIÓN` | Nombre exacto **del catálogo del servidor** |
| `origen_titulo_origen` · `clave_licenciatura_catalogo` · `version_catalogo_licenciaturas` | `catalogo` · `LIC-…` · `ANUIES-2000-2026-LIC-PRELIMINAR` | — |
| `cuenta_con_posgrado` | **false** | Declaración explícita |
| `nivel_ultimo_grado`, `titulo_ultimo_grado`, `titulo_ultimo_grado_otro`, las 4 columnas de posgrado, `titulo_ultimo_grado_archivo`, `cedula_ultimo_grado_archivo` | **NULL** | No se duplica la licenciatura ni se crean referencias documentales ficticias |
| `titulo_origen_archivo`, `cedula_origen_archivo` | rutas reales | Los **únicos** 2 archivos académicos (prueba: 2 archivos físicos) |

**Derivado en consultas** (`RequisitosFormacion`):
- máximo nivel = **Licenciatura**;
- requisitos = solo los de origen;
- documentos del último grado = «no aplica» (sin revisión documental duplicada);
- el acuse y el expediente muestran «Último grado · Licenciatura — corresponde a la formación de origen».

## 21. Almacenamiento — Escenario 2: licenciatura con posgrado

Ejemplo: «Ingeniería en Computación» y «Maestría en Administración de Organizaciones».

| Campo | Valor físico |
|---|---|
| Origen | Igual que en el escenario 1 (`titulo_origen`, nombre exacto, `catalogo`, clave `LIC-…` y versión) |
| `cuenta_con_posgrado` | **true** |
| `nivel_ultimo_grado` | `Maestría` (solo Especialidad, Maestría o Doctorado: validación y CHECK) |
| `titulo_ultimo_grado` | `Administración` (área) |
| `titulo_ultimo_grado_especifico` · `origen_titulo_ultimo_grado` · `clave_posgrado_catalogo` · `version_catalogo_posgrados` | `MAESTRÍA EN ADMINISTRACIÓN DE LAS ORGANIZACIONES` · `catalogo` · `MAE-…` · `ANUIES-2025` |
| Documentos | Título y cédula de licenciatura y título y cédula del último grado (4 archivos) |

## 22. Fuente única de verdad

| Concepto | Fuente | Nunca |
|---|---|---|
| Área de certificación | `solicitudes_certificacion.area_certificacion` | Se modifica desde Formación |
| Clasificación académica de la licenciatura | Catálogo (clave + versión); se registra en `titulo_origen` solo si es compatible | Se registra un área distinta para «satisfacer» la validación |
| Nombre exacto | `titulo_origen_especifico` / `titulo_ultimo_grado_especifico` | Lo envía el navegador |
| Declaración de posgrado | `cuenta_con_posgrado` | Se infiere de registros históricos |
| Último grado | Escenario 1: derivado (licenciatura); escenario 2: columnas de último grado | Se copia la licenciatura |
| Documentos | Columnas de archivos e historial | Se copian ni se crean ficticios |

## 23. Validaciones (Laravel y PostgreSQL)

- **Ambos escenarios:**
  - formación de origen con título del catálogo (clave existente y versión vigente) o manual (5–200 caracteres, sin `<` ni `>`);
  - `titulo_origen` y `titulo_origen_otro` **prohibidos** desde el navegador.
- **Escenario 1:**
  - `cuenta_con_posgrado = 0`;
  - **cualquier** dato o archivo de posgrado: `prohibited`;
  - solo 2 documentos exigidos.
- **Escenario 2:**
  - `cuenta_con_posgrado = 1`;
  - `nivel_ultimo_grado ∈ {Especialidad, Maestría, Doctorado}` («Licenciatura» rechazada en el servidor y por el CHECK);
  - catálogo de posgrados vigente (clave, nivel, área y versión) o captura manual;
  - 4 documentos.
- **Requisito normativo conservado (DOC-02 / ELE):** la certificación en «Disciplinas vinculadas al ámbito económico-administrativo» **exige posgrado** (Reglamento vigente, arts. 1 y 13; Instrumentos 2026, factor 1.1). El escenario 1 se rechaza con un mensaje comprensible y la pantalla lo avisa. Para las demás áreas, la convocatoria 2026 (base I.4) pide el posgrado «en caso de poseerlo».
- **Cambio de casilla con datos guardados:**
  - se pide confirmación;
  - los datos y documentos de posgrado **se conservan** sin ser vigentes;
  - al volver a marcar se recuperan.
- **Formación autorizada o solicitud enviada:** sin cambios (bloqueo vigente).
- **Registros sin declaración:** la casilla aparece desmarcada en registros nuevos, o marcada visualmente si ya tienen posgrado. No se escribe nada hasta que el solicitante guarda.

## 24. Decisión pendiente D-LIC-1 (detención autorizada en la instrucción 27)

Un título del catálogo clasificado de forma **preliminar** en un área distinta de la que admite la certificación (p. ej., «Contaduría Fiscal Pública» con certificación en Administración, o «Derecho» con certificación en Contaduría Pública):
- **hoy** se detiene, con la explicación y la sugerencia de revisar la certificación o usar «No encuentro mi título»;
- **no** se modifica la regla vigente (`config/anfeca_equivalencias.php`).

| Opción | Efecto |
|---|---|
| A (vigente) | Detener con explicación; la captura manual queda sujeta a revisión de procedencia |
| B | Aceptar y marcar para revisión de procedencia (exige cambiar el significado de `titulo_origen` o agregar una columna de procedencia) |
| C | Relajar la regla 1:1 área ↔ certificación (cambio normativo) |

Requiere la decisión de la CNCA, ligada también a la aprobación de la clasificación (las 1 417 pendientes).

**Captura manual:** no existe clasificación de catálogo y no se inventa. Se registra el área que admite la certificación (igual que el selector vigente de una sola opción) con `origen = manual`, lo que la deja sujeta a revisión.

## 25. Refrendos

- Con título de origen histórico registrado en el trámite de refrendo, el bloque de origen **no cambia** (regla vigente: `titulo_origen` prohibido).
- En un refrendo **sin** formación previa, el catálogo se usa igual que en primera vez.
- El antecedente (`certificados_historicos`) no guarda la licenciatura y **no se modifica**.
- **Propuesta (no implementada):** permitir **completar** el nombre exacto una sola vez cuando la solicitud de refrendo tenga solo el área, sin cambiar el área histórica.

## 26. Banderas y recuperación

| Bandera | Por omisión | Rige |
|---|---|---|
| `ANFECA_CATALOGO_POSGRADOS` | true en 8086 | Catálogo de posgrados (sin cambios) |
| `ANFECA_CATALOGO_LICENCIATURAS` | **false** | Selector de licenciaturas en el origen |
| `ANFECA_DECLARACION_POSGRADO` | **false** | Casilla de posgrado |

Dos banderas independientes: el catálogo de licenciaturas depende de la aprobación de la clasificación; la casilla depende solo de DOC-02, ya decidido.

1. **Funcional (principal):** desactivar las banderas (probado con datos de ambos escenarios, 8/8):
   - el origen vuelve al select anterior y protege el título registrado (no se puede cambiar el área);
   - la **declaración guardada sigue rigiendo**, así que el solicitante no tiene que duplicar documentos;
   - se conservan nombres, claves, versiones, declaración, documentos e historial;
   - el catálogo de posgrados sigue funcionando.
2. **Código:** `git revert --no-edit` de los commits de **interfaz**, del más reciente al más antiguo (`git log --format=%h <primer commit de interfaz>^..HEAD -- resources/js resources/views/registro/formacion-academica.blade.php`), conservando los commits del servidor.
   - Si la interfaz anterior no envía la casilla, rige la declaración guardada.
   - Probado: revertir solo dos commits intermedios **entra en conflicto** si hay commits de interfaz posteriores.
   - No usar `reset --hard`.
3. **Compilación:** `bash scripts/anfeca/desplegar_compilacion.sh restaurar <manifest_anterior.json>` (probado en aislamiento; rechaza manifiestos inexistentes).
4. **Estructural:** `revertir_licenciaturas_posgrado.sh` solo con autorización y columnas vacías.
   - **Nunca** `down()` con datos.
   - **Nunca** una restauración automática de PostgreSQL.

## 27. Catálogo consolidado (2000-2001 a 2025-2026)

- **Fuente:** 25 ciclos históricos más el actual, descargados de uno en uno (agrupados por nivel, programa y campos; sin detalle institucional).
- **Volumen:** 23 MB en `~/ANFECA_FUENTES/licenciaturas/historico/`, con `descargas.ndjson` (huellas por ciclo).

| Área | Denominaciones | Vigentes 2025-2026 | Solo históricas | Requieren revisión |
|---|---:|---:|---:|---:|
| Administración | 1 110 | 768 | 342 | 1 013 |
| Contaduría | 211 | 122 | 89 | 112 |
| Informática | 589 | 367 | 222 | 232 |
| Negocios Internacionales | 199 | 114 | 85 | 143 |
| Otro | 7 277 | 3 900 | 3 377 | 819 |
| **Total** | **9 386** | 5 271 | 4 115 | 2 319 |

- Se conservan las 5 271 claves del ciclo actual; sin colisiones.
- 2 718 denominaciones solo tienen el nombre en la fuente (sin campo de formación).
- Archivos: `licenciaturas_anuies_consolidado.json` (SHA-256 `7bc8c998…`), matriz consolidada y manifiesto (privados).

**Resúmenes por familia** (`resumir_revision.py`; matrices sin cambios):

| Catálogo | Pendientes | Grupos | Subgrupos homogéneos | Aprobación en bloque posible |
|---|---:|---:|---:|---|
| Licenciaturas 2025-2026 | 1 417 | 80 | 134 | 46 subgrupos ≥ 5 cubren 1 276 (90 %) |
| Licenciaturas consolidado | 2 319 | 111 | 189 | 61 subgrupos ≥ 5 cubren 2 092 |
| Posgrados | 572 | 68 | 107 | 30 subgrupos ≥ 5 cubren 444 |

## 28. PDM (apagado; sin cambios en PowerDesigner)

- `anfeca_compat` y `EquivalenciaPdm` desconocen las 5 columnas.
- En modo PDM, el nombre exacto, la clave y la declaración no se guardarían.
- Ampliación pendiente **antes** de activar el PDM (igual que CAT-09).

## 29. Pruebas

| Prueba | Resultado |
|---|---|
| `FormacionLicenciaturaPosgradoTest` (13 pruebas, catálogo ficticio): escenarios 1 y 2 en los 3 niveles; Derecho y «Otro»; captura manual; discrepancia de área; 13 negativas; cambio de casilla; autorizada y enviada; endpoint; catálogo ausente; rollback por banderas; refrendo; sin migración; reversión con datos | Correcto |
| `FormacionLicenciaturaMigracionPgTest` (PostgreSQL 18.6 desechable) | Correcto |
| Regresión completa | SQLite 195 pruebas con PHP 8.4 y 8.5 (1 omitida preexistente); PostgreSQL de revisión 22/22; PDM 69/69 |
| `probar_migracion_licenciaturas.sh` | 18/18 |
| `probar_migracion_catalogo.sh` (actualizado) | 15/15 |
| Verificación visual (25 comprobaciones) | Correcto |
| Rollback funcional (8) | Correcto |
| Recuperación de compilación en aislamiento | Correcto |

Evidencias en `~/ANFECA_EVIDENCIAS/formacion_licenciaturas_posgrado_20261126/`.

