# Catálogo de posgrados en «Último grado de estudios»

**Fecha:** 2026-11-25. **Estado: IMPLEMENTADO DETRÁS DE LA BANDERA `ANFECA_CATALOGO_POSGRADOS=false`.**

- La migración aditiva **no** está aplicada en 8086.
- El catálogo **no** está activado.
- Las 572 clasificaciones marcadas **no** están aprobadas.

Alternativa de persistencia aprobada: **A (columnas aditivas)**.

> La clasificación por área es **administrativa**: facilita la captura. **No** es una equivalencia académica oficial ni un criterio de elegibilidad. «Otro» **no** significa «no elegible»; la procedencia de un posgrado distinto la dictamina la Comisión Regional (DOC-03).

## 1. Fuente

| Elemento | Valor |
|---|---|
| Archivo | `CATALOGO DE POSGRADO_ACTUALIZADO_AL_2025 25-11-2026.xlsx` en `/home/servidorweb/ANFECA_FUENTES/` (fuera del repositorio; **no se modificó**) |
| Tamaño y SHA-256 | 738 488 bytes, SHA-256 `d5b630df24be1d3ff47b0917751d74b7f459161266dd2a72d54cb4ac7b1c85ad` |
| Hojas utilizadas | ESPECIALIDADES, MAESTRÍAS y DOCTORADOS |
| Hoja excluida | «Catálogo Posgrados ANUIES» (no se utiliza) |
| Columnas | A: área de estudio · B: carrera genérica · C: carrera específica · D: nombre del posgrado |

## 2. Método

El script reproducible es `scripts/anfeca/catalogos/clasificar_posgrados.py` (reglas `reglas-1.3`). Se ejecuta con un entorno virtual aislado con openpyxl y `python -I`.

**Limpieza**
- Solo se eliminan encabezados, filas sin nombre y duplicados exactos.
- Los nombres se conservan **tal como están en el original**: solo se recortan los espacios extremos.

**Clasificación** (el nombre es la señal principal; la carrera específica y la genérica solo desempatan)
- **Reglas ponderadas por área.** Se toma el peso máximo y se suma una bonificación pequeña por cada regla adicional. Nunca decide una sola palabra aislada.
- **Exclusiones:**
  - «redes sociales» no es Informática;
  - los dominios ajenos (derecho, salud, educación, ingeniería, etc.) van a «Otro».
- **Prioridades:**
  - «Administración tributaria/fiscal» → Contaduría;
  - «Administración de TI/redes» → Informática;
  - «Administración de negocios internacionales» → Negocios Internacionales.
- **Objeto gestionado:** en «Administración / Gestión / Dirección de X», el área es la de X. Por ejemplo, «Administración de justicia» → Otro.
- **Énfasis:** «Administración con enfoque en X» se clasifica como Administración con un área alternativa.

**Temas interdisciplinarios** (siempre «Requiere revisión»): finanzas, economía, logística, administración educativa, derecho fiscal, lavado de dinero, gestión tecnológica, analítica de negocios y administración de servicios especializados.

**Clave estable:** `ESP|MAE|DOC-` + los primeros 12 hexadecimales del SHA-256 del nombre normalizado (sin acentos, mayúsculas, puntuación ni espacios extra). Así, la misma clave identifica al mismo título entre ejecuciones.

## 3. Resultados

| Nivel | Títulos | Administración | Contaduría | Informática | Negocios Internacionales | Otro |
|---|---|---|---|---|---|---|
| Especialidad | 568 | 166 | 50 | 41 | 14 | 297 |
| Maestría | 1000 | 346 | 128 | 111 | 66 | 349 |
| Doctorado | 226 | 83 | 15 | 21 | 7 | 100 |
| **Total** | **1794** | 595 | 193 | 173 | 87 | 746 |

**Exclusiones e incidencias**
- Se excluyó 1 fila: DOCTORADOS, fila 138, que tiene clasificación pero no nombre de posgrado.
- No hubo duplicados exactos.
- 3 títulos no tienen área, genérica ni específica: se clasifican solo por su nombre.

**Confianza**

| Alta | Media | Baja |
|---|---|---|
| 945 | 593 | 256 |

**Requieren revisión: 572.**

| Tema de revisión | Títulos |
|---|---|
| Finanzas | 112 |
| Economía | 46 |
| Logística | 32 |
| Administración educativa | 14 |
| Economía y finanzas | 8 |
| Derecho fiscal | 7 |
| Administración de servicios especializados | 3 |
| Analítica de negocios | 3 |
| Lavado de dinero | 2 |

