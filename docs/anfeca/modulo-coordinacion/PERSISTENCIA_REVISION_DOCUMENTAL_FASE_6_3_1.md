# Fase 6.3.1 · Diseño técnico de la persistencia de la revisión documental

> **Estado: PROPUESTA PENDIENTE DE AUTORIZACIÓN.** La migración está **preparada y probada solo en bases desechables**. **No se ha ejecutado** en la base del ambiente 8086 (`bd_anfeca_ensayo_pruebas_pg`). El PDM permanece **apagado** y **no** hay espejo hacia `anfeca_modelo`.

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
    CONSTRAINT rd_observaciones_largo_ck CHECK (observaciones IS NULL OR char_length(observaciones) <= 2000),
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
| `observaciones` | Sí | Obligatoria (≥ 10 caracteres sin espacios extremos) en `con_observaciones` y `no_valido`; máximo 2000 |
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

### 5.1 Concurrencia y verificación de versión (HTTP 409)

El formulario de revisión envía tres datos de control, obtenidos al **mostrar** el documento:

1. `revision_vigente_id`: id de la revisión vigente que vio el coordinador, o nulo.
2. `version_sha256`: huella del archivo que abrió el coordinador. El enlace «Ver» lleva `?v=<sha256>` y la descarga responde 409 si la versión ya cambió.
3. `clave_idempotencia`: UUID del formulario.

Dentro de una transacción, el servicio hace lo siguiente:

1. Si `clave_idempotencia` ya existe y es del mismo coordinador, devuelve **200** con esa revisión (reintento). Si es de otro coordinador, devuelve **409**.
2. Recalcula la vigente actual. Si difiere de `revision_vigente_id`, devuelve **409** «otra persona registró una revisión» junto con la vigente.
3. Recalcula la ruta y la huella del archivo actual. Si la huella difiere de `version_sha256` o el archivo cambió de estado, devuelve **409** «el archivo cambió desde que lo consultó». Así nadie valida una versión distinta de la que vio.
4. Inserta con `revision_anterior_id` igual a la vigente.
5. Si la base rechaza por `rd_sucesor_unico_uq`, `rd_primera_revision_uq` o `rd_idempotencia_uq`, por una carrera entre los pasos 2 y 4, la excepción se traduce a **409**. **La base garantiza que la cadena no se bifurque** aunque la aplicación falle.

No se bloquean filas de `solicitudes_certificacion`: el control es optimista y las restricciones únicas son la red de seguridad.

### 5.2 Integración en la interfaz (sin perder la compactación 6.2.4)

Se mantienen sin cambios:
- las fichas 50/50 de igual altura y su orden;
- la cuadrícula 3 × 2 de documentos de unos 58 px;
- los adicionales del artículo 13/14 en horizontal;
- el encabezado, el pie y el menú lateral.

Cada tarjeta de documento añade, **en la misma fila**:
- una segunda insignia de **resultado** («Por revisar», «Validado», «Con observaciones», «No válido»), distinta de la de **carga**;
- un botón compacto «Revisar», junto a «Ver».

El registro se hace en un **diálogo modal**, como en el prototipo:
- opciones de resultado;
- observaciones y motivo del cambio cuando proceden;
- versión revisada (nombre y huella abreviada).

El historial se consulta en un panel desplegable `<details>` dentro del diálogo, **no** en la tarjeta. La altura permanente del expediente aumenta como máximo en una línea de indicadores de revisión en el encabezado de la sección «Documentos»: aplicables, por revisar, validados, con observaciones, no válidos y avance.

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

Por eso, la protección efectiva frente al propietario son los **disparadores**. Un propietario o superusuario podría desactivarlos deliberadamente con `ALTER TABLE … DISABLE TRIGGER`. Esa operación no es ordinaria y queda fuera de este alcance.

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
| `observaciones` | `ESTADO_SOLICITUD_DOCUMENTO.ESDO_OBSERVACION` (ampliar a 1000 o más) |
| `coordinador_id` | Extensión `ESDO_ID_USUARIO` |
| `version_sha256` | Extensión `ESDO_HUELLA` |
| `revisado_en` | `ESDO_FECHA` |

El espejo se diseñará en la fase de integración con el PDM. Mientras tanto, `ANFECA_PDM_MODO=apagado`.

## 9. Procedimiento de aplicación, respaldo y recuperación (cuando se autorice)

Ambiente: 8086, base `bd_anfeca_ensayo_pruebas_pg`. No se ejecuta hasta recibir autorización expresa.

1. **Espacio:** `df -h /` con al menos 1 GB libre (la base pesa ≈ 16 MB; el respaldo, menos de 5 MB).
2. **Respaldo previo verificado:**
   ```sh
   pg_dump -Fc -d bd_anfeca_ensayo_pruebas_pg -f ~/ANFECA_RESPALDOS/ensayo_pre_6_3_1_<fecha>.dump
   pg_restore -l ~/ANFECA_RESPALDOS/ensayo_pre_6_3_1_<fecha>.dump > /dev/null   # verifica legibilidad
   sha256sum ~/ANFECA_RESPALDOS/ensayo_pre_6_3_1_<fecha>.dump > ….sha256
   ```
