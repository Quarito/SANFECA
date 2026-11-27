# Integración de certificacion_anfeca en 8086 · PHP 8.5.12, PostgreSQL 18 y modelo PowerDesigner · 2026-11-27

Este documento continúa el PR #17 y registra lo **ejecutado realmente en el servidor**. Lo que falta para la conmutación de 8086 son **intervenciones con privilegios de administrador y dos decisiones del responsable** (§10). Mientras no se cumplan, 8086 sigue en servicio con su configuración anterior:
- PHP-FPM 8.4.27, pool `www`;
- base `bd_anfeca_ensayo_pruebas_pg`;
- PDM apagado.

## 1. Matriz de resultado

| Componente | Resultado | Evidencia |
|---|---|---|
| SQL autorizado | **REQUIERE AUTORIZACIÓN** (3 renombres) | §2. El adjunto `0963a389…` no es recuperable. El PDM literal (`76289767…`) está identificado y es instalable; las dos correcciones autorizadas están verificadas |
| 36 tablas PDM | **APROBADO** (modelo literal) | §2. 36/173/36/35/96/36 en las tres variantes; matriz de 36 filas. En `certificacion_anfeca`, 0 elementos faltantes del PDM literal |
| D-11 | **BLOQUEADO** (decisión institucional) | §4. No existe un padrón oficial en el servidor ni en Git |
| Respaldo institucional | **APROBADO** | §3. Instantánea consistente, SHA-256 `9b183bcd…`, restaurada: 75 tablas y 200 filas idénticas; 20 documentos con huellas iguales |
| Clúster independiente | **APROBADO** (sin servicio del sistema) | §5. PostgreSQL 18.6 nativo en 127.0.0.1:5433, identificador 7712865409902226718 (el compartido es 7697381974958512424). Para sobrevivir a un reinicio requiere el paso administrativo §10-1 |
| Base certificacion_anfeca | **APROBADO** (instalada; aún sin uso por 8086) | §5. Restauración íntegra: huellas, DDL y 63 secuencias iguales; roles mínimos |
| Migraciones | **APROBADO** para la conmutación con PDM apagado | §6. 35 de 36; `escuela_facultad` sigue sin autorizar. La alineación PDM (5 renombres) **no** se aplica hasta la decisión de §2 |
| Conciliación | **BLOQUEADO** por D-11 (PDM) / **APROBADO** (copia public) | §6. Huellas origen = destino en las 75 tablas. La migración de datos al PDM, la conciliación y la equivalencia esperan D-11 |
| PHP 8.5.12 CLI | **APROBADO** | §7. Composer, PHPUnit 205/79/22 y Vite |
| PHP 8.5.12 FPM | **APROBADO** en el candidato / **REQUIERE AUTORIZACIÓN** en 8086 | §7. FastCGI: `8.5.12 fpm-fcgi`, `cert8086_app@5433`. El pool de 8086 debe correr como `apache` (§10-2) |
| Laravel | **APROBADO** | §7. Laravel 13.42.0 sobre PHP 8.5.12 y `certificacion_anfeca` |
| Solicitudes y refrendos | **APROBADO** en el candidato | §8. E2E 43/43 y 36/36: registro, primera vez, refrendo, acuse y consulta |
| Documentos | **APROBADO** en el candidato | §8. 20/20 huellas históricas; visor PDF/JPG/PNG |
| Coordinación | **APROBADO** en el candidato | §8. Rol, bandeja, filtros, expedientes, historial y alcance por zona |
| Reversión | **APROBADO** (BD) / **NO EJECUTADO** (Apache) | §9. Intercambio por renombre y su reversión ensayados. El retorno del VirtualHost requiere root |
| Puerto 8086 | **NO EJECUTADO** (sin conmutar) | Sigue respondiendo con su configuración anterior |
| Ambientes protegidos | **APROBADO** | 8008, 8084 y 8085 sin cambios. El clúster 5432 y el pool `www` no se tocaron |

## 2. Modelo PowerDesigner y SQL

