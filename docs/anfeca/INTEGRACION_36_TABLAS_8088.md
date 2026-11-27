# ANFECA 8088 · Integración definitiva sobre las 36 tablas oficiales PowerDesigner

> Rama: `integracion/anfeca-36-tablas-definitivo` · Modelo: `database/sql/oficial/anfeca_modelo_powerdesigner_certificacion_anfeca_sin_esquema.sql`
> (SHA-256 `5798ce839af01339018536155ab17698c7cb953c7f150e2b9c6b268aab978285`, versión de revisión 2026-11-17).
> Ningún contenedor (Docker, Podman ni equivalentes) se usa en esta integración ni en su despliegue.

## 1. Alcance y estado

| Elemento | Estado |
|---|---|
| Punto de partida | `integracion/consulta-expediente-pdm` (contiene `desarrollo/modulo-coordinacion`, `correccion/equivalencia-f45` y `desarrollo/anfeca-pruebas` salvo su merge) + 2 correcciones sueltas (`revision/cal-01-formacion` 362adcf, `integracion/proteccion-alta-pdm` 8cc0798). `integracion/alta-domicilio-pdm` **no** se integró: agrega una FK que no existe en el SQL oficial. |
| Pantallas | `resources/` y `public/` son **idénticos** al estado aceptado en 8086 (`00c2896`), salvo 21 líneas PHP de consulta de datos en `components/sidebar-certificacion.blade.php` (sin cambio visual). Verificable con `git diff 00c2896 -- resources public`. |
| Persistencia | Todos los procesos funcionales leen y escriben **solo** las 36 tablas, en el esquema `public`. |
| Sesiones, caché, colas, tokens de recuperación | Archivos (`storage/framework`) y `sync`: no crean tablas en la base institucional. |
| Comandos que crearían estructuras | `migrate*`, `db:wipe`, `schema:dump`, `*:table` quedan **bloqueados** por la aplicación. |
| Despliegue en 192.168.7.130:8088 | **No realizado desde esta sesión**: el entorno de trabajo no tiene acceso a la red del servidor. Todo se ejecutó y probó en un laboratorio nativo equivalente (ver §6) y se entregan los scripts para AlmaLinux 9 (§8). |

## 2. Arquitectura de la capa de persistencia

```
Pantallas Blade/Vue (sin cambios) ─► Controladores (mismas rutas, validaciones y mensajes)
                                       │  mismos nombres de campo que antes (curp, colonia, titulo_origen…)
                                       ▼
App\Services\Anfeca\*  ── ExpedienteSolicitante · Tramites/SolicitudVista · FormacionPdm · DocumentosPdm
                          HistoricoCertificados · ConsultaRefrendo · Instituciones · Catalogo
                                       │  Query Builder / Eloquent (App\Models\Pdm\*, generados del SQL)
                                       ▼
PostgreSQL 18.6 · certificacion_anfeca · public · 36 tablas
```

* `App\Models\Pdm\*` se **generan** del SQL oficial (`scripts/anfeca_8088/generar_modelos.php`): tabla, PK (incluidas las compuestas), columnas y relaciones FK exactas. `App\Models\Cuenta` extiende `Usuario` solo para la autenticación.
* La coordinación usa **subconsultas** (proyecciones) con los alias que ya tenía (`s`, `d`, `i`, `u`, `h`); no se crean vistas.
* Se retiraron del flujo: `users`, `datos_solicitantes`, `solicitudes_certificacion`, `selecciones_certificacion`, `formaciones_academicas(_solicitud)`, `documentos_probatorios(_solicitud)`, `datos_refrendo`, `certificados_historicos`, `instituciones_afiliadas`, `zonas`, `coordinador_zona`, `historial_documentos_academicos`, `revisiones_documentales`, `password_reset_tokens`, `sessions`, `cache`, `jobs`, y los esquemas `anfeca_modelo`/`anfeca_compat` con su espejo y modos.

