# Fase 6.3.1 · Diseño técnico de la persistencia de la revisión documental

> **Estado (2026-11-25): IMPLEMENTADA Y PROBADA EN ENTORNOS AISLADOS · MIGRACIÓN PENDIENTE DE AUTORIZACIÓN.**
> - La migración está en `database/migrations/` (autorizada, commit `66d40dc`) y se aplica **solo con `--path`**. **Aún no se ha ejecutado** en la base de 8086 (`bd_anfeca_ensayo_pruebas_pg`); ver §9.0.
> - El código funcional está publicado pero **desactivado** (`ANFECA_COORDINACION_REVISION=false`); en 8086 las rutas responden 404 y el expediente no cambia.
> - El PDM permanece **apagado** y **no** hay espejo hacia `anfeca_modelo`.

| Elemento | Ubicación |
|---|---|
| Migración propuesta | `database/migrations_propuestas/2026_11_26_000000_create_revisiones_documentales_table.php`, **fuera** de `database/migrations`, así que `php artisan migrate` no la ve |
| Pruebas de restricciones | `tests/Feature/RevisionesDocumentalesEsquemaTest.php` (SQLite en memoria) y `phpunit.revision-pg.xml` (PostgreSQL desechable) |
| Análisis previo | `REVISION_DOCUMENTAL_FASE_6_3_0.md` (estados, alternativas A/B/C y PDM) |
| Visualización del prototipo | `scripts/anfeca/demo_prototipo/` (§10) |

Decisión adoptada: **alternativa C**, persistencia híbrida. La fase 6.3.1 implementa solo la tabla Laravel `public.revisiones_documentales`. Su correspondencia con el PDM queda documentada en §8 para una fase posterior.

---

## 1. Hechos verificados en el proyecto (solo lectura, 2026-11-25)

| Hecho | Consecuencia para el diseño |
|---|---|
| `solicitudes_certificacion.id`, `users.id`, `solicitud_id`: `bigint` | Llaves `bigint` |
| Los documentos se guardan como **columnas de ruta** en `formaciones_academicas_solicitud` (4) y `documentos_probatorios_solicitud` (4) | No hay tabla de documentos con id. La clave del documento es el **nombre de la columna** (`documento_clave`, 8 valores) |
| La carga usa `->store('anfeca/documentos/solicitudes/{id}', 'local')`, que genera un nombre aleatorio único | Cada reemplazo produce una **ruta nueva** |
| La versión se identifica de forma inequívoca por **ruta + SHA-256 + bytes** | Se registra lo que el coordinador revisó de verdad |
| El solicitante solo modifica documentos en `borrador`; la revisión solo procede en `enviado` | La regla «solo enviadas» se verifica en la aplicación **y** en un disparador de la base |
| Las llaves foráneas existentes usan `ON DELETE RESTRICT` | Se mantiene el criterio: **sin cascadas** |
| `rol_anfecalaravel` es propietario de las tablas y no es superusuario; PostgreSQL 18.6 | Los disparadores y el `REVOKE` se crean con ese rol (§6.3) |
| `anfeca_modelo.ESTADO_SOLICITUD_DOCUMENTO`: 0 filas; catálogo `ESTADO_DOCUMENTO` = {Cargado, Sustituido} | El PDM no tiene hoy evaluador ni resultado de revisión (§8) |

## 2. Modelo de datos y relaciones

```
users (bigint id) ───────────────┐ coordinador_id (RESTRICT)
                                 ▼
solicitudes_certificacion ──► revisiones_documentales ◄──┐ revision_anterior_id (RESTRICT, UNIQUE)
   (bigint id)  solicitud_id    (historial de solo        │   = cadena lineal por documento
                (RESTRICT)       inserción)          ─────┘
                                     │
                                     ▼
                     revisiones_documentales_vigentes (vista: filas sin sucesor)
```

- **Cadena por documento:** para cada `(solicitud_id, documento_clave)` existe una cadena lineal `r1 ← r2 ← r3`.
  - La primera revisión tiene `revision_anterior_id = NULL` (índice único parcial: solo una).
  - Cada revisión tiene a lo sumo un sucesor (`UNIQUE (revision_anterior_id)`).
  - La revisión **vigente** es la que no tiene sucesor.
- **Por revisar:** no se guarda. Un documento aplicable está «Por revisar» cuando no hay revisión vigente **o** cuando la versión vigente del archivo (ruta + huella) difiere de la versión revisada.
- **No aplica:** no se guarda. Lo calculan las reglas del trámite (artículo 13/14 solo en refrendo con `aplica_articulo_14`, como en la pantalla de solicitantes). Como `resultado` no admite `no_aplica`, ese estado **no puede usarse para omitir un documento obligatorio**.

## 3. Definición exacta (PostgreSQL, normativa)

Este es el texto que ejecuta `upPostgres()`; la lista de documentos se expande desde la constante `DOCUMENTOS`:

```sql
CREATE TABLE public.revisiones_documentales (
    id                    bigint GENERATED ALWAYS AS IDENTITY,
    solicitud_id          bigint       NOT NULL,
    documento_clave       varchar(60)  NOT NULL,
    archivo_estado        varchar(20)  NOT NULL,   -- cargado | no_cargado | no_localizado
    version_ruta          varchar(255),
    version_sha256        char(64),
    version_bytes         bigint,
    resultado             varchar(20)  NOT NULL,   -- validado | con_observaciones | no_valido
    observaciones         text,
    motivo_cambio         text,
    revision_anterior_id  bigint,
    coordinador_id        bigint       NOT NULL,
    coordinador_nombre    varchar(255) NOT NULL,   -- copia al momento de revisar
    coordinador_rol       varchar(30)  NOT NULL,   -- copia al momento de revisar
    solicitud_estado      varchar(20)  NOT NULL,   -- copia: siempre 'enviado'
    clave_idempotencia    uuid         NOT NULL,
    revisado_en           timestamptz  NOT NULL DEFAULT clock_timestamp(),

    CONSTRAINT revisiones_documentales_pkey PRIMARY KEY (id),
    CONSTRAINT rd_solicitud_fk   FOREIGN KEY (solicitud_id)  REFERENCES public.solicitudes_certificacion (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT rd_coordinador_fk FOREIGN KEY (coordinador_id) REFERENCES public.users (id)                    ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT rd_anterior_fk    FOREIGN KEY (revision_anterior_id) REFERENCES public.revisiones_documentales (id) ON UPDATE RESTRICT ON DELETE RESTRICT,

    CONSTRAINT rd_documento_ck  CHECK (documento_clave IN ('titulo_origen_archivo','cedula_origen_archivo',
        'titulo_ultimo_grado_archivo','cedula_ultimo_grado_archivo','constancia_antiguedad_archivo',
        'comprobante_pago_archivo','solicitud_articulo_14_archivo','constancia_articulo_14_archivo')),
    CONSTRAINT rd_archivo_estado_ck CHECK (archivo_estado IN ('cargado','no_cargado','no_localizado')),
    CONSTRAINT rd_resultado_ck  CHECK (resultado IN ('validado','con_observaciones','no_valido')),
    CONSTRAINT rd_version_ck CHECK (
           (archivo_estado = 'cargado'       AND version_ruta IS NOT NULL AND version_sha256 IS NOT NULL AND version_bytes IS NOT NULL)
        OR (archivo_estado = 'no_localizado' AND version_ruta IS NOT NULL AND version_sha256 IS NULL     AND version_bytes IS NULL)
        OR (archivo_estado = 'no_cargado'    AND version_ruta IS NULL     AND version_sha256 IS NULL     AND version_bytes IS NULL)),
    CONSTRAINT rd_sha256_ck  CHECK (version_sha256 IS NULL OR version_sha256 ~ '^[0-9a-f]{64}$'),
    CONSTRAINT rd_ruta_ck    CHECK (version_ruta IS NULL OR (version_ruta LIKE 'anfeca/%' AND strpos(version_ruta, '..') = 0)),
    CONSTRAINT rd_bytes_ck   CHECK (version_bytes IS NULL OR version_bytes >= 0),
    CONSTRAINT rd_validado_requiere_archivo_ck CHECK (resultado <> 'validado' OR archivo_estado = 'cargado'),
    CONSTRAINT rd_observaciones_ck       CHECK (resultado = 'validado' OR char_length(btrim(coalesce(observaciones, ''))) >= 10),
    CONSTRAINT rd_observaciones_largo_ck CHECK (observaciones IS NULL OR char_length(observaciones) <= 1000),
    CONSTRAINT rd_motivo_ck CHECK (motivo_cambio IS NULL OR char_length(btrim(motivo_cambio)) BETWEEN 10 AND 1000),
    CONSTRAINT rd_motivo_solo_con_anterior_ck CHECK (revision_anterior_id IS NOT NULL OR motivo_cambio IS NULL),
    CONSTRAINT rd_rol_ck     CHECK (coordinador_rol IN ('coordinador_nacional','coordinador_zona')),
    CONSTRAINT rd_nombre_ck  CHECK (char_length(btrim(coordinador_nombre)) > 0),
    CONSTRAINT rd_solicitud_estado_ck CHECK (solicitud_estado = 'enviado'),
    CONSTRAINT rd_sucesor_unico_uq UNIQUE (revision_anterior_id),
    CONSTRAINT rd_idempotencia_uq  UNIQUE (clave_idempotencia)
);

CREATE UNIQUE INDEX rd_primera_revision_uq ON public.revisiones_documentales (solicitud_id, documento_clave)
    WHERE revision_anterior_id IS NULL;
CREATE INDEX rd_documento_idx   ON public.revisiones_documentales (solicitud_id, documento_clave, id DESC);
CREATE INDEX rd_coordinador_idx ON public.revisiones_documentales (coordinador_id, revisado_en DESC);

-- BEFORE INSERT: rd_validar_insercion()
--   · revisado_en := clock_timestamp()            (la fecha la fija la base, no el cliente)
--   · la solicitud debe estar 'enviado' AHORA     (no solo la copia)
--   · users.rol del coordinador = coordinador_rol (no se puede suplantar el rol)
--   · la revisión anterior es del mismo (solicitud_id, documento_clave)
--   · si el resultado difiere del anterior → motivo_cambio obligatorio
-- BEFORE UPDATE OR DELETE (por fila) y BEFORE TRUNCATE (por sentencia): rd_impedir_modificacion() → excepción
REVOKE ALL ON public.revisiones_documentales FROM PUBLIC;

CREATE VIEW public.revisiones_documentales_vigentes AS
    SELECT r.* FROM public.revisiones_documentales r
     WHERE NOT EXISTS (SELECT 1 FROM public.revisiones_documentales s WHERE s.revision_anterior_id = r.id);
```