- **Fuente disponible:** PDM literal `database/sql/anfeca_modelo/anfeca_certifica_1.original.sql`, SHA-256 `76289767d6dd66996d0a8a05e78f2a6a71bf55cf99ef7656d36a842b8a9701d5`.
- **Instalable:** `anfeca_modelo.sql`, SHA-256 `79bc108d…`.
- **Adjunto corregido:** `anfeca_modelo_powerdesigner_corregido_claude.sql`, SHA-256 `0963a389…`. No está en el servidor, en Git ni en los PR, así que **no se declara recuperado**.

`scripts/anfeca/pruebas/comparar_pdm_original.sh` (corregido):
- registra y clasifica los errores del SQL literal;
- compara tres variantes en un clúster desechable;
- elimina las bases temporales incluso ante fallos (probado con un SQL inválido: `rc=1`, 0 bases restantes).

| Variante | Contenido | Conteos |
|---|---|---|
| Original | PDM literal; 1 error clasificado: `TIENE_ARTICULO_FK` sobre `EVALUACION.SOLI_ID_ARTICULO`, una columna inexistente | 36/173/36/35/96/36 |
| A (autorizada) | Original + `CUMU_ID_PERSONA→COMU_ID_PERSONA` (`comunicacion`) + `DOCU_ID_DOCUMENTO→SOCU_ID_DOCUMENTO` (solo en `solicitud_documento`; la FK sigue apuntando a `documento.docu_id_documento`) | 36/173/36/35/96/36 |
| B (instalable) | A + `con_lugar_entrega→conv_lugar_entrega`, `con_evento_entrega→conv_evento_entrega` y `solo_art13→soli_art13` | 36/173/36/35/96/36 |

Sobre `TIENE_ARTICULO_FK`: el PDM declara 61 índices. Con los 36 que respaldan las llaves primarias, los 96 aprobados equivalen a 36 + 60 declaraciones válidas, es decir, **sin** `TIENE_ARTICULO_FK`. La cifra autorizada ya presupone su omisión.

**Justificación técnica de los 3 renombres pendientes (no constituye autorización):**
- en el PDM, todas las demás columnas de `CONVOCATORIA` usan el prefijo `CONV_` y todas las de `SOLICITUD` usan `SOLI_`;
- son la misma clase de error que las dos correcciones autorizadas (`CUMU_` en `COMUNICACION`, `DOCU_` en `SOLICITUD_DOCUMENTO`).

**Dependencias:**
- la variante B la usan `app/Models/Pdm/{Convocatoria,Solicitud}.php`, `PlanMigracionPdm.php`, `anfeca_compat_funciones_pdm.sql`, la migración `2026_11_20_000001_alinear_identificadores_powerdesigner.php` (aplica los 5 renombres juntos) y 7 pruebas PDM;
- la variante A no tiene código asociado;
- el código que opera hoy en 8086 (`ddd4c8d`) usa los nombres literales.

Matriz completa por tabla (regenerable con el script):

| # | Tabla | Col. | Restr. | Índ. | Variante A (autorizada) | Variante B (instalable) |
|---:|---|---:|---:|---:|---|---|
| 1–7 | `acta`, `alcaldia_municipio`, `area_certificacion`, `articulo_subindice`, `c_estado_solicitud`, `certificado`, `colonia` | 5, 3, 2, 3, 2, 5, 4 | 3, 5, 3, 4, 3, 6, 6 | 3, 3, 2, 2, 2, 3, 3 | Idénticas al PDM | Iguales a A |
| 8 | `comunicacion` | 5 | 7 | 4 | Renombra `comu_id_persona` | Igual a A (solo cambia el nombre generado del NOT NULL) |
| 9 | `convocatoria` | 12 | 12 | 3 | Idéntica | **Además** renombra `conv_lugar_entrega` y `conv_evento_entrega` |
| 10–29 | `dictamen` … `sede` (20 tablas) | — | — | — | Idénticas al PDM | Iguales a A |
| 30 | `solicitud` | 11 | 14 | 6 | Idéntica | **Además** renombra `soli_art13` |
| 31 | `solicitud_documento` | 5 | 8 | 4 | Renombra `socu_id_documento` | Igual a A (nombre generado del NOT NULL) |
| 32–36 | `tipo_comunicacion`, `tipo_convocatoria`, `tipo_dictamen`, `tipo_personal_academico`, `usuario` | 3, 4, 3, 3, 6 | 4, 5, 4, 4, 9 | 2, 2, 1, 2, 4 | Idénticas al PDM | Iguales a A |