El resto son casos de confianza baja o con señales en conflicto.

**Casos obligatorios verificados** (`probar_clasificacion.py`):

| Título | Área |
|---|---|
| Administración Tributaria | Contaduría |
| Administración de TI | Informática |
| Redes y Telecomunicaciones | Informática |
| Administración de Negocios Internacionales | Negocios Internacionales |
| Administración de Justicia Civil/Penal | Otro |
| Administración de Empresas | Administración |
| Dirección Estratégica de Empresas | Administración |
| Auditoría | Contaduría |
| Ciencias Computacionales | Informática |

## 4. Archivos derivados (FUERA del repositorio, permisos 600)

Ubicación: `/home/servidorweb/ANFECA_FUENTES/`.

| Archivo | Contenido |
|---|---|
| `posgrados_anuies_2025.json` | SHA-256 `ed15f4f2…6e437c`. Estructura: `version`, `reglas`, `niveles`, `areas`, `aviso`, `titulos[]` = {`clave`, `nivel`, `area`, `titulo`, `confianza`, `requiere_revision`, `area_estudio`, `carrera_generica`, `carrera_especifica`, `hoja`, `fila`} |
| `matriz_clasificacion_posgrados.xlsx` | Hojas Resumen, Matriz (16 columnas, encabezados fijos, filtros) y Casos ambiguos. La columna «Clasificación autorizada» está **vacía** |
| `manifiesto_catalogo.json` | Huellas de la fuente y del catálogo, conteos, exclusiones, pendientes y confianza |

**Ni el Excel, ni el JSON, ni la matriz se publican en GitHub.** Las pruebas usan un catálogo ficticio: `tests/fixtures/catalogos/posgrados_ficticio.json`.

## 5. Persistencia (alternativa A)

Los campos existentes **no cambian de significado**:

| Campo | Significado |
|---|---|
| `nivel_ultimo_grado` | Nivel |
| `titulo_ultimo_grado` | Área |
| `titulo_ultimo_grado_otro` | Título libre del área «Otro» |

Columnas nuevas, nulas y aditivas:

| Columna | Tipo | Contenido |
|---|---|---|
| `titulo_ultimo_grado_especifico` | varchar(300) | Título exacto: del catálogo (lo determina el **servidor**) o de la captura manual |
| `origen_titulo_ultimo_grado` | varchar(20) | `catalogo` · `manual` · NULL (histórico, Licenciatura o bandera desactivada) |
| `clave_posgrado_catalogo` | varchar(40) | Clave estable, **solo** para selecciones reales del catálogo |
| `version_catalogo_posgrados` | varchar(40) | Versión del catálogo, **solo** para selecciones reales del catálogo |

**Área «Otro»:** el título también se refleja en `titulo_ultimo_grado_otro` (máximo 200 caracteres), por compatibilidad con el formulario anterior, las consultas y la capa PDM.

**Distinción de casos:**

| Caso | Columnas nuevas | Campos existentes |
|---|---|---|
| Catálogo | `catalogo` + clave + versión | — |
| Manual | `manual`, sin clave ni versión | — |
| Histórico | NULL | Sin cambios |
| Licenciatura | NULL | Comportamiento actual |
| «Otro» (catálogo o manual) | Igual que arriba | Más `_otro` |

**Migración propuesta** (fuera de la ruta automática): `database/migrations_propuestas/2026_11_27_000000_add_titulo_especifico_to_formaciones_academicas_solicitud.php`.

SQL equivalente en PostgreSQL:

```sql
BEGIN;
ALTER TABLE public.formaciones_academicas_solicitud
  ADD COLUMN titulo_ultimo_grado_especifico varchar(300) NULL,
  ADD COLUMN origen_titulo_ultimo_grado     varchar(20)  NULL,
  ADD COLUMN clave_posgrado_catalogo        varchar(40)  NULL,
  ADD COLUMN version_catalogo_posgrados     varchar(40)  NULL;
ALTER TABLE public.formaciones_academicas_solicitud
  ADD CONSTRAINT fas_titulo_especifico_coherente_ck CHECK (
       (origen_titulo_ultimo_grado IS NULL AND titulo_ultimo_grado_especifico IS NULL
            AND clave_posgrado_catalogo IS NULL AND version_catalogo_posgrados IS NULL)
    OR (coalesce(origen_titulo_ultimo_grado, '') = 'catalogo' AND coalesce(btrim(titulo_ultimo_grado_especifico), '') <> ''
            AND coalesce(clave_posgrado_catalogo, '') ~ '^(ESP|MAE|DOC)-[0-9A-F]{12}$'
            AND coalesce(btrim(version_catalogo_posgrados), '') <> '')
    OR (coalesce(origen_titulo_ultimo_grado, '') = 'manual' AND coalesce(btrim(titulo_ultimo_grado_especifico), '') <> ''
            AND clave_posgrado_catalogo IS NULL AND version_catalogo_posgrados IS NULL));
COMMIT;
```

