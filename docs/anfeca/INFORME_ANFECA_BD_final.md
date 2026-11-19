# Informe técnico — ANFECA_BD_final

> **Nota (20/11/2026):** este informe se elaboró sobre `main` (03/nov). El estado vigente del código, los parches y las vulnerabilidades está en `INFORME_INTEGRACION.md`, sobre `desarrollo/anfeca-pruebas` @ `6c873c2`.

**Proyecto:** Sistema de Registro, Certificación y Refrendo Académico ANFECA (ANFECA_PRUEBAS)
**Fecha:** 20/11/2026 · **Rama:** `ANFECA_BD_final` (desde `main`, commit `8549d09`)
**Alcance acordado:** solo cambios **aditivos**. Integración aislada del PDM, verificación, documentación, parches propuestos y evaluación de vulnerabilidades.

---

## 1. Resumen ejecutivo

1. **El repositorio está desfasado respecto al servidor.**
   - GitHub termina el **03/nov**: 15 migraciones y ningún controlador de solicitud, refrendo, aceptación o acuse.
   - El servidor `/var/www/ANFECA_PRUEBAS` tiene el trabajo del 04 al 19/nov **sin commit**.
   - Es el mayor riesgo actual: un fallo del disco o de la VM perdería dos semanas de trabajo validado.
   - **Primera recomendación:** respaldar y subir ese código a una rama propia (ver §6).
2. **El PDM de PowerDesigner quedó integrado de forma aislada y probada.**
   - Esquema `anfeca_modelo` con 36 tablas y 35 FK.
   - Comandos `anfeca:modelo-instalar` (en seco por defecto, en una sola transacción) y `anfeca:modelo-verificar` (solo lectura, con mapeo PDM↔Laravel).
   - Se probó en PostgreSQL 16 real: `public` no cambia, la segunda ejecución se rechaza y un fallo a media instalación se revierte por completo.
3. **Dos fallos funcionales confirmados por ejecución** en el código del repo:
   - El **registro de usuarios responde 500** (`Undefined variable $identidadAutorizada`).
   - `/registro` responde 500 (la vista `registro.create` no existe).
   - Hay que comprobar si el servidor los conserva (parches P1 y P4).
4. **La regla de «Modificar datos» del repo está invertida** respecto a la acordada. Hay parche listo (P2).
5. **Vulnerabilidades:**
   - No hay hallazgos críticos de inyección: hay CSRF en todos los formularios, ningún `{!! !!}` ni `v-html`, y las consultas usan parámetros enlazados.
   - Lo prioritario es la configuración de exposición (`APP_DEBUG`, HTTP sin TLS) y la actualización de dependencias Symfony y Laravel con avisos publicados.

---

## 2. Qué se hizo en la rama `ANFECA_BD_final`

| Archivo nuevo | Propósito |
|---|---|
| `database/sql/anfeca_modelo/anfeca_modelo.sql` | PDM ejecutable y aislado. Todo calificado con `anfeca_modelo.`, sin `DROP`, sin `search_path`, con `CREATE SCHEMA` no idempotente a propósito. |
| `database/sql/anfeca_modelo/anfeca_certifica_1.original.sql` | Copia literal del PDM con el encabezado **NO EJECUTAR** |
| `database/sql/anfeca_modelo/ANFECA_PDM_CATALOGO_TABLAS.json` | Inventario de columnas y de las 35 FK |
| `config/anfeca_modelo.php` | Esquema, BD permitida (`ANFECA_MODELO_BD_PERMITIDA`), totales esperados y mapeo PDM↔Laravel |
| `app/Services/AnfecaModeloInspector.php` | Consultas de solo lectura al catálogo PostgreSQL y revisión estática del script |
| `app/Console/Commands/AnfecaModeloInstalar.php` | `anfeca:modelo-instalar [--ejecutar] [--confirmar]` |
| `app/Console/Commands/AnfecaModeloVerificar.php` | `anfeca:modelo-verificar [--solo-divergencias]` |
| `tests/Feature/AnfecaModeloSqlTest.php` | 10 pruebas: estructura del SQL y protecciones de los comandos |
| `docs/anfeca/MAPEO_PDM_LARAVEL.md` | Mapeo, campos que no se deben perder y observaciones al PDM |
| `docs/anfeca/PASOS_SERVIDOR.md` | Procedimiento paso a paso con respaldo, instalación, verificación y reversión |
| `docs/anfeca/PARCHES_PENDIENTES.md` | 9 correcciones con código, prioridad y prueba |

