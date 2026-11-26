# Catálogo de posgrados en «Último grado de estudios»: propuesta, bloqueos y recuperación

**Fecha:** 2026-11-25. **Estado: DETENIDO ANTES DE IMPLEMENTAR.** Hay dos bloqueos (§1). No se modificó código funcional ni la base de datos.

## 1. Bloqueos

| # | Bloqueo | Evidencia | Qué se requiere |
|---|---|---|---|
| B1 | **El archivo fuente no está en el servidor**: `CATALOGO DE POSGRADO_ACTUALIZADO_AL_2025 25-11-2026.xlsx` | Búsqueda en todo el sistema de archivos (`find / -xdev`): no hay ningún `.xls*` de posgrados. Solo existen los documentos normativos descargados de la CNCA | Copiar el archivo, sin modificarlo, a `/home/servidorweb/ANFECA_FUENTES/` (ruta propuesta fuera del repositorio y de `/var/www`) |
| B2 | **Los campos existentes no permiten conservar con seguridad nivel + área + título exacto** | Ver §2 | Elegir una alternativa de persistencia (§3). La recomendada requiere una **migración aditiva**, que no está autorizada |

Sin B1 no es posible clasificar los 1794 registros ni verificar los conteos (568 / 1000 / 226). Sin B2, la parte de persistencia debe detenerse, como indica la instrucción (§13).

## 2. Análisis de los campos existentes (`formaciones_academicas_solicitud`)

| Campo | Tipo | Uso actual | Validación actual |
|---|---|---|---|
| `nivel_ultimo_grado` | varchar(255) | Nivel | `in: Licenciatura, Especialidad, Maestría, Doctorado` |
| `titulo_ultimo_grado` | varchar(255) | **Área** (no el título) | `in: Administración, Contaduría, Informática, Negocios Internacionales, Otro` |
| `titulo_ultimo_grado_otro` | varchar(200) | Título libre, solo cuando el área es «Otro» | `required_if:…,Otro`, `prohibited_unless:…,Otro`, `max:200` |

**Hallazgo determinante.** `FormacionAcademicaController.php`, líneas 287–288:

```php
if (($datos['titulo_ultimo_grado'] ?? null) !== 'Otro') {
    $datos['titulo_ultimo_grado_otro'] = null;
}
```

El código actual **borra** el título personalizado cuando el área no es «Otro».

**Datos actuales en 8086** (no se modifican):

| Folio | Nivel | Área | Título libre |
|---|---|---|---|
| 3 | Maestría | Informática | — |
| 4 | Maestría | Contaduría | — |
| 5 | Maestría | Otro | «Economía» |

**Lectores de estos campos:**
- `ConsultaSolicitudes` (expediente de coordinación: muestra `_otro` solo si el área es «Otro»);
- `acuse-recepcion.blade.php`, líneas 441–447 (misma regla);
- `RegistroCompletadoController` y `SeguimientoRegistros` (exigen nivel y área);
- `EquivalenciaPdm` y la capa `anfeca_compat` (PDM apagado; mapea `titulo` y `otro`).

## 3. Alternativas de persistencia

| Alt. | Descripción | Conserva los 3 elementos | Desactivar la bandera sin pérdida | Contradice validaciones actuales | Requiere migración | Riesgo |
|---|---|---|---|---|---|---|
| **A (recomendada)** | Mantener `titulo_ultimo_grado` = área y `_otro` = título libre del área «Otro» (sin cambios). **Agregar** columnas nulas `titulo_ultimo_grado_catalogo` varchar(300), `titulo_ultimo_grado_clave` varchar(40) (clave estable del registro) y `catalogo_posgrados_version` varchar(20) | Sí | **Sí**: el código anterior no conoce las columnas nuevas y `updateOrCreate` no las toca | No | **Sí** (aditiva, nula, reversible) | Bajo |
| B | Guardar el título exacto en `titulo_ultimo_grado_otro` para todas las áreas | Parcial (200 caracteres; mezcla título de catálogo y título libre) | **No**: con la bandera desactivada, las líneas 287–288 lo borran al volver a guardar | **Sí** (`prohibited_unless`) | No | Alto |
| C | Guardar el título exacto en `titulo_ultimo_grado` y derivar el área del catálogo | Sí (área derivada) | **No**: el formulario anterior no muestra el valor y su validación lo rechaza; obliga a recapturar | **Sí** (`in:` 5 áreas) | No | Alto |
| D | Tabla aparte `formacion_titulo_catalogo` (1:1 con la formación) | Sí | Sí | No | Sí | Bajo; más piezas que A |
| E | Guardar la selección solo en el navegador o en la sesión hasta autorizar la migración | No (no persiste) | — | No | No | No cumple el requisito |

**Recomendación: A.** Es aditiva y reversible (`down()` que solo elimina columnas vacías o con respaldo). Requiere **autorización expresa** para la migración. Hasta entonces, la persistencia queda detenida.

**Con A:**
- **Validación:** el servidor recalcula y verifica `clave` + nivel + área contra el catálogo JSON versionado; rechaza títulos inexistentes, de otro nivel o de otra área y valores manipulados.
- **«No encuentro mi título»:** `area = Otro` y `_otro` obligatorio (máximo 200), igual que hoy.
- **Lectura:** expediente, acuse y seguimiento muestran `titulo_ultimo_grado_catalogo` cuando existe y, si no, la regla actual.
- **Desactivar la bandera:** se oculta el selector; las columnas nuevas conservan su valor y se siguen mostrando en las consultas.
- **PDM:** la capa `anfeca_compat` no las mapea. Se documenta como extensión pendiente (PDM apagado).