- Los `coalesce` evitan que un NULL haga pasar el CHECK. Este defecto se detectó y corrigió en la prueba con PostgreSQL desechable.
- Las filas existentes no se modifican: las columnas nuevas quedan en NULL.
- `down()` **se niega** si alguna fila tiene datos en las columnas nuevas. Solo elimina columnas vacías.

**Aplicación futura en 8086 (NO autorizada todavía):**
1. Respaldo verificado: `bash scripts/anfeca/respaldo_verificado.sh bd_anfeca_ensayo_pruebas_pg desarrollo/modulo-coordinacion <commit>`.
2. Aplicar solo esta migración: `php artisan migrate --force --path=database/migrations_propuestas/2026_11_27_000000_…php`. **Nunca** un `migrate` general ni la migración de `escuela_facultad`.
3. Verificar: 4 columnas, CHECK presente y conteo de filas sin cambios.

## 6. Validaciones del servidor (`App\Services\Catalogos\TituloUltimoGrado`)

Con el catálogo activo y un nivel distinto de Licenciatura:

| Campo | Regla |
|---|---|
| `modo_titulo_ultimo_grado` | `catalogo` o `manual` |
| `clave_posgrado_catalogo` | Obligatoria en modo catálogo; prohibida en modo manual; formato `^(ESP\|MAE\|DOC)-[0-9A-F]{12}$` |
| `titulo_ultimo_grado_manual` | Obligatorio en modo manual (5–200 caracteres, sin `<` ni `>`); prohibido en modo catálogo |
| `titulo_ultimo_grado_otro` | Prohibido (lo deriva el servidor) |

En modo catálogo:
- La clave debe **existir** en la versión vigente y corresponder al **nivel** y al **área** enviados.
- El nombre guardado es el del catálogo del servidor.
- Se ignoran los nombres, orígenes o versiones que envíe el navegador.

Se conservan sin cambios la autorización (`solo.solicitantes`), el bloqueo por solicitud no borrador (`lockForUpdate`), la formación autorizada y las reglas de envío.

## 7. Interfaz

Solo cambia la ficha «Último grado de estudios», y solo con el catálogo activo:
- **Fila 1:** Nivel y Área al 50 % cada uno (34 px).
- **Fila 2:** «Nombre del título» al 100 % con `SelectorBuscable.vue`. Lo describe el estándar de catálogos extensos: `docs/anfeca/ESTANDARES_UI_UX.md`, §7 bis.7.
- La última opción, «No encuentro mi título», está disponible en todas las áreas. Cambia a captura manual en el mismo espacio y permite volver al catálogo sin perder el texto.
- Licenciatura conserva su comportamiento: el título libre solo aparece con el área «Otro».
- Con la bandera desactivada, el formulario anterior queda intacto.
- Si la bandera está activa pero falta el archivo, la huella no coincide o falta la migración:
  - se muestra el formulario anterior con un aviso;
  - el endpoint responde 404;
  - el motivo queda en el registro.

**Endpoint** `GET /registro/catalogos/posgrados?nivel=&area=&q=`:
- autenticado (solicitantes) y limitado a 60 solicitudes por minuto;
- máximo 400 resultados; el grupo mayor tiene 349;
- devuelve solo `clave` y `titulo`, con `Cache-Control: private`.

**Consultas que muestran el título exacto:**
- el expediente de coordinación (título, origen y área);
- el acuse (título y área).

Las demás pantallas (seguimiento, `RegistroCompletado`) solo verifican la completitud con nivel y área. No cambian.

## 8. Bandera, activación y recuperación

