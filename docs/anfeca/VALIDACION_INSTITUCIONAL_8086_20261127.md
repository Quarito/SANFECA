# Validación institucional de 8086 · certificacion_anfeca, PHP 8.5.12 y PostgreSQL 18 · 2026-11-27

Primera ejecución **en el servidor real** de los controles que los PR #9–#16 dejaron preparados en laboratorio. Esta ejecución no conmutó 8086 ni modificó 8008, porque hay cuatro condiciones críticas sin resolver (§8).

**Dictamen: DESPLIEGUE NO APROBADO.** 8086 sigue operando con su configuración actual:
- PHP-FPM 8.4.27;
- base `bd_anfeca_ensayo_pruebas_pg`;
- PDM apagado.

El artefacto candidato, el runtime PHP 8.5.12 y el procedimiento de despliegue quedaron preparados y probados en aislamiento.

## 1. Diagnóstico técnico (Fase A, solo lectura)

| # | Elemento | 8086 (`/var/www/ANFECA_ENSAYO`) | 8008 (`/var/www/anfeca`) |
|---|---|---|---|
| 1–2 | VirtualHost, DocumentRoot | `/etc/httpd/conf.d/anfeca-ensayo.conf`: `Listen 8086`, `DocumentRoot /var/www/ANFECA_ENSAYO/public`, `AllowOverride All`, acceso por IP | **Sin VirtualHost y sin proceso en el puerto 8008** (`ss -lnt`); `APP_URL=http://localhost:8000` |
| 3 | Pool PHP-FPM | Global `www` (`/etc/php-fpm.d/www.conf`): usuario `apache`, socket `/run/php-fpm/www.sock`. También atiende 8084 y 8085 | Sin pool propio |
| 4 | PHP CLI, FPM, Composer | PHP 8.4.27 del sistema (CLI y FPM); Composer `/usr/local/bin/composer` y `/usr/bin/composer` | Mismo PHP del sistema; Laravel 13.37.0 |
| 5–7 | PostgreSQL | 18.6, `127.0.0.1:5432`, rol `rol_anfecalaravel` (sin CREATEDB ni superusuario), base `bd_anfeca_ensayo_pruebas_pg`, `system_identifier` 7697381974958512424 | **Mismo clúster** (7697381974958512424), rol `user_anfeca`, base `bd_anfeca` (UTF8, es_MX.UTF-8) |
| 8 | Servicios compartidos | Apache, el pool `www` y el clúster 5432 se comparten con 8084, 8085 y 8008 | — |
| 9 | Laravel | `APP_ENV=production`, `APP_DEBUG=false`, caché y sesiones en archivo, cola `sync`, correo `array`, configuración sin caché | `APP_ENV=local`; sesión y cola en base de datos |
| 10 | Migraciones | 35 ejecutadas y 1 pendiente (`escuela_facultad`, no autorizada) | 6 |
| 12 | Esquemas | `public` (26 tablas), `anfeca_modelo` (44 tablas: 36 base y 8 de extensiones), `anfeca_compat` | Solo `public` (15 tablas) |
| 13 | PDM | `ANFECA_PDM_MODO=apagado`; existe un `config/anfeca_pdm_decisiones.php` local, sin commit, con decisiones marcadas como ficticias | — |
| 14 | Archivos privados | `storage/app/private/anfeca`: `apache:apache`, `drwx--S---` (solo `apache` puede leerlos) | Sin documentos |
| 15 | Catálogos | Licenciaturas 9 386 (SHA-256 `7bc8c998…`) y posgrados 1 794 (`ed15f4f2…`); 0640 `servidorweb:apache` | — |
| 16 | SELinux | `Enforcing`; `httpd_can_network_connect=off`, `httpd_can_network_connect_db=on`; `storage` con `httpd_sys_rw_content_t` | — |
| 17 | Espacio | 32.7 GB libres en `/` | — |
| 18 | Cron y Scheduler | Sin crontab del usuario; temporizadores solo del sistema | — |
| 20 | Rama y commit | `desarrollo/modulo-coordinacion` `ddd4c8d`, con cambios locales sin commit (ver §7) | `main` `227060e`, con cambios locales sin commit |