**Estado en `certificacion_anfeca`** (restaurada del origen): contiene el PDM literal completo, con 0 elementos faltantes en las 36 tablas, y 54 elementos aditivos de extensiones (`ext_*`). Las correcciones no se aplican mientras la decisión siga pendiente: con PDM apagado no afectan la operación.

## 3. Respaldos

| Respaldo | Ruta (privada) | Verificación |
|---|---|---|
| PostgreSQL, instantánea consistente | `~/ANFECA_RESPALDOS/respaldo_verificado_bd_anfeca_ensayo_pruebas_pg_20261127_084134/` | SHA-256 `9b183bcd…`; restauración real con inventario de 1 520 objetos, 75 tablas y 200 filas idénticas |
| Documentos (20), catálogos, `.env`, compilación, Apache y FPM | `~/ANFECA_RESPALDOS/certificacion_8086_20261127/` | Las huellas de documentos coinciden con el punto de recuperación del 26-nov |
| Respaldo final | Lo genera `02_conmutar_root.sh` con los escritores detenidos | Exige que tenga menos de 2 horas |

Comparando las 63 secuencias del origen actual con las del destino restaurado: no hubo inserciones en el origen después de las 08:41.

## 4. D-11 · Instituciones (matriz)

| ID | Nombre registrado | Número de afiliación registrado | Facultad registrada | Fuente | Correspondencia propuesta | Estado |
|---|---|---|---|---|---|---|
| 1 | Universidad Autónoma de Chiapas (Campus I) | 9806001 | Facultad de Contaduría y Administración | Captura en `instituciones_afiliadas` del ensayo (20-nov 22:55, sin seeder ni commit); repetida en `ZONAS_DIAGNOSTICO_Y_NORMALIZACION.md`, que se derivó de la misma base | Conservar el número y la facultad registrados | **Pendiente de confirmación oficial** |
| 2 | Universidad de Estudios Profesionales de Ciencias y Artes | 9804002 | — (vacía) | Ídem | Número registrado; **falta la facultad** | **Bloqueada:** falta la facultad oficial |
| 3 | Instituto Tecnológico Superior Zacatecas Norte | 0003013 | «Escuela Superior» (genérica) | Ídem | Número registrado; facultad por confirmar | **Pendiente de confirmación oficial** |

Fuentes revisadas sin resultado oficial: `~/ANFECA_FUENTES`, `docs/` (incluidos `DECISIONES_DATOS_F1.md` e `INCIDENCIAS_F2_ENSAYO.md`), el historial de Git de todas las ramas y 6 bases del servidor (solo contienen `PRUEBA-001`, ficticia).

El archivo local `config/anfeca_pdm_decisiones.php` de 8086 contiene decisiones marcadas como ficticias y **no se usaron**.

**Decisión requerida:** confirmar las tres filas con el padrón ANFECA. Se registran en `config/anfeca_pdm_decisiones.php` del release, con `base_de_datos = 'certificacion_anfeca'` y la fuente, o como `no_migrar`. Solo se detiene la migración PDM. La conmutación con PDM apagado no depende de D-11.

## 5. Clúster exclusivo y base