## 4. Diseño previsto (listo para implementar al levantar B1 y B2)

- **Fuente única:** `storage/app/catalogos/posgrados.<version>.json`. No se publica si no está autorizado: se genera desde el Excel con un script reproducible y se registra su SHA-256 y el del Excel.
  - Servicio `App\Services\Catalogos\CatalogoPosgrados`: lectura, filtrado y validación.
  - Endpoint `GET /registro/catalogos/posgrados?nivel=&area=&q=`, paginado y limitado. Vue **no** incorpora las 1794 opciones.
- **Bandera:** `ANFECA_CATALOGO_POSGRADOS=false` en `config/anfeca_catalogos.php`. Controla de forma coherente la vista, el componente, las reglas y el endpoint (404 cuando está desactivada).
- **Interfaz** (solo la ficha «Último grado de estudios»):
  - fila 1: Nivel (50 %) y Área académica (50 %), selects de 32–34 px;
  - fila 2: Nombre del título (100 %) con un componente `SelectorBuscable.vue` de 44–48 px, hasta 2 líneas visibles, lista flotante de ≈ 200 px con desplazamiento interno, apertura hacia arriba si no hay espacio, búsqueda sin acentos ni mayúsculas, teclado, cierre con Escape, clic fuera o selección; no altera la altura del documento;
  - área «Otro»: incluye «No encuentro mi título», que habilita la captura manual obligatoria en la misma fila.
- **Licenciatura:** comportamiento actual sin cambios.
- **Matriz de clasificación** (con el Excel): las 1794 filas con nivel, carrera genérica, carrera específica, nombre, área propuesta, criterio, confianza, observaciones, requiere revisión y clasificación autorizada (vacía).
  - Clasificación por reglas ponderadas sobre la carrera específica, el nombre y la carrera genérica, con excepciones explícitas (p. ej., «Administración Tributaria» → Contaduría).
  - Los casos ambiguos se marcan para revisión.
  - Se genera **fuera del repositorio** si su distribución no está autorizada.

## 5. Punto de recuperación (realizado el 2026-11-25)

| Elemento | Ubicación |
|---|---|
| Commit actual | `affce07` (`desarrollo/modulo-coordinacion`) |
| Cambios locales ajenos | 4 archivos, registrados y sin tocar (`cambios_locales.txt` / `.diff`) |
| Fuentes de Formación, controladores, vistas y configuración | `fuentes_formacion.tar.gz` |
| Compilación servida en 8086 | `compilacion_servida_public_build.tar.gz` (`app-JYtMCHvV.js`) |
| Respaldo de la base, verificado por restauración real | `respaldo_verificado_bd_anfeca_ensayo_pruebas_pg_20261125_190950` · SHA-256 `c3755e64d04ccfa7…`; catálogo y 175 filas idénticos |
| Huellas de los 20 documentos originales | `documentos_huellas.json`, idénticas a la línea base |
| Carpeta | `~/ANFECA_RESPALDOS/catalogo_posgrados_punto_recuperacion_20261125_190949/` (permisos 700) |

## 6. Procedimiento de recuperación (tres niveles)

1. **Nivel 1: desactivación funcional.** `ANFECA_CATALOGO_POSGRADOS=false` en el `.env` local y `php artisan config:clear`.
   - Vuelve el formulario anterior.
   - Con la alternativa A, los títulos ya guardados se conservan en sus columnas y se siguen mostrando.
   - Los documentos no cambian.
2. **Nivel 2: reversión de código.** `git revert --no-edit <commits del catálogo>`, en orden inverso y solo esos commits, sin afectar a la coordinación; push sin forzar.
   - Recompilar (`npx vite build`) y comprobar que el manifiesto coincide con la compilación esperada.
   - Si fuera necesario, restaurar `compilacion_servida_public_build.tar.gz`.
3. **Nivel 3: recuperación de la base.** Solo con autorización expresa y análisis de los datos afectados.
   - Restaurar el respaldo verificado en una base nueva, comparar y hacer un cambio controlado.
   - **Nunca** de forma automática: eliminaría lo registrado después del respaldo.

## 7. Decisiones pendientes

1. Proporcionar el Excel (B1).
2. Elegir la alternativa de persistencia; con A, autorizar la migración aditiva (B2).
3. Autorizar la distribución en GitHub del JSON derivado o mantenerlo fuera del repositorio.
4. Aprobar la clasificación (columna «Clasificación autorizada») antes de la activación definitiva.

## 8. Captura de referencia de la pantalla anterior

Captura con solicitantes ficticios en la demo desechable: `formacion_antes_*` en el expediente de evidencias.

| Medida | Valor |
|---|---|
| Altura del documento a 1366 px | 974 px (formación vacía) · 1122 px (con título «Otro») |
| Altura en móvil | 2247 px |
| Select de nivel | 34 px |
| Select de área | 38 px |
| Opciones de área | Administración, Contaduría, Informática, Negocios Internacionales, Otro |