Sistema: AlmaLinux **10.2** (la instrucción mencionaba 9). La cuenta de servicio no tiene `sudo`.

## 2. Respaldo y recuperación (Fase B)

| Elemento | Resultado |
|---|---|
| Respaldo de PostgreSQL de una sola instantánea (script del PR #14) | `~/ANFECA_RESPALDOS/respaldo_verificado_bd_anfeca_ensayo_pruebas_pg_20261127_084134/` · `pg_dump -Fc` 360 KB · SHA-256 `9b183bcd…` |
| Restauración en PostgreSQL 18.6 desechable sin red | Correcta. Inventario idéntico (1 520 objetos); DDL idéntico, salvo 11 líneas que difieren solo en la forma de `ANY(ARRAY[...])`; **75 tablas y 200 filas con huellas idénticas** |
| Documentos privados (20) | `~/ANFECA_RESPALDOS/certificacion_8086_20261127/documentos/`. Se copiaron por HTTP como coordinación, porque el directorio pertenece a `apache`. Sus huellas son idénticas a las del punto de recuperación del 26-nov |
| Catálogos, `.env`, compilación, cambios locales y configuración de Apache y FPM | En la misma carpeta (600/700) |
| Dimensionamiento | La base ocupa 16 MB y el respaldo 360 KB; el tmpfs de 800 MB del script basta para este origen. Para 8008 debe medirse aparte |

## 3. PHP 8.5.12 (Fase C), en un entorno paralelo

La imagen `localhost/anfeca-php-fpm:8.5.12` se construyó con `scripts/anfeca/runtime/Dockerfile.php8512`. La base está fijada por digest y Composer en 2.11.3. Hubo que **calificar los nombres de imagen**, porque Podman en AlmaLinux no resuelve nombres cortos como `composer:2`.

| Verificación (PHP 8.5.12 CLI/FPM) | Resultado |
|---|---|
| `composer validate`, `composer install` desde `composer.lock` y `check-platform-reqs --lock` | PASÓ; `composer.lock` sin cambios |
| `phpunit -c phpunit.xml` (SQLite) | **204 pruebas, 2 468 aserciones, 1 omitida** |
| `phpunit -c phpunit.pgsql.xml` (PostgreSQL 18.6 desechable) | **79/79**, 1 234 aserciones (migración, modos apagado, espejo, principal y exclusivo, exportación y reversión con datos sintéticos) |
| `phpunit -c phpunit.revision-pg.xml` | **22/22** |
| Acuse PDF (Dompdf, `memory_limit=512M`) | 5/5 pruebas de acuse; la respuesta es `application/pdf` |
| `php-fpm -t` y `probar_php_fpm.sh` (FastCGI real) | PASÓ: PHP-FPM 8.5.12, extensiones, Laravel sobre `certificacion_anfeca` y `GET /login` |
| Artisan `about` | Laravel 13.42.0 sobre PHP 8.5.12 |
| Vite (`npm ci --ignore-scripts`, `npm run build`) | PASÓ |

## 4. Modelo PowerDesigner (Fases D y E)

El archivo adjunto `anfeca_modelo_powerdesigner_corregido_claude.sql` (SHA-256 `0963a389…`, citado por los PR #9, #12 y #16) **no está en el servidor ni en ninguna rama**. No se pudo comprobar su identidad. Como evidencia sustituta reproducible, `scripts/anfeca/pruebas/comparar_pdm_original.sh` compara en un clúster desechable estos dos archivos:
- el **PDM literal** de PowerDesigner, `anfeca_certifica_1.original.sql` (SHA-256 `76289767…`, en el repositorio desde el 20-nov);
- el SQL instalable `anfeca_modelo.sql` (SHA-256 `79bc108d…`).

| Conteo | PDM literal | SQL instalable |
|---|---:|---:|
| Tablas / columnas / PK / FK / índices / secuencias | 36 / 173 / 36 / 35 / 96 / 36 | 36 / 173 / 36 / 35 / 96 / 36 |

La comparación de 515 filas de inventario abarcó columnas, tipos, longitudes, nulabilidad, valores predeterminados, restricciones, índices y secuencias. Resultado: **31 tablas idénticas y 5 que difieren únicamente por el renombre de una columna**, con sus restricciones e índices dependientes:

| Tabla | PDM literal → instalable | Estado |
|---|---|---|
| `comunicacion` | `cumu_id_persona` → `comu_id_persona` | Autorizada |
| `solicitud_documento` | `docu_id_documento` → `socu_id_documento` (la FK sigue apuntando a `documento.docu_id_documento`) | Autorizada |
| `convocatoria` | `con_lugar_entrega` → `conv_lugar_entrega` | **Pendiente de confirmar** (atribuida al adjunto en el PR #9) |
| `convocatoria` | `con_evento_entrega` → `conv_evento_entrega` | **Pendiente de confirmar** |
| `solicitud` | `solo_art13` → `soli_art13` | **Pendiente de confirmar** |

Además, el PDM literal no puede ejecutarse completo: el índice `TIENE_ARTICULO_FK` apunta a `evaluacion.soli_id_articulo`, una columna inexistente. La matriz de 36 filas está en `~/anfeca_trabajo/evidencias_20261127/comparacion_pdm/matriz_36_tablas.md` y se regenera con el script.

En `certificacion_anfeca` (laboratorio, PostgreSQL 18.6) se instalaron las migraciones Laravel, `anfeca:modelo-instalar` (36 tablas y 35 FK, sin tocar `public`), las extensiones PDM y los catálogos. `anfeca:modelo-verificar` concluyó sin errores y con 8 entradas de mapeo divergentes ya documentadas.

## 5. Simulación de migración de datos (Fase F) sobre la copia institucional

Se usó `ensayo_copia_f2.sh plan` con el respaldo del §2, en un clúster PostgreSQL 18.6 independiente (`127.0.0.1:55432`, identificador distinto del compartido):
- SHA-256 del respaldo verificado;
- **75 tablas con conteos y huellas idénticos** en la copia;
- se aplicó la alineación de identificadores PDM (`2026_11_20_000001`) y los catálogos de formación;
- plan generado: 16 tablas PDM y 135 filas por migrar.

| Decisión | Resultado con evidencia real |
|---|---|
| **D-11** | **BLOQUEA:** a las instituciones 1, 2 y 3 les faltan el número de afiliación y la facultad confirmados con la fuente oficial (la 2 no tiene facultad; la 3 registra «Escuela Superior»). Además, 6 personas quedan sin institución (informativo) |
| D-13 | Sin incidencias bloqueantes en esta copia |
| D-14 | Sin incidencias bloqueantes en esta copia |

No se reutilizaron las decisiones locales marcadas como ficticias. Mientras D-11 no tenga datos oficiales, no proceden la migración, la conciliación ni la equivalencia institucionales.

Hallazgo de laboratorio: con la red rootless de Podman (pasta), PostgreSQL informa la IP del anfitrión como `inet_server_addr()`, y la guarda `BasePdm::descartable` rechaza correctamente la copia. El clúster de ensayo debe usar `--network host` y escuchar en `127.0.0.1`. **No se relajó la guarda.**

## 6. Modos PowerDesigner (Fase G)

Cubiertos con datos sintéticos por `phpunit.pgsql.xml` en PHP 8.5.12: apagado, espejo, principal, exclusivo, exportación, conciliación, equivalencia y reversión (79/79). Sobre la copia institucional quedan pendientes, por D-11.

## 7. Cambios ajenos encontrados en 8086 (se conservan; no se incluyen en commits)

Archivos modificados en el árbol vivo después del despliegue del 26-nov:
- 19:47: `app/Http/Controllers/Catalogos/CatalogoLicenciaturasController.php` y `app/Services/Catalogos/CatalogoLicenciaturas.php` (filtro de búsqueda por área);
- 20:02: `resources/js/components/FormacionAcademica.vue`;
- 20:02: la compilación servida `app-ZPlUBWH0.js`.

Persisten `config/anfeca_pdm_decisiones.php` y `resources/views/auth/login.blade.php`. Antes de cualquier conmutación, el responsable debe decidir si estos cambios se integran por Git.

## 8. Condiciones críticas pendientes (bloqueos)

| # | Bloqueo | Quién lo resuelve | Evidencia |
|---|---|---|---|
| B-1 | No se puede demostrar la identidad del SQL autorizado (`0963a389…`); hay 3 renombres sin autorización explícita | Responsable: entregar el archivo o confirmar los 3 renombres | §4 |
| B-2 | Para crear `certificacion_anfeca` hace falta un superusuario o CREATEDB. Si 8008 también va a usar ese nombre, se requiere un **segundo clúster** | DBA con `sudo` | §1 (roles y clúster compartido) |
| B-3 | El pool PHP-FPM 8.5.12 de 8086 debe correr como `apache`, porque los documentos privados son `drwx--S---` de `apache`. Un contenedor rootless no puede leerlos; la conmutación requiere root (unidad del sistema y prueba de SELinux) | Administrador con `sudo` | §1, §2 |
| B-4 | D-11: faltan los datos oficiales de 3 instituciones | Responsable, con el padrón ANFECA | §5 |

## 9. Procedimiento de conmutación de 8086 (para ejecutarse cuando B-1…B-4 estén resueltos)

Como administrador y en una ventana autorizada:
1. Ejecutar de nuevo `respaldo_verificado.sh`, `huellas_documentos.sh` y el punto de recuperación.
2. **Clúster:** inicializar PostgreSQL 18 independiente para el ensayo, como servicio del sistema en `127.0.0.1:5433` con su propio directorio y su propio `system_identifier`. Crear el rol propietario y luego:

   ```
   createdb --template=template0 --owner=<rol> --encoding=UTF8 --locale=es_MX.UTF-8 certificacion_anfeca
   ```

3. **Datos:**

   ```
   pg_restore --single-transaction --exit-on-error --no-owner --no-privileges -d certificacion_anfeca <respaldo>
   ```

   Comparar huellas con `cmp`, aplicar `migrate --path=database/migrations_pdm` (alineación) con `ANFECA_MODELO_BD_PERMITIDA=certificacion_anfeca` y `ANFECA_PDM_AUTORIZAR_EXTENSIONES=si`, y después el seeder de catálogos y `anfeca:modelo-verificar`. Con D-11 registrado para esta base, ejecutar `anfeca:pdm-migrar` (plan, simular y ejecutar), `anfeca:pdm-conciliar` y la equivalencia.
4. **PHP-FPM 8.5.12 exclusivo:** contenedor del sistema con la imagen `localhost/anfeca-php-fpm:8.5.12` como **root**, con `--user 48:48`, `--network host` y `-v /var/www/ANFECA_ENSAYO:/var/www/ANFECA_ENSAYO`, escuchando en un puerto local reservado. Después:
   - agregar ese puerto a `http_port_t` con `semanage port -a`;
   - probar con `ausearch -m avc -ts recent` que no haya denegaciones;
   - en `/etc/httpd/conf.d/anfeca-ensayo.conf` (solo ese VirtualHost), agregar `<FilesMatch \.php$> SetHandler "proxy:fcgi://127.0.0.1:<puerto>" </FilesMatch>`;
   - ejecutar `apachectl configtest` y recargar `httpd`. La recarga es *graceful* y no altera 8084 ni 8085.
5. **Laravel:** en `.env`, `DB_PORT=5433` y `DB_DATABASE=certificacion_anfeca`; luego `config:cache`, `route:cache` y `view:cache` **con PHP 8.5.12**.
6. **Pruebas posteriores:**
   - inicio de sesión y solicitudes 3–11;
   - documentos (las 20 huellas);
   - catálogos y acuse PDF;
   - Coordinación;
   - logs;
   - comprobar que 8008, 8084 y 8085 respondan igual que antes.
7. **Reversión:**
   - restaurar el `.env` respaldado y el VirtualHost anterior (vuelve al socket `www`, PHP 8.4.27), y recargar Apache;
   - la base `bd_anfeca_ensayo_pruebas_pg` permanece intacta.

   Si ya hubo escrituras en `certificacion_anfeca`, conciliarlas antes de volver.