`down()` **se niega** a revertir si la tabla contiene al menos una fila. Solo elimina una tabla vacía.

## 4. Columnas, nulos y documentos no cargados

| Columna | Nulo | Regla |
|---|---|---|
| `id` | No | Identidad `GENERATED ALWAYS`: no admite valores manuales |
| `solicitud_id`, `coordinador_id` | No | Llave foránea `RESTRICT` |
| `documento_clave` | No | Una de las 8 claves; en la aplicación debe además ser **aplicable** al trámite |
| `archivo_estado` | No | Estado del archivo **al momento de revisar** |
| `version_ruta` | Sí | Nula solo si `no_cargado`. Debe iniciar con `anfeca/` y no contener `..` |
| `version_sha256` / `version_bytes` | Sí | Obligatorias solo si `cargado`. Huella en hexadecimal minúscula de 64 caracteres |
| `resultado` | No | `validado` exige `cargado` |
| `observaciones` | Sí | Obligatoria (≥ 10 caracteres sin espacios extremos) en `con_observaciones` y `no_valido`; opcional en `validado`; máximo **1000** |
| `motivo_cambio` | Sí | Solo cuando hay revisión anterior; obligatorio si cambia el resultado; 10 a 1000 caracteres |
| `revision_anterior_id` | Sí | Nula solo en la primera revisión del documento |
| `coordinador_nombre`, `coordinador_rol`, `solicitud_estado` | No | Copias que conservan el contexto aunque después cambie el usuario |
| `clave_idempotencia` | No | UUID generado al abrir el formulario de revisión; evita duplicados por reintento |
| `revisado_en` | No | Hora del servidor de base de datos |

**Documentos no cargados o no localizados.** Pueden revisarse solo con resultado desfavorable (`con_observaciones` o `no_valido`) y observación obligatoria, por ejemplo «El documento no fue cargado». No tienen ruta ni huella (`no_cargado`) o tienen ruta sin huella (`no_localizado`), y nunca pueden quedar «Validado».

## 5. Reglas funcionales (fase 6.3.1, servicio `RevisionDocumental`)

| Regla | Aplicación | Base de datos |
|---|---|---|
| Solo solicitudes **enviadas** | Política y servicio: 409 si no está enviada | Disparador y `rd_solicitud_estado_ck` |
| Permisos y alcance por zona | `Gate revisarDocumentoComoCoordinacion` y `AlcanceZona`: 404 fuera de alcance | Rol real verificado por disparador |
| Documento aplicable al trámite | `DocumentosAplicables`: 404 si no aplica (no se puede «No aplica» manual) | Lista de claves (`rd_documento_ck`) |
| Observación obligatoria en desfavorables | `RegistrarRevisionRequest`: 422 | `rd_observaciones_ck` |
| Justificación al cambiar un resultado | 422 si falta `motivo_cambio` | Disparador |
| No validar un archivo inexistente | 422 | `rd_validado_requiere_archivo_ck`, `rd_version_ck` |
| Coordinador, fecha e historial | Se toman de la sesión, nunca del formulario | Copias, `clock_timestamp()` y solo inserción |
| Guardado parcial | Una revisión por documento; no exige revisar todo | Una fila por evento |
| Sin dictamen automático | El servicio no cambia `solicitudes_certificacion` ni calcula puntajes | Sin columnas de dictamen |
| Archivos originales protegidos | Solo lectura (`Storage::readStream` para la huella); nunca se mueven ni reescriben | La ruta se guarda como texto |

### 5.1 Concurrencia, versión e idempotencia (HTTP 409)

Al **abrir** el documento (`GET …/revisiones`), el servidor entrega:
- `revision_vigente_id`, o nulo;
- `version.token`: SHA-256 de **estado de carga + ruta + SHA-256 del archivo + tamaño**, calculado leyendo el archivo real;
- una `clave_idempotencia` (UUID) nueva por cada apertura.

El navegador devuelve esos tres valores sin modificarlos. **Nunca** son la fuente de verdad: el servidor los vuelve a calcular.

`RevisionDocumental::registrar()` sigue estos pasos dentro de una transacción:

| Paso | Comprobación | Respuesta |
|---|---|---|
| 0 | `SELECT … FOR SHARE` de la solicitud. No la modifica, pero espera a cualquier cambio en curso del solicitante (que bloquea con `FOR UPDATE`) | — |
| 1 | **Idempotencia.** Si la clave ya existe y coinciden coordinador, solicitud, documento, versión (token), resultado, observaciones, motivo y revisión anterior, devuelve la existente. Si algo difiere | **200** (reintento) / **409** `idempotencia` |
| 2 | La solicitud está `enviado` (estado actual) | **409** `estado` |
| 3 | La vigente actual es la consultada. Si no lo es, antes de responder se **vuelve a consultar la clave**: un reintento de la misma petición confirmado entre los pasos 1 y 3 se reconoce como reintento (carrera hallada con procesos reales) | **409** `concurrencia` (incluye la vigente) / **200** |
| 4 | El token de versión **recalculado desde el archivo** coincide con el consultado: misma ruta, huella, tamaño y estado | **409** `version` |
| 5 | «Validado» exige archivo cargado; cambiar el resultado exige `motivo_cambio` | **422** |
| 6 | Inserción; la base rechaza una bifurcación o un duplicado simultáneo | **409** `concurrencia` (o reintento si es la misma clave) |

- **Autorización en cada petición.** La política y el alcance por zona se verifican **antes** de buscar una revisión previa. Si el coordinador perdió la zona, el reintento responde 404 y no devuelve la revisión.
- **«Ver» de una versión obsoleta.** El enlace lleva `?v=<token>` y responde **409** si el archivo cambió.
- **Sin bloqueos sobre el historial.** No se usa `FOR UPDATE` en `revisiones_documentales`, porque exigiría privilegio UPDATE. Las restricciones únicas son la garantía final.

**Probado con concurrencia real** en PostgreSQL 18.6 desechable (`tests/Pg/RevisionDocumentalConcurrenciaPgTest.php`), con procesos PHP independientes y transacciones confirmadas:
- 6 primeras revisiones simultáneas: 1 creada y 5 con 409;
- 6 sucesores simultáneos de la misma vigente: 1 creado y 5 con 409, con un solo sucesor;
- 4 reintentos simultáneos con la misma clave: 1 creada y 3 que devuelven la misma fila. Una primera ejecución falló de forma intermitente; la corrección del paso 3 quedó estable en 10 de 10 rondas;
- carrera de **sustitución del archivo**: la revisión espera al bloqueo del solicitante y recibe 409 `version`.

### 5.2 Integración en la interfaz (implementada, sin perder la compactación 6.2.4)

Se conservan:
- las fichas 50/50 de igual altura (medidas: 526 × 461 px a 1366 px, solicitante a la izquierda);
- la cuadrícula 3 × 2;
- los adicionales en horizontal;
- el encabezado, el pie, el menú lateral y la numeración.

Cada tarjeta muestra, **sin formularios**:
- nombre;
- insignia de **carga**;
- insignia de **resultado** (texto y color, distinta de la de carga);
- botones «Ver» y «Revisar», apilados y compactos.

La tarjeta mide **62 px** en 1366×768, 1920×1080, tableta y móvil.

Una línea de **indicadores** encabeza la sección: aplicables, cargados, faltantes, por revisar, validados, con observaciones, no válidos, % revisado y % validado. Los dos porcentajes se muestran por separado y los no aplicables se excluyen de todos los denominadores.

El **diálogo modal** (`RevisionDocumentalDialogo.vue`) muestra:
- la versión revisada (archivo, SHA-256 abreviado y tamaño);
- el resultado vigente (y si corresponde a otra versión);
- quién registrará la revisión, con la fecha y hora del servidor;
- las opciones de resultado («Validado» deshabilitado sin archivo);
- observaciones (contador de 0 a 1000);
- el motivo del cambio, que solo aparece y es obligatorio si cambia el resultado;
- el **historial** en un panel desplegable, con la columna «Núm.».

Tras registrar, la página se recarga y muestra solo estados persistidos.

## 6. Protección del historial (solo inserción)

### 6.1 Base de datos
- Disparadores `BEFORE UPDATE OR DELETE` (por fila) y `BEFORE TRUNCATE` (por sentencia) que lanzan una excepción. Esto aplica también al rol propietario de la aplicación.
- Llaves foráneas `ON DELETE RESTRICT`. **No hay cascadas**: no se puede borrar una solicitud, un usuario ni una revisión anterior con historial dependiente.
- `REVOKE ALL ... FROM PUBLIC`.
- `down()` se niega con historial.

### 6.2 Aplicación
- Modelo `RevisionDocumental`:
  - `$timestamps = false`;
  - `save()` sobre un registro existente y `delete()` lanzan `LogicException`;
  - sin `SoftDeletes`.
- No hay rutas `PUT`, `PATCH` ni `DELETE`. La única escritura es `POST .../revisiones`, que **inserta**.
- Pruebas automáticas que intentan `UPDATE`, `DELETE`, `TRUNCATE` y eliminaciones en cascada (§11).

### 6.3 Límite conocido y endurecimiento recomendado
Hallazgo de la prueba en PostgreSQL desechable: **no se puede revocar `UPDATE` al rol propietario**. La verificación de la llave foránea autorreferida usa `SELECT … FOR KEY SHARE`, que requiere ese privilegio, y sin él toda inserción encadenada falla con `42501`.

Por eso, la protección efectiva frente al propietario son los **disparadores**.

**Advertencia expresa:** un **propietario del esquema o un superusuario** puede modificar deliberadamente estas protecciones: desactivar disparadores (`ALTER TABLE … DISABLE TRIGGER`), eliminar restricciones o borrar la tabla. Las protecciones impiden modificaciones mediante **operaciones administrativas ordinarias**, no frente a un administrador con privilegios de DDL que actúe deliberadamente. En esta intervención **no se alteraron** roles ni propietarios de PostgreSQL.