**No se modificó ningún archivo existente.**

### Protecciones del comando de instalación (en orden)

1. El esquema no puede ser `public`.
2. Revisión estática del script, que rechaza lo siguiente:
   - `DROP`, `TRUNCATE`, `DELETE`, `UPDATE`, `INSERT`, `GRANT` o `search_path`;
   - referencias a `public`;
   - objetos sin calificar.
3. El script debe contar exactamente 36 tablas y 35 FK.
4. La conexión debe ser `pgsql`.
5. La BD conectada debe ser `bd_laravelanfeca_pruebas`, lo que protege `/var/www/ANFECA`.
6. El esquema no debe existir todavía.
7. Sin `--ejecutar` no modifica nada; con `--ejecutar` pide confirmación.
8. Todo ocurre en **una sola transacción**. Al final se comprueban 36 tablas, 35 FK y que la lista de tablas de `public` sea idéntica; si algo no cuadra, se revierte todo.

### Cambios aplicados al SQL de la propuesta

| Cambio | Motivo |
|---|---|
| Objetos calificados con `anfeca_modelo.` | El script ya no depende de `search_path`. Con `SET LOCAL` fuera de una transacción, la propuesta original habría creado las tablas en `public`. |
| `BEGIN/COMMIT` retirados | La transacción la pone el comando o `psql -1` |
| Sin `IF NOT EXISTS` | Repetir la ejecución falla limpio |

Se mantienen las dos correcciones de la propuesta: el índice inválido `TIENE_ARTICULO_FK` y `RELATIONSHIP_27_FK` como índice no único.

---

## 3. Verificaciones realizadas (evidencia)

Entorno: contenedor con PostgreSQL 16.15 y Laravel 13.43. Las dependencias se resolvieron en una copia aparte, porque el `composer.lock` exige PHP ≥ 8.4.1 y el contenedor tiene PHP 8.3; el lock del repo **no se modificó**.

| # | Prueba | Resultado |
|---|---|---|
| 1 | `php -l` de los 5 archivos PHP nuevos | Sin errores |
| 2 | `php artisan test` | 11/12 pasan; las **10 nuevas pasan**. La única que falla es `ExampleTest`, preexistente (P9). |
| 3 | `php artisan migrate` del repo en PG16 | 15 tablas en `public` |
| 4 | `psql -1 -v ON_ERROR_STOP=1 -f anfeca_modelo.sql` | 36 tablas, 35 FK y 96 índices en `anfeca_modelo`; **`public` idéntico** |
| 5 | Segunda ejecución del SQL | `schema "anfeca_modelo" already exists`; sin cambios |
| 6 | `anfeca:modelo-instalar --ejecutar` contra `otra_bd` | **Rechazado** (BD no permitida) |
| 7 | `anfeca:modelo-instalar` en seco | Comprobaciones correctas; el esquema no se crea |
| 8 | `anfeca:modelo-instalar --ejecutar --confirmar` | 36 tablas, 35 FK, `public` sin cambios |
| 9 | Repetir el punto 8 | **Rechazado** (el esquema ya existe) |
| 10 | Script con una FK rota a propósito | Error y **reversión total**: el esquema no queda creado |
| 11 | `anfeca:modelo-verificar` | 36/36 y 35/35; 10 de 21 entradas de mapeo con divergencias, esperadas porque faltan las tablas del servidor |
| 12 | SQL de reversión del documento | Con el esquema vacío solo afecta a `anfeca_modelo.*`. Bloquea si hay filas o si una vista o FK de `public` depende del esquema. |
| 13 | `POST /registro/datos-personales` (repo) | **500 `Undefined variable $identidadAutorizada`** → P1 |
| 14 | `GET /registro` (repo) | **500 `View [registro.create] not found`** → P4 |