| Atributo | Valor |
|---|---|
| Motor | PostgreSQL 18.6 (`/usr/pgsql-18/bin`), `initdb` con suma de verificación de datos, UTF8 y `es_MX.UTF-8` |
| Datos, socket y logs | `/home/servidorweb/anfeca_pg8086/{data,run,logs}` (700). §10-1 lo traslada a `/var/lib/pgsql/anfeca8086` |
| Endpoint | Solo `127.0.0.1:5433`; `pg_hba`: socket peer para el superusuario del clúster y SCRAM en loopback |
| Identificador | 7712865409902226718 (independiente del 5432) |
| Roles | `cert8086_propietario` (dueño y migraciones; sin CREATEDB/CREATEROLE) y `cert8086_app` (solo DML y uso de secuencias y funciones; no puede crear en `public`). Contraseñas en `~/ANFECA_CREDENCIALES/pg8086_certificacion_anfeca.txt` (600) |
| Base | `certificacion_anfeca`, propietario `cert8086_propietario`, UTF8, `es_MX.UTF-8`, `REVOKE ALL FROM PUBLIC` |
| Contenido | Respaldo restaurado: huellas iguales en 75 tablas y 200 filas; DDL igual (solo cambia la forma de `ANY(ARRAY)`); 63 secuencias iguales |

**Orden validado:**
1. restaurar el respaldo, que ya contiene `public`, `anfeca_modelo` (PDM literal + extensiones) y `anfeca_compat`;
2. verificar huellas;
3. otorgar permisos.

No se ejecuta el SQL PDM sobre la base restaurada, porque duplicaría objetos. La alineación de nombres se aplica después, por migración, solo cuando se autorice.

## 6. Migraciones y datos

- **Laravel:** 35 ejecutadas; 1 pendiente no autorizada (`2026_11_20_190000_add_escuela_facultad`).
- **PDM:** `2026_11_20_000001` (5 renombres) y `2026_11_28_000001` (catálogos de formación) quedan pendientes en `certificacion_anfeca` por la decisión de §2. Están probadas en una copia: el plan F2 con 16 tablas y 135 filas se bloquea solo por D-11.
- **Datos institucionales:** se conservan íntegros (huellas). La migración `public → anfeca_modelo`, la conciliación y la equivalencia esperan D-11.

## 7. PHP 8.5.12 y candidato