## 3. Mapeo de pantallas → modelo oficial

### 3.1 Acceso, datos personales, domicilio y contacto

| Campo de pantalla | Tabla.atributo | Nota |
|---|---|---|
| Correo (usuario de acceso) | `USUARIO.USUA_NOMBRE` + `COMUNICACION` tipo «Correo electrónico» | Comparación sin mayúsculas; duplicado ⇒ acceso rechazado |
| Contraseña | `USUARIO.USUA_CONTRASENIA` (bcrypt) | |
| Perfil | `USUARIO.USUA_ID_ROL → ROL` (`solicitante`, `coordinador_nacional`, `coordinador_zona_1…7`) | Privilegios en `ROL_PRIVILEGIO → PRIVILEGIO` |
| Datos autorizados | `USUARIO.USUA_ESTATUS = DATOS_AUTORIZADOS` (`ACTIVO` antes) | Ver INC-04 |
| CURP, RFC, nombre(s), apellidos | `PERSONA.PERS_CURP/RFC/NOMBRE/APELLIDO_PATERNO/APELLIDO_MATERNO` | Unicidad de CURP/RFC/correo validada en la aplicación |
| Género | `PERSONA.PERS_GENERO` (`F`/`M`) | |
| Calle, núm. exterior, núm. interior | `DOMICILIO.DOMI_CALLE/NUMERO_EXT/NUMERO_INT` | Longitudes del modelo: 170 / 6 / 4 |
| Entidad federativa | `DOMICILIO.DOMI_ID_ENTIDAD_FEDERATIVA → ENTIDAD_FEDERATIVA` (32 claves INEGI) | Texto capturado normalizado («cdmx» → Ciudad de México) |
| Colonia, alcaldía/municipio, CP | `COMUNICACION` tipos «Colonia/Alcaldía o municipio/Código postal del domicilio» | **INC-01** |
| Teléfono particular / celular | `COMUNICACION` tipos «Teléfono particular/celular» | Al menos uno |
| Institución de afiliación | `PERSONA.PERS_ID_INSTITUCION → INSTITUCION` y `PERS_NUM_AFILIACION_INSTITUCION` | Se captura en Formación |
| Cédula profesional (dato opcional) | — | **INC-02** (la pantalla no la captura) |

### 3.2 Certificación, refrendo, compromiso y envío

| Concepto | Tabla.atributo |
|---|---|
| Certificación ANFECA/CACSLA/CACECA | `SOLICITUD.SOLI_ID_CONVOCATORIA → CONVOCATORIA → TIPO_CONVOCATORIA.TICO_SIGLAS` |
| Tipo de personal | `SOLICITUD.SOLI_ID_TIPO_PERSONAL_ACADEMICO → TIPO_PERSONAL_ACADEMICO` |
| Área de certificación | `SOLICITUD.SOLI_ID_AREA_CERTIFICACION → AREA_CERTIFICACION` |
| Folio | `SOLICITUD.SOLI_FOLIO_SOLICITUD = SOLI_ID_SOLICITUD` (misma numeración que mostraban las pantallas) |
| Tipo de trámite | Refrendo ⇔ `SOLICITUD.SOLI_NUMERO_CERTIFICADO` (folio histórico) registrado |
| Antecedente histórico | `HISTORICO_CERTIFICADO`: una fila por evento; `HICE_FOLIO_CERTIFICADO` = número de certificado, `HICE_TIPO` = certificación, `HICE_NUMERO_CERTIFICACION` 0 = primera vez / 1…7 = refrendo, `HICE_ANIO_CERTIFICACION`, `HICE_CURP`, `HICE_AREA_CERTIFICACION` |
| Artículo 14 / inciso | `SOLICITUD.SOLI_ART14`, `SOLI_ART13` (inciso 13.1), `SOLI_ID_ARTICULO → ARTICULO_SUBINDICE` |
| Número de refrendo correspondiente | Calculado: último refrendo del histórico + 1 |
| Estados y fechas | `ESTADO_SOLICITUD` (historial con fecha y hora) + `C_ESTADO_SOLICITUD`: `BO` Borrador · `RF` Datos de refrendo registrados · `FA` Formación académica autorizada · `DP` Documentos probatorios registrados · `CA` Compromiso de entrega aceptado · `EN` Enviada |