Endurecimiento recomendado, que requiere decisión del administrador de base de datos:
- separar el propietario del esquema (`rol_anfeca_esquema`) del rol de la aplicación;
- conceder al rol de la aplicación solo `SELECT, INSERT` sobre esta tabla;
- activar `log_statement = 'ddl'` para auditar cualquier `ALTER TABLE`.

## 7. Conservación del historial
- **Permanente:** las revisiones forman parte del expediente de certificación y no se depuran desde la aplicación.
- Cualquier depuración futura, por ejemplo tras un plazo legal de archivo que debe definir ANFECA, requiere un procedimiento formal con autorización, respaldo previo verificado y acta. **No** se hará con migraciones ni operaciones administrativas ordinarias.
- Si se corrige un error, se registra una **revisión nueva** con `motivo_cambio`. Nunca se edita la anterior.

## 8. Correspondencia futura con el PDM (no se implementa ahora)

| `revisiones_documentales` | `anfeca_modelo` (propuesta 6.3.0, alternativa C) |
|---|---|
| `solicitud_id` + `documento_clave` + `version_ruta` | `SOLICITUD_DOCUMENTO` (una fila por versión de archivo) |
| `resultado` | Nuevos valores del catálogo `ESTADO_DOCUMENTO` (Validado, Con observaciones, No válido) |
| `observaciones` | `ESTADO_SOLICITUD_DOCUMENTO.ESDO_OBSERVACION` (ampliar a **1000** caracteres: límite homologado con Vue, Laravel y PostgreSQL) |
| `coordinador_id` | Extensión `ESDO_ID_USUARIO` |
| `version_sha256` | Extensión `ESDO_HUELLA` |
| `revisado_en` | `ESDO_FECHA` |

El espejo se diseñará en la fase de integración con el PDM. Mientras tanto, `ANFECA_PDM_MODO=apagado`.

## 9. Procedimiento de aplicación, respaldo y recuperación (cuando se autorice)

Ambiente: 8086, base `bd_anfeca_ensayo_pruebas_pg`. **No se ejecuta** hasta recibir autorización definitiva.

> ⚠ **Migración pendiente ajena en 8086.** `2026_11_20_190000_add_escuela_facultad_to_instituciones_afiliadas` figura como pendiente. Por eso **nunca** debe ejecutarse `php artisan migrate` general, ni `migrate:fresh` o `db:wipe`, porque aplicaría o destruiría otros cambios. Solo se permite `--path` con el archivo exacto.

1. **Comprobaciones:** al menos 5 GB libres (criterio preventivo), rama `desarrollo/modulo-coordinacion` y commit autorizado.
2. **Respaldo verificado por restauración real** (ensayo realizado el 2026-11-25, §9.1):
   ```sh
   cd /var/www/ANFECA_ENSAYO
   bash scripts/anfeca/respaldo_verificado.sh bd_anfeca_ensayo_pruebas_pg desarrollo/modulo-coordinacion <commit>
   ```
   El script:
   - verifica la base, la rama y el commit;
   - ejecuta `pg_dump -Fc` y calcula su SHA-256;
   - **restaura** el respaldo en PostgreSQL 18.6 desechable sin red;
   - compara el inventario exacto del catálogo, el DDL normalizado, los conteos y la huella MD5 de **todas** las filas por tabla;
   - ejecuta Laravel (`migrate:status`) sobre la restauración.
   
   Si algo difiere, se detiene.
3. **Aplicación**, en un commit propio:
   - copiar la propuesta a `database/migrations/`;
   - ejecutar `php artisan migrate --path=database/migrations/2026_11_26_000000_create_revisiones_documentales_table.php --force`.
4. **Verificación:**
   - `\d+ revisiones_documentales`: restricciones, índices, 3 disparadores y la vista;
   - huellas de las tablas existentes idénticas a las del paso 2 (se repite el bloque de huellas del script);
   - un `UPDATE` de prueba dentro de `BEGIN … ROLLBACK` es rechazado.
5. **Activación** (decisión separada): `ANFECA_COORDINACION_REVISION=true` en el `.env` de 8086 y `php artisan config:clear`.
6. **Reversión:**
   - desactivar la bandera (efecto inmediato, sin tocar datos);
   - si la tabla está vacía, `php artisan migrate:rollback --path=…`, que `down()` permite;
   - si tiene historial, `down()` se niega y solo con autorización se restaura el respaldo del paso 2 en una base nueva, con comprobación y cambio controlado.
7. **Respaldo continuo:** la tabla entra en los `pg_dump` habituales. Para recuperar una revisión puntual se usa `pg_restore -t revisiones_documentales` sobre una base temporal. **Nunca** se reinsertan ni se actualizan filas.

### 9.0 Estado de la aplicación en 8086 (2026-11-25)