- **Imagen:** `localhost/anfeca-php-fpm:8.5.12` (Dockerfile del PR #17), con PHP 8.5.12 CLI y FPM y Composer 2.11.3.
- **Pruebas:**
  - Composer validate, install desde el lock y platform-reqs: correctos.
  - PHPUnit SQLite: 205 pruebas (1 omitida, 2 489 aserciones).
  - PHPUnit PostgreSQL 18.6: 79/79.
  - Revisión documental: 22/22.
  - Vite: correcto.

  Las dos pruebas de valores por omisión deben correr con el `.env` del laboratorio, como en CI. Con un `.env` de producción leen las banderas activas.
- **Candidato:**
  - pool PHP-FPM 8.5.12 exclusivo (127.0.0.1:19086, `pm` dinámico, 512M/6M/25M, `expose_php` desactivado), detrás de un servidor web en 127.0.0.1:18086;
  - storage con los 20 documentos históricos en sus rutas originales y los catálogos;
  - base `cert8086_pruebas_funcionales`, una copia de `certificacion_anfeca` para escrituras de prueba.
- **Identidad por FastCGI:** `{"php":"8.5.12","sapi":"fpm-fcgi","memory_limit":"512M","laravel":"13.42.0","bd":"cert8086_pruebas_funcionales","rol":"cert8086_app","puerto":5433,"pdm":"apagado"}`.
- **Artefacto:** `anfeca8086_9431a94.tar.gz` (SHA-256 `5194d42a…`), construido con dependencias sin desarrollo y PHP 8.5.12. Su compilación Vite es **`app-ZPlUBWH0.js`, idéntica a la que sirve 8086 hoy**.

## 8. Pruebas funcionales (candidato, HTTP real)

`scripts/anfeca/pruebas/e2e_candidato.py`: **43/43** con refrendo y **36/36** sin refrendo.

- **Solicitantes:**
  - registro desde cero;
  - CURP duplicada rechazada;
  - certificación bloqueada hasta autorizar los datos;
  - autorización, y edición bloqueada después;
  - primera vez (Contaduría Pública);
  - formación con filtro por área (51 títulos de Contaduría);
  - documentos PNG/JPG;
  - compromiso, envío y acuse PDF;
  - consulta posterior;
  - refrendo con certificado histórico ficticio, número inexistente rechazado y formación con Maestría.
- **Coordinación:**
  - inicio de sesión;
  - dashboard e indicadores;
  - bandeja con las solicitudes nuevas e históricas (3, 4 y 5);
  - filtro válido aceptado y manipulado rechazado (422);
  - expedientes;
  - visor PNG/JPG/PDF;
  - **20/20 documentos históricos con huella idéntica**;
  - historial;
  - revisión documental desactivada (404);
  - 3 coordinaciones de zona con alcance correcto: un expediente ajeno responde 404 y la bandeja queda vacía mientras `zona_id` no esté normalizado (pendiente autorizado aparte);
  - solicitante sin acceso a Coordinación.
- **Interfaz (Playwright, 9/9):**
  - filtro por área con 5 áreas;
  - casilla de posgrado guardada y recuperada;
  - búsqueda con acentos del catálogo V2;
  - bandeja, expediente histórico y visor PDF;
  - 0 errores de JavaScript.
- **Logs:** 0 entradas `production.ERROR`.

**Cambios locales integrados** (commit `49b100f`): el filtro por área (controlador, servicio y Vue), idéntico al que opera en 8086, más una prueba de regresión. El catálogo V2 que usa 8086 (`ANUIES-2000-2026-LIC-PRELIMINAR-V2`, SHA-256 `270f1fe1…`) solo corrige acentos en 862 títulos (0 cambios de área); se conserva tal cual.

## 9. Reversión

- **Base de datos:** `sincronizar_bd.sh … ensayo` restauró en `certificacion_anfeca_sinc_*`, verificó 75 tablas, intercambió por renombre y revirtió el intercambio. **No usa DROP.** La base de origen `bd_anfeca_ensayo_pruebas_pg` nunca se modifica.
- **Aplicación:** el release vive en `/var/www/ANFECA_8086/releases/<commit>` y `/var/www/ANFECA_ENSAYO` (código y `.env`) queda intacto. Revertir consiste en restaurar el VirtualHost guardado y recargar Apache (`03_revertir_root.sh`).
- **Escrituras posteriores:** si `certificacion_anfeca` las recibió después de la conmutación, el script deja 8086 en mantenimiento y lista las tablas a conciliar. Nunca reabre la base anterior perdiendo solicitudes.

## 10. Intervenciones mínimas necesarias

| # | Quién | Acción | Motivo |
|---|---|---|---|
| 1 | Administrador (`sudo`) | `sudo bash scripts/anfeca/despliegue_8086/01_preparar_servicios_root.sh --verificar`, y después `--ejecutar` | Hace falta root para:<br>• convertir el clúster 5433 en servicio del sistema (`anfeca-pg8086`, SELinux `postgresql_db_t` y puerto `postgresql_port_t`);<br>• crear el pool PHP-FPM 8.5.12 como `apache` (`anfeca8086-fpm`): los documentos privados son `drwx--S---` de `apache`;<br>• etiquetar los puertos 19086 y 18087 como `http_port_t`;<br>• crear `/var/www/ANFECA_8086`;<br>• agregar un VirtualHost candidato solo en loopback |
| 2 | Responsable | Autorizar o rechazar los renombres `conv_lugar_entrega`, `conv_evento_entrega` y `soli_art13` | Si se autorizan, el release aplica la alineación PDM. Si no, se requiere una migración de la variante A con ajustes de código (estimados en §2) |
| 3 | Responsable | Confirmar D-11 (matriz §4) | Desbloquea la migración PDM, la conciliación y la equivalencia |
| 4 | Administrador, en la ventana | `sudo ANFECA_AUTORIZO_CONMUTACION=si bash scripts/anfeca/despliegue_8086/02_conmutar_root.sh` | Mantenimiento, respaldo final, sincronización, VirtualHost de 8086 → PHP-FPM 8.5.12 + `certificacion_anfeca`, verificaciones y reversión automática ante fallas |

Después de la conmutación:

```
ANFECA_E2E_PERMITIR_8086=si E2E_CURP1=<CURP ficticia> python3 -I scripts/anfeca/pruebas/e2e_candidato.py http://192.168.7.130:8086 <salida> <huellas.json>
```

Esto crea solo registros ficticios y omite el refrendo.