Reglas aplicadas: el refrendo solo inicia si la persona captura el número; el certificado debe ser de la certificación elegida y su CURP coincidir; periodicidad de 3 años (año del último evento + 3); dos certificados distintos se validan por separado; no se admite otro refrendo del mismo certificado en el periodo; primera vez bloqueada solo si ya existe certificado de la misma certificación **y** área; después de «Enviada» no se admiten cambios.

### 3.3 Formación académica

| Concepto | Tabla.atributo |
|---|---|
| Licenciatura de origen / último grado | `PERSONA_ESTUDIO_PROFESIONAL → ESTUDIO_PROFESIONAL → NIVEL_PROFESIONAL` |
| Nombre del título (catálogo, manual u «Otro») | `ESTUDIO_PROFESIONAL.ESPR_NOMBRE` (150) |
| Origen del nombre, categoría y papel | `ESTUDIO_PROFESIONAL.ESPR_ESTADO` (3): `G/C/M/O` + `A/C/I/N/O` + `L/U` — **INC-05** |
| Título y cédula (archivos) | `PEES_URL_TITULO`, `PEES_URL_CEDULA` + una fila `SOLICITUD_DOCUMENTO` por carga (historial) |
| «Cuento con estudios de posgrado» | Derivado: existe/no existe último grado de posgrado |
| Clave y versión del catálogo ANUIES | Derivadas del catálogo vigente por el nombre registrado (INC-05) |
| Autorización | Evento `FA` de la solicitud |

### 3.4 Documentos

| Concepto | Tabla.atributo |
|---|---|
| Cada documento cargado | `SOLICITUD_DOCUMENTO` (solicitud, `DOCUMENTO`, fecha, hora) |
| Estado del documento | `ESTADO_SOLICITUD_DOCUMENTO` + `ESTADO_DOCUMENTO` («Cargado», «Sustituido por una versión posterior»; catálogo listo para «Validado», «Con observaciones», «No válido») |
| Archivo | `storage/app/private/anfeca/expedientes/{SOLI_ID}/{SOCU_ID}.{ext}` — **INC-03** |
| Comprobante de pago | Además `PAGO.PAGO_URL_COMPROBANTE` |
| Enlace de evidencias | Documento «Enlace de evidencias»; la URL en `ESDO_OBSERVACION` (≤ 300) — **INC-07** |

### 3.5 Coordinación

Bandeja, expediente, visor PDF/JPG/PNG, indicadores y seguimiento leen las proyecciones de §2. Alcance: `alcance_nacional` (todo) o `alcance_zona_N` (zona N); la zona de una solicitud es la de la institución de la persona (`INSTITUCION.INST_ZONA`, interpretado contra las 7 zonas oficiales). Un regional recibe 404 fuera de su zona; un solicitante recibe 403 en `/coordinacion`.

## 4. Incompatibilidades del modelo oficial (documentadas; ninguna oculta datos ni quita campos de pantalla)