| Paso | Estado |
|---|---|
| Condiciones previas | ✔ 5.2 GB libres · rotación de `coordinador.nacional` verificada (contraseña nueva válida, anterior rechazada, 0 sesiones de la cuenta) · rama y commit autorizados · PDM `apagado` (`integracion_control` vacía) · bandera de revisión `false` · conexión efectiva `pgsql` 127.0.0.1:5432 `bd_anfeca_ensayo_pruebas_pg` · sin configuración en caché |
| Migración en la ruta autorizada | ✔ commit `66d40dc` (`database/migrations/2026_11_26_000000_…`) |
| Respaldo verificado inmediatamente antes | ✔ SHA-256 `5c981f12…`; restauración real idéntica (1429 elementos de catálogo, 74 tablas, 174 filas); Laravel sobre la restauración: 34 ejecutadas, 2 pendientes (esta y la ajena) |
| Ensayo en seco (`--pretend`) en 8086 | ✔ Solo la migración autorizada; sin `escuela_facultad` |
| **Ensayo completo sobre la restauración del respaldo** (clon del commit `66d40dc`, PostgreSQL 18.6 desechable) | ✔ `aplicar_revisiones_documentales.sh`: solo cambian `migrations` (38 → 39) y la tabla nueva vacía; `verificar_revisiones_documentales.sh`: 0 fallas; con datos reales restaurados: reintento idéntico reconocido, 409 por clave con otro contenido, UPDATE, DELETE, TRUNCATE y borrado de solicitud rechazados |
| **Aplicación en 8086** | ✔ Aplicada manualmente por el responsable del proyecto el 2026-11-25 a las 17:20 (169.87 ms): tabla y vista creadas, tabla vacía, 17 columnas, 3 llaves foráneas RESTRICT, 15 CHECK, 6 índices, 3 disparadores, 0 fallas, datos originales sin cambios. Respaldo: `respaldo_verificado_bd_anfeca_ensayo_pruebas_pg_20261125_171900` (SHA-256 `ce09dbcbdb3fa1c0…`). Verificación posterior en §13.1 |

Orden de aplicación en 8086 (el respaldo debe regenerarse en el commit vigente y tener menos de 2 horas):

```sh
cd /var/www/ANFECA_ENSAYO
bash scripts/anfeca/respaldo_verificado.sh bd_anfeca_ensayo_pruebas_pg desarrollo/modulo-coordinacion $(git rev-parse --short HEAD)
R=$(ls -d ~/ANFECA_RESPALDOS/respaldo_verificado_bd_anfeca_ensayo_pruebas_pg_* | tail -1)
ANFECA_AUTORIZO_MIGRACION=revisiones_documentales \
  bash scripts/anfeca/aplicar_revisiones_documentales.sh bd_anfeca_ensayo_pruebas_pg $(git rev-parse --short HEAD) "$R"
```

El script de aplicación:
- revisa todas las condiciones previas;
- ejecuta solo `migrate --path`;
- compara las huellas de **todas** las tablas antes y después, y se detiene si cambia algo más que `migrations` y la tabla nueva;
- ejecuta la verificación estructural.

Los resultados quedan en `$R/aplicacion/`.

### 9.1 Ensayo del respaldo verificado (2026-11-25, sin migrar)

| Comprobación | Resultado |
|---|---|
| Base / servidor | `bd_anfeca_ensayo_pruebas_pg`, PostgreSQL 18.6, 16 MB |
| `pg_dump -Fc` | 340 KB · SHA-256 `345925d930ddb9f6…` |
| Restauración real | Correcta en PostgreSQL 18.6 desechable sin red |
| Inventario del catálogo | **Idéntico**: 1429 elementos (602 columnas, 432 restricciones, 196 índices, 19 disparadores, 35 funciones, 74 tablas, 9 vistas, 62 secuencias) |
| DDL | Idéntico tras normalizar. 6 líneas difieren solo en la forma de las conversiones de `ANY(ARRAY[…])`, que PostgreSQL re-escribe al restaurar |
| Datos | Conteos y MD5 de filas **idénticos** en 74 tablas (174 filas) |
| Funcional | Laravel `migrate:status`: 34 ejecutadas y 1 pendiente (la ajena indicada arriba); consultas: 7 usuarios y 3 solicitudes |

## 10. Visualización segura del prototipo (6.3.0)

En 8086 la ruta responde 404 porque `ANFECA_COORDINACION_PROTOTIPOS=false`. **No se modifica** el `.env` de 8086. La propuesta es un entorno aislado y temporal:

```sh
cd /var/www/ANFECA_ENSAYO
bash scripts/anfeca/demo_prototipo/iniciar.sh   # crea ~/ANFECA_DEMO_PROTOTIPO y arranca en 127.0.0.1:8099
# en su equipo:  ssh -L 8099:127.0.0.1:8099 servidorweb@192.168.7.130
# navegador:     http://127.0.0.1:8099/login  →  /coordinacion/prototipos/revision-documental
bash scripts/anfeca/demo_prototipo/detener.sh   # elimina contenedor, copia y base ficticia
```