**No probado aquí:**
- El código del servidor (04–19/nov).
- Las pantallas Vue en el navegador.
- El envío de solicitudes y el acuse.
- La ejecución en `bd_laravelanfeca_pruebas` real.

Todo esto se hace con `PASOS_SERVIDOR.md`.

---

## 4. Evaluación de vulnerabilidades

Escala: **Alta / Media / Baja / Informativa**. Se evaluó el código del repo (03/nov) y la configuración conocida; hay que reconfirmar cada punto en el servidor.

| ID | Severidad | Hallazgo | Evidencia | Recomendación |
|---|---|---|---|---|
| V1 | **Alta** | **Modo depuración y entorno `local` expuestos en la red.** Con `APP_DEBUG=true` cualquier error muestra la traza, rutas, consultas SQL y variables. Con `APP_ENV=local` se activan las rutas `/preview/*`, que muestran pantallas sin autenticación. | `.env.example` (`APP_ENV=local`, `APP_DEBUG=true`); `routes/web.php` (bloque `preview`). El fallo 500 del registro mostraría la traza completa. | En el servidor de pruebas compartido usar `APP_ENV=staging` y `APP_DEBUG=false`. Verificar con `php artisan about`. |
| V2 | **Alta** | **Tráfico HTTP sin TLS.** Contraseñas, sesiones, CURP, RFC y documentos viajan en claro por la LAN (`http://192.168.7.130:8084`). | Contexto §2 | HTTPS con certificado interno (mod_ssl); después `SESSION_SECURE_COOKIE=true`. No exponer el puerto a Internet. |
| V3 | **Media** | **Dependencias con avisos de seguridad** en el `composer.lock` del repo: `laravel/framework` 13.38 (CVE-2026-128083, se corrige en ≥ 13.40) y `symfony/http-foundation`, `http-kernel` y `routing` 8.1.9 (CVE-2026-121619, -118514, -118516, -123339 y dos sin CVE; se corrigen en ≥ 8.1.11). Afectan a proxies de confianza, cabecera `Host` y reglas por ruta. | `composer audit --locked`. `npm audit`: 0. | En el servidor (que reporta Laravel 13.42), respaldar `composer.lock` y luego `composer audit` y `composer update "symfony/*" laravel/framework --with-dependencies`. Repetir las pruebas. |
| V4 | **Media** | **Integridad de identidad:** la regla de edición está invertida. Antes de autorizar se pueden cambiar CURP, RFC, nombre y correo (también `users.email`, que es la credencial de acceso). | `DatosSolicitanteController::update` | Parche P2 (servidor + Vue). |
| V5 | **Media** | **Disponibilidad:** el registro y `/registro` fallan con 500. Con V1 activo, además filtran información. | Pruebas 13 y 14 | Parches P1 y P4. |
| V6 | Media | **Archivos:** se valida el contenido real (`mimes`, vía finfo), el tamaño (5 MB) y el disco privado. La descarga comprueba la propiedad por `user_id`, sin IDOR. Pero los archivos reemplazados quedan huérfanos y sin versión, no se guarda hash y los PDF pueden llevar JavaScript. | `FormacionAcademicaController`, `DocumentoProbatorioController` | P8 (historial + sha256). Servir siempre con `Content-Disposition: attachment`, como ya se hace. Valorar un escaneo con ClamAV. |
| V7 | Baja | **Política de contraseñas inconsistente:** mínimo 12 en el registro y 8 en la recuperación. | `RecuperacionPasswordController` | P6. Considerar `Password::min(12)->uncompromised()` si hay salida a Internet. |
| V8 | Baja | **Fuerza bruta:** `throttle:5,1` limita solo por IP y ruta. | `routes/web.php` | `RateLimiter` por correo + IP para el login y la recuperación. |
| V9 | Baja | **Enumeración de cuentas:** el registro revela si un correo, CURP o RFC ya existe (regla `unique`). La recuperación responde de forma uniforme, lo cual es correcto. | `store()` | Aceptable en esta etapa; documentarlo. |
| V10 | Baja | **Higiene del repositorio:** el `.gitignore` tiene marcadores de conflicto (`<<<<<<<`), aunque `.env` sigue ignorado. Hay 24 respaldos `.antes_*`/`.bak`/`respaldo` versionados; 3 están en `public/css` y se sirven públicamente. Hay archivos sueltos `0`, `sin_vinculacion` y `tructura_formacion_anfeca'` (salida de `less`). | `git ls-files` | Resolver el `.gitignore` y retirar los respaldos del repo, conservándolos fuera de `public/`. Hacerlo en una rama propia después de sincronizar con el servidor. |
| V11 | Baja | `GET /registro/datos-personales` es accesible con sesión iniciada. | `routes/web.php` | P5 |
| V12 | Info | **PDM:** `USUA_CONTRASENIA VARCHAR(255)` no indica hash. `MONEY` depende del locale. Faltan unicidades (CURP, RFC, usuario, folios). | SQL PDM | Mantener la autenticación de Laravel (bcrypt, 12 rondas). Corregirlo en PowerDesigner antes de cualquier convergencia. |
| V13 | Info | **Sesión:** `SESSION_ENCRYPT=false`, sesiones en BD, `http_only` y `same_site=lax`. | `config/session.php` | `SESSION_ENCRYPT=true` cuando haya HTTPS. |

