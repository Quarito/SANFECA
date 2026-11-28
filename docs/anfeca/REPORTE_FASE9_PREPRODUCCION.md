# Reporte preliminar de preparación · Fase 9 (SEPOMEX, domicilio y sesiones)

Fecha: 28/11/2026 · Estado: **candidato listo para validarse en 8088. Nada se ha ejecutado en el servidor 8088, ni en
8008, ni en producción.** Todo lo que se reporta como «probado» se probó en un entorno aislado equivalente (PHP 8.5.12 y
PostgreSQL 18.6 compilados de fuente, bases nuevas y desechables, datos ficticios).

## 1. Rama y commits

Rama de integración controlada: **`integracion/fase9-8088`** (publicada en GitHub; sin merge a `main`).
Base: `integracion/anfeca-36-tablas-definitivo` `6c73286` (versión desplegada en 8088).

| Commit | Contenido |
|---|---|
| `b310871`, `e23f18a` | Control de sesión: aviso de inactividad, cuenta regresiva de 15 s, renovación explícita, cierre exacto, 401/419 distinguidos |
| `76095ce`, `1269b8f`, `5506eb8` | Importador SEPOMEX (diagnóstico de solo lectura y carga aditiva) y consulta por código postal |
| `5cb909b` | Merge de las mejoras de sesión en `desarrollo/catalogo-sepomex` |
| `8fb172f`, `da4f8bc`, `5e7c7d3` | Domicilio en el modelo vigente, validación de captura, presentación de Mis datos, batería de aceptación |
| **`34b04d2`** | **Commit de integración** (merge `--no-ff` sobre `6c73286`) — es el código que se desplegaría |
| `c129c35`, `4d81bda`, `c15ccf0` | Solo scripts de operación, evidencias y documentación (no cambian la aplicación) |

## 2. Archivos modificados (6c73286 → 34b04d2: 38 archivos)

- **Aplicación**: `ImportarSepomex`, `LectorSepomex`, `ConsultaCodigoPostal`, `ValidacionDomicilio`, `CodigoPostalController`,
  `DatosSolicitanteController`, `ExpedienteSolicitante`, `Catalogo`, `CargarCatalogosAnfeca`, `ControlInactividad`,
  `SesionController`, `bootstrap/app.php`, `routes/web.php`.
- **Configuración**: `config/anfeca_sesion.php` (nuevo), `config/anfeca_8088.php` (sin tipos de comunicación geográficos ni
  lista de entidades), `config/anfeca_pdm_decisiones.php` (retirado).
- **Interfaz** (sin cambios de diseño): `codigoPostal.js` (nuevo), `DatosPersonales.vue`, `ActualizarDatos.vue`, vistas de
  sesión, `errors/419`, parcial `control-sesion`, inclusión del parcial en `panel`, `mis-datos`, `datos-personales` y barra.
- **Pruebas y operación**: `scripts/anfeca_8088/pruebas/*`, `scripts/anfeca_8088/servidor/fase9/*` (bloques 1–8).
- **Documentación**: `FASE_9_SEPOMEX.md`, `CONTROL_SESION_8088.md`, `OPERACION_8088_FASE9.md`, `INTEGRACION_36_TABLAS_8088.md`.

## 3. Estado del esquema

Sin cambios estructurales: **36 tablas, 173 atributos, 35 FK** idénticos al SQL oficial de PowerDesigner
(`anfeca:verificar-esquema`: «Sin diferencias»). No hay migraciones, tablas, columnas ni esquemas nuevos. No se crea
`DOMI_ID_COLONIA`. El código postal, la colonia y el municipio se **validan en la captura** y no se persisten; `DOMICILIO`
guarda calle, número exterior, número interior y entidad federativa. `COMUNICACION` solo guarda correo y teléfonos.

## 4. Resultado de importación (base aislada)

Versión SEPOMEX 21/11/2026, `CPdescarga.xls` SHA-256 `0a8e2611…a052821`: 159 527 filas → **32** entidades,
**2 478** municipios, **158 507** colonias; **1 020** registros consolidados (solo difieren en tipo de asentamiento);
166.8 s; 515 MB pico; segunda ejecución y diagnóstico posterior con **0 pendientes** (idempotente). El archivo original
queda fuera de GitHub (en el servidor: `/var/lib/anfeca8088/sepomex/`, permisos 750).