| Garantía | Mecanismo |
|---|---|
| Datos ficticios identificados | Zona «Z-DEMO», «Universidad FICTICIA…», «Persona ficticia» y el aviso «PROTOTIPO DEMOSTRATIVO · datos ficticios» |
| Sin persistencia | El prototipo no hace peticiones al servidor ni usa almacenamiento del navegador (verificado por prueba). La base de la demo es un SQLite desechable |
| Solo coordinación | Middleware `coordinacion`: solicitante 403, invitado → login (verificado) |
| Sin tocar solicitudes reales | No hay conexión a PostgreSQL; el contenedor de carga no tiene red |
| Temporal y restringido | Escucha solo en `127.0.0.1`; contraseña aleatoria de un solo uso en un archivo con permisos 600; `detener.sh` lo elimina todo |
| Sin cambios de infraestructura | Sin Apache, firewall ni SELinux |

Requisitos: unos 600 MB libres (el script lo verifica) y `public/build` compilado.

## 10.1 Revisión funcional en la demostración (6.3.1)

`iniciar.sh` también activa la revisión funcional sobre el SQLite desechable:
- aplica allí la migración propuesta;
- crea PDF **ficticios**;
- arranca con `ANFECA_COORDINACION_REVISION=true` solo dentro del contenedor.

Para usarla: `/coordinacion/solicitudes/1`.

## 11. Matriz de pruebas (27 casos + adicionales): todos automatizados

Archivos de prueba:
- **F:** `tests/Feature/RevisionDocumentalTest.php` (SQLite en memoria y disco falso);
- **E:** `tests/Feature/RevisionesDocumentalesEsquemaTest.php` (SQLite y PostgreSQL desechable);
- **C:** `tests/Pg/RevisionDocumentalConcurrenciaPgTest.php` (PostgreSQL desechable, procesos reales);
- **N:** `tests/Feature/CoordinacionTest.php`.

| # | Caso | Resultado esperado | Prueba |
|---|---|---|---|
| 1 | Validar un documento cargado | 201; coordinador de la sesión; huella y tamaño leídos del archivo; fecha del servidor | F `test_01` |
| 2 | Validar sin archivo o con archivo no localizado | 422; también lo rechaza la base | F `test_02`, E |
| 3 | Desfavorable sin observación, menor de 10 o mayor de 1000 | 422; 1000 se acepta | F `test_03`, E |
| 4 | Guardado parcial y nueva sesión | Indicadores idénticos | F `test_04_23` |
| 5 | Historial ordenado e inmutable | «Núm.» 1..n; el modelo impide `save()` y `delete()` | F `test_05_06` |
| 6 | Cambio de resultado | Exige motivo; la anterior se conserva | F `test_05_06`, E |
| 7 | No aplica | 404; `no_aplica` da 422 | F `test_07`, E |
| 8 | Adicionales art. 13/14 | Solo en refrendo aplicable; denominador 8 | F `test_08` |
| 9 | Nueva versión | Vuelve a «Por revisar»; no se traslada la validación | F `test_09` |
| 10 | Otra zona | 404 en GET y POST, sin inserción | F `test_10` |
| 11 | Coordinación nacional | Revisa cualquier zona, incluso sin zona | F `test_11` |
| 12 | Solicitante o invitado | 403 / 401 | F `test_12` |
| 13 | Borrador | 409 `estado`; sin botón «Revisar» | F `test_13`, E |
| 14 | Dos coordinadores sobre la misma vigente | 409 `concurrencia` con la vigente | F `test_14_22`, C |
| 15 | Integridad de los originales | Tablas y archivos (SHA-256) idénticos | F `test_15_24` |
| 16 | Parámetros manipulados | `por_revisar` y UUID inválido dan 422; campo inexistente 404; versión o vigente falsificadas 409 | F `test_16` (2) |
| 17 | Regresión de solicitantes y PDM | Suite completa (§12) | Todas |
| 18 | Archivo cambia entre consulta y guardado | 409 `version` | F `test_18`, C (carrera real) |
| 19 | Documento sin ruta o sin huella | Solo desfavorables; columnas nulas coherentes | F `test_19`, E |
| 20 | Modificar o eliminar el historial | Rechazado: UPDATE, DELETE, TRUNCATE, cascadas, rutas PUT/PATCH/DELETE y `down()` con datos | F `test_20`, E, C |
| 21 | Reintento idéntico | 200 con la misma fila, sin duplicar | F `test_21`, C (4 simultáneos) |
| 22 | Doble clic o dos pestañas | Una inserción; la otra recibe 409 | F `test_14_22`, C |
| 23 | Consistencia tras cerrar sesión | Indicadores idénticos con otra cuenta autorizada | F `test_04_23` |
| 24 | Integridad de las solicitudes | Sin cambios tras 4 revisiones | F `test_15_24` |
| 25 | Suplantación de rol | Rechazada por la base | E |
| 26 | Fecha forjada | Ignorada (aplicación y disparador de PG) | F `test_01`, E (PG) |
| 27 | La propuesta no corre con `migrate` | Fuera de la ruta y de `migrations` | E |
| A1 | Clave reutilizada con otro contenido (resultado, observación, documento, coordinador, solicitud) | 409 `idempotencia` | F `test_extra_clave…`, C |
| A2 | Permisos retirados antes de un reintento | 404 sin devolver la revisión previa | F `test_extra_permisos…` |
| A3 | Cambio de ruta con la misma huella | 409 `version` | F `test_extra_cambio_de_ruta…` |
| A4 | Cambio de estado de carga sin huella (no cargado a no localizado) | 409 `version` | F `test_extra_cambio_de_estado…` |
| A5 | Concurrencia real en PostgreSQL | 6 primeras y 6 sucesores simultáneos sin bifurcación | C |
| A6 | Aislamiento de archivos de prueba | Raíz de Laravel = árbol de pruebas, fuera de `/var/www`; disco falso dentro del árbol | F `test_aislamiento…`, `tests/TestCase.php` |
| A7 | Numeración del historial | «Núm.» 1..n | F `test_05_06` |
| A8 | Desactivada por omisión | 404 y expediente sin cambios | F `test_desactivada…` |
| A9 | Interfaz compacta | Sin formularios en tarjetas; indicadores; fichas 50/50 | F `test_interfaz…` |
| A10 | Ver con versión obsoleta | 409 | F `test_extra_ver…` |
| A11 | Límite de 1000 homologado | Vue, Laravel, PostgreSQL y SQLite | F `test_componente_vue…`, E |