3. **Huella de datos previa:** `scripts/anfeca/huella_bd.sh` (conteos de tablas existentes).
4. **Aplicación:** copiar la propuesta a `database/migrations/` en un commit propio y ejecutar `php artisan migrate --path=database/migrations/2026_11_26_000000_create_revisiones_documentales_table.php --force` **solo** en 8086.
5. **Verificación:**
   - `\d+ revisiones_documentales`: restricciones, índices y disparadores presentes;
   - la huella de datos posterior es idéntica en las tablas existentes;
   - un `UPDATE` de prueba dentro de `BEGIN … ROLLBACK` es rechazado.
6. **Reversión:**
   - si la tabla está vacía, `php artisan migrate:rollback --step=1`, que `down()` permite;
   - si tiene historial, `down()` se niega y se restaura desde el respaldo **solo** con autorización: `pg_restore` sobre una base nueva, comprobación y cambio controlado.
7. **Respaldo continuo:** la tabla entra en los respaldos `pg_dump` habituales. Para recuperar una revisión puntual se usa `pg_restore -t revisiones_documentales` sobre una base temporal y se consulta. **Nunca** se reinsertan filas con UPDATE.

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

## 11. Matriz de pruebas actualizada

Estado: **E** = automatizada y pasando hoy (restricciones de la migración propuesta); **6.3.1** = se implementa con el servicio y el controlador.

| # | Caso | Resultado esperado | Estado |
|---|---|---|---|
| 1 | Validar un documento cargado | Fila con coordinador, fecha, ruta, huella y bytes | E (esquema) / 6.3.1 |
| 2 | Validar un documento no cargado o no localizado | 422; la base también lo rechaza | E / 6.3.1 |
| 3 | Desfavorable sin observación o con menos de 10 caracteres | 422; la base también lo rechaza | E / 6.3.1 |
| 4 | Guardado parcial y nueva sesión | Avance recuperado desde la vista de vigentes | 6.3.1 |
| 5 | Historial | Todas las revisiones, en orden e inmutables | E / 6.3.1 |
| 6 | Cambio de resultado | Exige motivo; la anterior se conserva | E / 6.3.1 |
| 7 | Documento no aplicable | 404; no se puede revisar ni guardar «no_aplica» | E (no_aplica rechazado) / 6.3.1 |
| 8 | Adicionales del artículo 13/14 | Solo en refrendo con `aplica_articulo_14` | 6.3.1 |
| 9 | Nueva versión (huella distinta) | Vuelve a «Por revisar»; la validación anterior no se traslada | 6.3.1 |
| 10 | Otra zona (GET/POST) | 404, sin inserción | 6.3.1 |
| 11 | Coordinación nacional | Revisa todas las zonas, incluidas las pendientes | 6.3.1 |
| 12 | Solicitante | 403 | 6.3.1 |
| 13 | Solicitud en borrador | 409; la base también lo rechaza | E / 6.3.1 |
| 14 | Concurrencia sobre la misma vigente | La primera se registra; la segunda recibe 409 y la base impide la bifurcación | E (bifurcación) / 6.3.1 |
| 15 | Integridad de originales | Filas de solicitud y archivos idénticos (huellas) | E (fila) / 6.3.1 |
| 16 | Parámetros manipulados (`no_aplica`, `por_revisar`, clave inexistente, rol falso) | 422 o 404; la base también lo rechaza | E / 6.3.1 |
| 17 | Regresión de solicitantes y del PDM | Sin cambios | E |
| 18 | **Archivo cambia entre la consulta y el guardado** | 409 «el archivo cambió»; sin inserción | 6.3.1 |
| 19 | **Documento sin ruta o sin huella** | Solo resultados desfavorables; huella o ruta inválida rechazada | E |
| 20 | **Intento de modificar o eliminar el historial** (UPDATE, DELETE, DELETE masivo, TRUNCATE, cascada desde solicitud o usuario, `down()` con datos) | Todos rechazados; filas idénticas | E |
| 21 | **Reintento con la misma clave de idempotencia** | Sin duplicado; 200 con la fila existente (mismo coordinador) | E (unicidad) / 6.3.1 |
| 22 | **Doble clic o dos pestañas** (misma vigente, claves distintas) | Una inserción; la otra recibe 409 | E (sucesor único) / 6.3.1 |
| 23 | **Consistencia tras cerrar sesión** | Al volver a entrar, los indicadores y la vigente son iguales a los de antes del cierre | 6.3.1 |
| 24 | **Integridad de las solicitudes** | `solicitudes_certificacion`, formación y documentos sin cambios tras N revisiones | E (fila) / 6.3.1 |
| 25 | **Suplantación del rol** (usuario solicitante declarado como coordinación) | Rechazado por la base | E |
| 26 | **Fecha forjada por el cliente** | Ignorada; `revisado_en` = hora de la base | E (PG) |
| 27 | **La propuesta no se ejecuta con `migrate`** | No está en la ruta de migraciones ni en la tabla `migrations` | E |

Pruebas automatizadas hoy: `RevisionesDocumentalesEsquemaTest`, 14 pruebas, pasando en SQLite en memoria (13, más 1 omitida porque la regla de fecha es exclusiva de PostgreSQL) y en PostgreSQL 18.6 desechable (14).