## 5. Resultados de pruebas (entorno aislado)

| Batería | Resultado |
|---|---|
| `e2e_8088.py` (registro de primera vez, refrendo, recuperación, coordinación, 36 tablas) | 30/30 |
| `e2e_catalogos_8088.py` (catálogos ANUIES) | 4/4 |
| `prueba_codigo_postal_navegador.cjs` (Mis datos, modificación, autorización, bloqueo, colonia del catálogo) | 12/12 |
| `prueba_control_sesion.py` (servidor: inactividad, 401/419, invitado y autenticado) | 22/22 |
| `prueba_control_sesion_navegador.cjs` (aviso, continuar, cierre, sesión perdida) | 13/13 |
| **Total** (ejecutado mediante el bloque 7B tal como correría en el servidor) | **81/81** |

Bloque 4 (diagnóstico SEPOMEX) ensayado tal cual: 0 pendientes y esquema 36/36. Los bloques 1, 2, 3, 5, 6 y 8 dependen de
rutas, servicios y permisos del servidor: solo se verificó su sintaxis y su lógica de detención.

## 6. Evidencias

`docs/anfeca/evidencias_8088/fase9/` — `ensayo_04/` (diagnóstico y registro), `ensayo_07B/` (salidas de las cinco
baterías, totales, registro del bloque y capturas `cp_02_mis_datos`, `cp_03_modificar`, `cp_04_autorizado`). Solo datos
ficticios (`@example.test`); sin contraseñas.

## 7. Pendientes

1. **Ejecutar los bloques 1–7 en 8088** (`OPERACION_8088_FASE9.md`) y anexar sus registros. Hasta entonces la fase no
   está validada en 8088.
2. Revisar en el bloque 1 si `/var/www/ANFECA_8088` tiene cambios no versionados (la comparación del diagnóstico anterior
   se interrumpió).
3. Instalar en el servidor `python3-requests` y Playwright 1.63 + Chromium para la parte B, o ejecutarla por túnel.
4. Tras la validación en 8088: autorización expresa para el procedimiento de producción (8008 u otro).

## 8. Riesgos

| Riesgo | Mitigación |
|---|---|
| Código en `/var/www/ANFECA_8088` distinto del repositorio | El bloque 3 se detiene; se integra con commit propio antes de continuar |
| Registros geográficos guardados en `COMUNICACION` por la versión anterior | Se conservan y no se usan; no se eliminan datos |
| Importación de ~2 min 47 s y 515 MB de memoria | Fuera de horario; transacción única; si falla no queda nada parcial |
| Usuarios con sesión abierta durante el despliegue | Modo mantenimiento (`artisan down --retry=60`) |
| SELinux o permisos tras `rsync` | `restorecon` y verificación A4 del bloque 7 |
| Chromium no disponible en AlmaLinux | Ejecutar la parte B desde otro equipo por túnel SSH |

## 9. Procedimiento de despliegue (8088)

Bloques en orden, revisando cada registro antes del siguiente: `01_diagnostico` → `02_respaldo` (volcado, conteos, huellas
de expedientes y restauración comprobada en una base **nueva**) → `03_preparar_codigo` (directorio nuevo, compilación
Vite) → `04_diagnostico_sepomex` → `05_aplicar_sepomex` (`CONFIRMAR=si RESPALDO=…`) → `06_compilar_desplegar`
(`CONFIRMAR=si RESPALDO=…`, conserva `.env` y `storage/`) → `07_pruebas`. Detalle y puntos de control:
`OPERACION_8088_FASE9.md`.

## 10. Procedimiento de reversión

`08_reversion.sh` simula por omisión (`CONFIRMAR=si` para aplicar). `MODO=codigo`: retira (renombra, no borra) el
directorio desplegado, restaura el código y `.env` desde el respaldo del bloque 2, incorpora los archivos de `storage/app`
cargados después del respaldo y recarga PHP-FPM. `MODO=datos`: restaura el volcado en una base **nueva** y se cambia `DB_DATABASE` a ella (la base actual
no se borra). Las filas añadidas por SEPOMEX en los tres catálogos no afectan a la versión anterior, que no las consulta.