| ID | Situación | Solución aplicada dentro de las 36 tablas | Requiere decisión |
|---|---|---|---|
| INC-01 | `DOMICILIO` no tiene colonia, municipio ni CP, ni FK a `COLONIA` | Valores en `COMUNICACION` con tipos de catálogo propios, asociados por FK a la persona | Sí: aprobar o incorporar `DOMI_ID_COLONIA` en PowerDesigner |
| INC-02 | `PERS…` no tiene cédula; `PEES_CEDULA` existe por estudio | La pantalla actual no captura el número de cédula; `PEES_CEDULA` queda disponible | No |
| INC-03 | `SOLICITUD_DOCUMENTO` no tiene ruta de archivo | La ruta se deriva de la PK (`SOCU_ID`); la asociación está en la base | Sí, si se desea la ruta como atributo |
| INC-04 | Sin atributo de «datos autorizados» ni su fecha | `USUA_ESTATUS = DATOS_AUTORIZADOS`; la fecha de autorización no se conserva (ninguna pantalla la muestra) | Opcional |
| INC-05 | `ESTUDIO_PROFESIONAL` no distingue origen/último grado ni guarda clave/versión de catálogo | Código de 3 caracteres en `ESPR_ESTADO`; clave y versión se derivan del catálogo vigente | Sí: aprobar la codificación |
| INC-06 | `USUARIO` no tiene fecha de creación | Seguimiento usa la fecha del primer trámite | No |
| INC-07 | Enlace de evidencias sin atributo; antes admitía 2 048 caracteres | Observación del estado inicial (máx. 300; validación ajustada) | Sí, si se requieren URL largas |
| INC-08 | Longitudes menores que la validación anterior (calle 170, núm. ext. 6, núm. int. 4, apellidos 60, correo 150, título 150) | La validación del servidor se alinea al modelo; la pantalla no cambia | No |
| INC-09 | `INSTITUCION` no tiene campus (sí `SEDE`) | «Sede» = primera `SEDE`; «Campus» se muestra vacío | Opcional |
| INC-10 | `HISTORICO_CERTIFICADO` no tiene zona histórica | El expediente muestra «No registrada» | No |

El SQL oficial **se ejecutó sin errores y sin modificaciones**. Observaciones informativas (no aplicadas): erratas de nombres (`PEES_ID_ESTUIDO_PROFESIONAL`, `ESSO_ID__ESTADO_SOLICITUD`), índices `*_PK` redundantes, `MONEY` dependiente del locale y ausencia de unicidad en CURP/RFC/correo/folios (la aplicación la garantiza con bloqueo de tabla en el alta).

## 5. Pendientes

| ID | Pendiente |
|---|---|
| DEF-01 | Decisiones INC-01, INC-03, INC-05 e INC-07 (aprobación o ajuste del modelo en PowerDesigner). |
| DEF-02 | Revisión documental de Coordinación (desactivada también en 8086): su versión anterior usaba `revisiones_documentales`. Debe reimplementarse sobre `ESTADO_SOLICITUD_DOCUMENTO` (estados ya catalogados); sin el coordinador que revisa, porque el modelo no lo registra. |
| DEF-03 | Despliegue real en 192.168.7.130:8088 con `scripts/anfeca_8088/servidor/instalar_8088.sh` y repetición de las pruebas en el servidor. |
| DEF-04 | Catálogo oficial de instituciones (con `INST_ZONA` en forma «Zona N - Nombre»), convocatorias oficiales (montos y fechas) y carga de `HISTORICO_CERTIFICADO` desde el Excel histórico (el importador sigue en modo vista previa). |
| DEF-05 | Copiar a 8088 los catálogos ANUIES de 8086 con su SHA-256/versión (banderas activas como en 8086). |

## 6. Laboratorio de validación (equivalente nativo del 8088)

| Componente | Valor |
|---|---|
| Sistema | Contenedor de trabajo Ubuntu 24.04 (no AlmaLinux): sin acceso a 192.168.7.130 |
| PHP | **8.5.12** compilado de las fuentes oficiales (`php-src`, etiqueta `php-8.5.12`), PHP-FPM con pool y socket exclusivos |
| PostgreSQL | **18.6** compilado de las fuentes oficiales (`REL_18_6`), clúster propio en `127.0.0.1:55488`, datos en `/var/lib/anfeca8088-pg/data` |
| Apache | 2.4.58, VirtualHost exclusivo `*:8088` → `/var/www/ANFECA_8088/public` vía `proxy_fcgi` |
| Base | `certificacion_anfeca`, esquema `public`, propietario `anfeca_8088` |
| Frontend | Compilado con Vite; en el laboratorio sin descargar la fuente de `fonts.bunny.net` (sin salida a ese dominio). En el servidor se compila con `vite.config.js` sin cambios. |

