# Análisis comparativo del PDM (`anfeca_modelo`) previo a la fase 6.4

**Solo análisis.** No se implementó ninguna estructura ni se activó el PDM.
Fuente: `database/sql/anfeca_modelo/anfeca_modelo.sql` y extensiones. Estado al 2026-11-24: las tablas
existen en `bd_anfeca_ensayo_pruebas_pg` y están **vacías**.

## 1. Tablas del PDM relacionadas

| Tabla PDM | Columnas clave | Relaciones | Equivalente actual en Laravel (`public`) |
|---|---|---|---|
| `EVALUACION` | `EVAL_ID_SOLICITUD` NN, `EVAL_PUNTUACION` (int, nulo), fecha y hora NN | → `SOLICITUD` | Ninguno |
| `DICTAMEN` | `DICT_ID_TIPO_DICTAMEN` NN, `DICT_ID_EVALUACION`, `DICT_ENCABEZADO` y `DICT_FOLIO_DICTAMEN` (varchar 9) NN, fecha y hora de creación NN, última impresión | → `TIPO_DICTAMEN`, → `EVALUACION` | Ninguno |
| `TIPO_DICTAMEN` | `TIDI_DESCRIPCION` (120), `TIDI_TIPO_DICTAMEN_ESTADO` (bool) | — | Ninguno (catálogo vacío) |
| `ACTA` | `ACTA_ID_SOLICITUD`, `ACTA_COORDINADOR`, `ACTA_SECRETARIO` y `ACTA_SEDE` (texto libre 1024) | → `SOLICITUD` | Ninguno |
| `CERTIFICADO` | `CERT_ID_EVALUACION` NN, `CERT_FOLIO` (100) NN, `CERT_ANIO` (15) NN, `CERT_ESTADO` (5) | → `EVALUACION` | `certificados_historicos` solo para antecedentes importados |
| `REFRENDO_CERTIFICADO` | `RECE_ID_CERTIFICADO` NN, `RECE_ID_EVALUACION`, número y año de refrendo NN | → `CERTIFICADO`, → `EVALUACION` | `certificados_historicos.refrendo_1..7` (texto) |
| `CONVOCATORIA` | Tipo NN, nombre, monto (`MONEY`), 4 fechas NN, **`CONV_PUNTUACION_MAX` y `CONV_PUNTUACION_MIN` NN**, lugar y evento | → `TIPO_CONVOCATORIA`; `SOLICITUD.SOLI_ID_CONVOCATORIA` (nulo) | Ninguno; la solicitud no tiene convocatoria |
| `TIPO_CONVOCATORIA` | Tipo, estado y siglas | — | `solicitudes_certificacion.certificacion` (ANFECA/CACSLA/CACECA, decisión D-7) |
| `ROL`, `PRIVILEGIO`, `ROL_PRIVILEGIO` | Nombres | `ROL_PRIVILEGIO` → `ROL` y `PRIVILEGIO` | `roles`, `privilegios` y `rol_privilegio` (vacías); la lógica usa `users.rol` |
| `USUARIO` | `USUA_ID_PERSONA` NN, `USUA_ID_ROL` NN, contraseña y estatus | → `PERSONA`, → `ROL` | `users` (`rol` en texto) |
| `ZONA`, `COORDINADOR_ZONA` (extensión) | Siglas, descripción; zona–usuario | → `USUARIO` | `zonas`, `coordinador_zona` |

**Criterios de evaluación por rubro:** **no existen** ni en el PDM ni en Laravel. `EVALUACION` guarda
una sola puntuación entera; la única regla de puntaje es el par `CONV_PUNTUACION_MAX`/`MIN` de la convocatoria.

## 2. Diferencias relevantes

1. **Calificación por rubro y observaciones:** el PDM no las contempla. Requieren tablas nuevas (criterio, rubro y calificación por rubro), o bien una extensión del PDM.
2. **Guardado parcial y validación documental:** existe `ESTADO_SOLICITUD_DOCUMENTO` (estado y observación por documento), aprovechable para la revisión documental de la fase 6.3. Falta un estado de evaluación «en proceso» o «parcial».
3. **Folios:** `DICT_FOLIO_DICTAMEN` tiene 9 caracteres y `CERT_FOLIO` 100. No hay reglas oficiales de folio; deben definirse antes de 6.7.
4. **Acta:** en el PDM es por solicitud y con texto libre (coordinador, secretario, sede). Un acta de resultados normalmente agrupa varias solicitudes, así que su cardinalidad está por confirmar.
5. **Convocatoria:** el PDM exige puntajes máximo y mínimo **NN**. No deben capturarse sin los criterios oficiales.
6. **Roles:** el PDM liga el rol al `USUARIO` técnico; Laravel usa `users.rol`. Para no duplicar, se recomienda conservar `users.rol` mientras el PDM esté apagado.

## 3. Alternativas de integración

| Alternativa | Descripción | Ventajas | Riesgos |
|---|---|---|---|
| A. Activar el PDM (espejo y luego principal) y usar sus tablas | Escribir evaluación, dictamen y acta en `anfeca_modelo` por la capa `anfeca_compat` | Una sola fuente institucional, sin duplicar | Requiere migración F2 completa, decisiones D-11 y catálogo de zonas; faltan tablas de criterios |
| B. Tablas Laravel nuevas en `public`, espejadas al PDM | Criterios, evaluación por rubro, dictamen y acta en `public`, con espejo en el PDM cuando se active | Avance independiente del PDM | Duplicación temporal; hay que mantener la equivalencia |
| C. Híbrida (recomendada para revisión) | Catálogos y criterios nuevos en `public` (no existen en el PDM). Evaluación, dictamen, acta y certificado con las **mismas columnas y claves** del PDM, para espejarlas sin transformación | Respeta el PDM y cubre lo que falta | Requiere aprobar estructuras nuevas y los criterios oficiales |

Antes de 6.4 se necesita:
- los criterios y puntajes oficiales de ANFECA;
- las reglas de folio de dictamen y acta;
- la cardinalidad del acta;
- el catálogo oficial de zonas;
- la decisión sobre la activación del PDM.