**Controles verificados como correctos:**
- `@csrf` en todos los formularios activos.
- Sin `{!! !!}` ni `v-html` en los archivos activos.
- `whereRaw` siempre con parámetros enlazados.
- `$fillable` en todos los modelos.
- `session()->regenerate()` al iniciar sesión e `invalidate()` + `regenerateToken()` al cerrar.
- Los tokens de recuperación caducan a los 60 min y se reenvían con un mínimo de 60 s.
- Las descargas se limitan al propietario.
- El middleware `datos.autorizados` protege las etapas.

---

## 5. Recomendaciones priorizadas

### Inmediatas (antes de seguir con las pruebas de usuarios)

1. **Respaldar y versionar el servidor.**
   - `pg_dump -Fc` con prueba de restauración (PASOS_SERVIDOR §1).
   - Subir el código del servidor a una rama nueva, por ejemplo `servidor-2026-11-19`. Antes del commit, comprobar que `.env`, `respaldos_*` y los documentos subidos **no** entren.
2. Aplicar **P1** (registro) y **P2** (regla de edición) sobre el código del servidor, con las pruebas de cada parche.
3. **V1:** desactivar `APP_DEBUG` en el servidor compartido.
4. Aplicar la migración pendiente del compromiso (`2026_11_19_173517`):
   - después del respaldo;
   - con `migrate --pretend`;
   - una sola migración, con `--path`.

### Corto plazo

5. Instalar el esquema aislado: `anfeca:modelo-instalar` en seco, luego `--ejecutar`, luego `anfeca:modelo-verificar`. Compartir la tabla de divergencias, ya con las tablas del servidor.
6. Volver a ejecutar la prueba 3.5.3 de refrendo (mensajes visibles) y completar la 3.5.4 de formación en refrendo.
7. **V3:** actualizar las dependencias con avisos, con respaldo del lock y una regresión completa.
8. **V2:** HTTPS interno.

### Mediano plazo

9. Fase 1 del mapeo PDM: decidir campo por campo, con `config/anfeca_modelo.php` como fuente. Primeras migraciones aditivas:
   - `datos_solicitantes.activo`;
   - historial de estados de la solicitud;
   - sha256 y versión en los documentos.
10. P8: historial de versiones de archivos.
11. Pruebas automatizadas de los flujos inicial y refrendo, del aislamiento entre usuarios y del envío.
12. Limpieza del repo (V10) y consolidación de los CSS «compactos».

---

## 6. Siguiente paso concreto

Ejecutar `docs/anfeca/PASOS_SERVIDOR.md` hasta el **Paso 4 (en seco)** y compartir la salida. Mientras tanto, hacer commit del código del servidor en una rama propia para poder aplicar los parches sobre la versión real.