## 7. Resultados de las pruebas (base limpia, usuarios ficticios nuevos)

Ejecutadas con `scripts/anfeca_8088/pruebas/e2e_8088.py` (banderas de catálogo apagadas) y `e2e_catalogos_8088.py` (banderas como en 8086). Cada verificación comprueba la respuesta HTTP **y** la fila exacta en la tabla correspondiente.

| # | Prueba | Resultado |
|---|---|---|
| 1 | Registro desde cero (PERSONA, USUARIO, DOMICILIO, COMUNICACION) y autenticación | Aprobada (2/2) |
| 2 | Recuperación de contraseña (CURP + correo, token en caché de archivos) | Aprobada |
| 3 | Captura de datos personales y recuperación en pantalla | Aprobada |
| 4 | Modificación restringida (solo domicilio y teléfonos; al menos un teléfono) | Aprobada |
| 5 | Autorización de datos y bloqueo posterior | Aprobada |
| 6 | Selección de certificación (catálogos FK) | Aprobada |
| 7 | Solicitud inicial | Aprobada |
| 8 | Refrendo histórico (consulta, certificado ajeno, vigencia de 3 años, sin selección automática, art. 14.1, envío, antecedente intacto, duplicado impedido) | Aprobada (4/4) |
| 9 | Formación académica (sin catálogo; catálogo escenario 2; escenario 1; área que exige posgrado; bloqueo tras autorizar) | Aprobada (6/6) |
| 10 | Carga de documentos (SOLICITUD_DOCUMENTO, estados, PAGO, enlace) y solo consulta posterior | Aprobada (2/2) |
| 11 | Consulta documental (archivo íntegro; sin sesión ⇒ acceso denegado) | Aprobada |
| 12 | Aceptación del compromiso (validación del servidor y texto definitivo) | Aprobada |
| 13 | Envío («Su solicitud fue registrada correctamente.» y bloqueo) | Aprobada |
| 14 | Acuse PDF (propio; 404 para ajenos) | Aprobada |
| 15 | Seguimiento del solicitante | Aprobada |
| 16 | Consulta por Coordinación nacional (bandeja, expediente, visor, indicadores, seguimiento) | Aprobada (2/2) |
| 17 | Permisos por rol y zona (Z7/Z6; 404 fuera de alcance; 403 a solicitantes) | Aprobada (2/2) |
| 18 | Integridad PK/FK (35 FK validadas, sin huérfanos) | Aprobada |
| 19 | Regresión de pantallas (15 rutas con su contenido) y capturas en `docs/anfeca/evidencias_8088/` | Aprobada |
| 20 | Ausencia de tablas adicionales (36 tablas, solo `public`) | Aprobada |

Totales: **28/28** verificaciones de `e2e_8088.py` y **4/4** de `e2e_catalogos_8088.py`. `php artisan anfeca:verificar-esquema`: 36/36 tablas, 173 atributos, 35/35 FK, 96 índices (60 explícitos + 36 de PK), 36 secuencias, **sin diferencias**; se comprobó además que detecta una tabla, una columna y una longitud alteradas. Respaldo `pg_dump` → restauración en base nueva → mismos conteos y esquema exacto.

## 8. Instalación en el servidor (AlmaLinux 9, nativa)

`scripts/anfeca_8088/servidor/instalar_8088.sh` (simula por omisión; `--ejecutar` para aplicar):