## 12. Inventario de la implementación (fase 6.3.1)

| Pieza | Archivo |
|---|---|
| Bandera (apagada por omisión) y límites 10/1000 | `config/anfeca_coordinacion.php` (`revision_documental`, `revision_texto_*`) |
| Enumeraciones | `app/Enums/ResultadoRevision.php` (3 resultados guardados), `app/Enums/EstadoRevision.php` (+ por revisar, no aplica) |
| Modelo de solo inserción | `app/Models/RevisionDocumental.php` |
| Aplicabilidad y versión real | `app/Services/Coordinacion/DocumentosAplicables.php`, `VersionDocumento.php` |
| Servicio | `app/Services/Coordinacion/RevisionDocumental.php` (resumen, indicadores, detalle, historial, registrar) |
| Controlador y validación | `RevisionDocumentalController.php`, `app/Http/Requests/Coordinacion/RegistrarRevisionRequest.php` |
| Policy | `SolicitudCertificacionPolicy::revisarDocumentoComoCoordinacion` |
| Rutas (solo GET y POST) | `coordinacion.solicitudes.revision`, `.revisiones`, `.revisiones.registrar` (throttle 30/min) |
| Interfaz | `partials/documento.blade.php`, `expediente.blade.php`, `RevisionDocumentalDialogo.vue`, `coordinacion.css` v10 |

## 13. Activación y desactivación de la revisión documental (8086)

**Estado actual: DESACTIVADA** (`ANFECA_COORDINACION_REVISION` ausente en `.env`; la configuración efectiva es `false`). El visor «Ver» funciona con la bandera desactivada.

**Activación** (solo con autorización expresa):
1. Comprobar las condiciones: tabla vacía y verificada (`bash scripts/anfeca/verificar_revisiones_documentales.sh` = 0 fallas), PDM `apagado`, configuración sin caché.
2. Editar **solo** el `.env` local de `/var/www/ANFECA_ENSAYO` (nunca se publica) y agregar `ANFECA_COORDINACION_REVISION=true`.
3. Ejecutar `php artisan config:clear`. Si el entorno usa caché de configuración: `php artisan config:cache`.
4. Verificar, con una sesión de coordinación nacional:
   - `GET /coordinacion/solicitudes/3/revision` → 200 en JSON;
   - el expediente muestra la línea de indicadores y los botones «Revisar»;
   - el diálogo abre el visor al 70 % y el panel al 30 %.
5. No se registran revisiones de ejemplo sobre los folios 3, 4 y 5. La aceptación la hace el responsable desde el navegador.

**Desactivación** (inmediata y sin pérdida de datos):
1. Quitar la línea o cambiarla a `ANFECA_COORDINACION_REVISION=false`.
2. Ejecutar `php artisan config:clear`.
3. Las rutas de revisión vuelven a responder 404 y el expediente vuelve a la vista de solo carga.
4. Las revisiones registradas **se conservan** (historial de solo inserción).

El prototipo demostrativo sigue desactivado (`ANFECA_COORDINACION_PROTOTIPOS=false`). Solo se usa en la demostración desechable de `scripts/anfeca/demo_prototipo/`.

### 13.1 Verificación posterior a la migración (2026-11-25)

| Comprobación | Resultado |
|---|---|
| `verificar_revisiones_documentales.sh` | 0 fallas (tabla vacía, vista, PK, 3 FK RESTRICT, 15 CHECK, índices, 3 disparadores, TRUNCATE rechazado, PUBLIC sin privilegios, migración ajena sin aplicar) |
| 20 documentos originales de los folios 3, 4 y 5 | SHA-256 y tamaño **idénticos** a la línea base previa; HTTP 200 por la ruta protegida |
| Tablero, bandeja y expedientes 3, 4 y 5 | HTTP 200; sin errores de JavaScript |
| Páginas de solicitantes (`/login`, `/registro/datos-personales`, `/recuperar`) | 200 |
| `laravel.log` | Sin errores nuevos desde el incidente documentado del 2026-11-25 (18:32 UTC) |
| PDM / bandera / prototipo | `apagado` / `false` / `false` |