**Instalación del catálogo en 8086 (cuando se autorice):**
1. Copiar el JSON a `storage/app/private/catalogos/posgrados_anuies_2025.json` con dueño `servidorweb`, grupo `apache` y permisos `0640`. El directorio `storage/app/private` ya tiene el contexto `httpd_sys_rw_content_t`.
2. **No** se cambian los permisos del HOME, ni SELinux, ni se usa 777.
3. En el `.env` local:
   - `ANFECA_CATALOGO_POSGRADOS_SHA256=ed15f4f2197bd57f779ed94ad0d53558a4a3e7bcdc31bebf614833aeb16e437c`;
   - `ANFECA_CATALOGO_POSGRADOS=true`, **solo con autorización**;
   - después, `php artisan config:clear`.

**Niveles de recuperación:**
1. **Bandera.** `ANFECA_CATALOGO_POSGRADOS=false` y `config:clear`.
   - Vuelve el formulario anterior.
   - Títulos, claves y versiones se conservan y se siguen mostrando en el expediente y el acuse.
   - Con el formulario anterior, un registro con título específico **no** puede cambiar de nivel o área: se rechaza con un mensaje explícito y no cambia nada.
   - Un título de catálogo del área «Otro» no se edita desde el formulario anterior. Un título manual del área «Otro» se mantiene sincronizado.
   - Al reactivar la bandera, todo reaparece.
2. **Código.** `git revert --no-edit` de los commits del catálogo, en orden inverso y sin `reset --hard`; push sin forzar; recompilar.
   - Las columnas, si existen, conservan sus datos.
   - El código anterior no las toca, porque `updateOrCreate` solo usa las claves validadas.
3. **Base de datos.** Solo con autorización expresa y nunca de forma automática.
   - Se restaura el respaldo verificado en una base nueva, se compara y se hace un cambio controlado.
   - **Nunca** se eliminan columnas con datos: `down()` lo impide.

**Punto de recuperación previo:** `~/ANFECA_RESPALDOS/catalogo_posgrados_punto_recuperacion_20261125_190949/`.

| Elemento | Valor |
|---|---|
| Commit | `affce07` |
| Compilación | `app-JYtMCHvV.js` |
| Respaldo verificado | `c3755e64…`, 175 filas |
| Huellas | 20 documentos |

## 9. Actualizaciones futuras del catálogo

1. Ejecutar el script con el nuevo Excel y una `VERSION_CATALOGO` nueva.
2. Revisar la matriz.
3. Aprobar las clasificaciones.
4. Instalar el JSON con la nueva huella y versión (`ANFECA_CATALOGO_POSGRADOS_VERSION`).

Las selecciones guardadas conservan su clave y su versión de origen. El título guardado no cambia aunque el catálogo cambie.

## 10. Impacto en el PDM (solo análisis; PDM apagado)

- `EquivalenciaPdm` y `anfeca_compat` mapean nivel, área y `titulo_ultimo_grado_otro`. No conocen las columnas nuevas, y el reflejo en `_otro` (área «Otro») mantiene la información disponible.
- **Extensión pendiente:** mapear `titulo_ultimo_grado_especifico`, origen y clave al modelo PDM cuando se active. No se modificó ningún componente del PDM.

## 11. Pruebas

| Prueba | Resultado |
|---|---|
| `scripts/anfeca/catalogos/probar_clasificacion.py`: casos obligatorios, temas de revisión, claves estables, consistencia JSON ↔ matriz ↔ manifiesto y reproducibilidad | Correcto |
| `tests/Feature/CatalogoPosgradosTest.php` (catálogo ficticio, SQLite) | 15 pruebas |
| `tests/Pg/CatalogoPosgradosMigracionPgTest.php` (PostgreSQL 18.6 desechable): columnas, CHECK con 8 combinaciones incoherentes, reversión segura | Correcto |
| Demostración desechable (127.0.0.1:8099, datos ficticios) | 31 verificaciones visuales |

**Medidas de altura del documento:**

| Caso | Antes | Ahora |
|---|---|---|
| Formulario vacío, 1366 px | 974 px | 974 px |
| Con «Otro», 1366 px | 1122 px | 1122 px |
| Móvil | 2247 px | 2227 px |

La lista abierta no altera la altura del documento.

## 12. Decisiones pendientes (detención obligatoria)

1. Aprobar o corregir las 572 clasificaciones marcadas (columna «Clasificación autorizada»).
2. Autorizar la migración aditiva en 8086.
3. Autorizar la instalación del JSON y la activación de la bandera.
4. Definir si los registros históricos de posgrado se completan (solo por acción del solicitante o por decisión expresa; nunca de forma masiva).