1. Comprobaciones de solo lectura: AlmaLinux 9, `/var/www/ANFECA_8088` inexistente, puertos 8088 y 5488 libres, PostgreSQL **18.6** y PHP/PHP-FPM **8.5.12** con extensiones; lista los clústeres y sitios existentes **sin tocarlos**.
2. Clúster PostgreSQL 18.6 **nuevo** (`/var/lib/pgsql/18/data_anfeca8088`, `127.0.0.1:5488`, unidad `postgresql-18-anfeca8088`, SELinux `postgresql_port_t`).
3. Base `certificacion_anfeca` nueva y vacía; rol `anfeca_8088` (contraseña interactiva); SQL oficial con verificación SHA-256; `anfeca:verificar-esquema`.
4. Aplicación clonada de la rama, `composer install --no-dev`, `npm ci && npm run build`, `.env` desde `scripts/anfeca_8088/env.8088.example`, SELinux (`httpd_sys_rw_content_t`, `httpd_can_network_connect_db`), `anfeca:catalogos`.
5. PHP-FPM exclusivo (`php-fpm-anfeca8088`, socket `/run/anfeca8088/php-fpm.sock`).
6. VirtualHost `/etc/httpd/conf.d/anfeca-8088.conf`, `semanage port -a -t http_port_t -p tcp 8088`, `firewall-cmd --add-port=8088/tcp`, `apachectl configtest` y `systemctl reload httpd` (recarga sin editar otros sitios).

Aislamiento 8088 ↔ 8086: puerto web, VirtualHost, directorio, pool/servicio PHP-FPM, clúster PostgreSQL (puerto, directorio de datos y unidad systemd), rol y base propios. Comprobación de solo lectura de que son clústeres distintos:

```bash
sudo -u postgres psql -h 127.0.0.1 -p 5488 -Atc "select inet_server_port(), current_setting('data_directory'), system_identifier from pg_control_system()"
```

```bash
sudo -u postgres psql -h 127.0.0.1 -p 5433 -Atc "select inet_server_port(), current_setting('data_directory'), system_identifier from pg_control_system()"
```

Los `system_identifier` y directorios deben ser distintos. Ninguna conexión de 8086 cambia.

## 9. Validación en el servidor y procedimiento propuesto para 8008

En 8088, tras instalar:

1. `php artisan anfeca:verificar-esquema` → sin diferencias.
2. `php artisan anfeca:laboratorio` **solo** si `APP_ENV=local` en una copia de validación; en 8088 se recomienda cargar instituciones e histórico ficticios con APP_ENV=local temporalmente, ejecutar las pruebas y después reinstalar la base limpia.
3. Cuentas de coordinación ficticias con `anfeca:coordinador-alta` y `e2e_8088.py` / `e2e_catalogos_8088.py` con las variables documentadas en el encabezado de cada script.
4. Revisión visual con las capturas de referencia y aprobación de las decisiones DEF-01.

Para 8008 (solo con autorización expresa): repetir exactamente el mismo `instalar_8088.sh` con `APP`, `PUERTO_WEB` y `PG_PUERTO` propios del 8008 en un **ensayo** previo; migrar datos institucionales solo mediante un procedimiento de conversión aprobado (no incluido aquí); repetir §9.1–9.4; comparar conteos con la fuente.

## 10. Plan de respaldo y reversión para un futuro despliegue

* **Antes:** `respaldo_8088.sh` (o su equivalente del ambiente destino): `pg_dump -Fc` del clúster propio verificando su `data_directory`, `pg_restore -l` como prueba de legibilidad, expedientes, `.env` y commit, todo con `SHA256SUMS`. Los respaldos se conservan; nunca se sustituyen sin verificar.
* **Reversión de aplicación:** volver al commit anterior (`git checkout <commit>` + `composer install` + `npm run build` + `config:cache`); la base no cambia porque la estructura es siempre la del SQL oficial.
* **Reversión de datos:** restaurar el volcado en una base **nueva** (`createdb … && pg_restore`), verificar con `anfeca:verificar-esquema` y conteos, y solo entonces cambiar `DB_DATABASE`.
* **Reversión de instalación 8088:** `desinstalar_8088.sh` (simula por omisión): quita el VirtualHost, la regla de firewall y las unidades del 8088 y **renombra** (no borra) datos y aplicación.
