# ANFECA · Control de sesión e inactividad (rama `feature/control-sesion-8088`)

Rama base: `integracion/anfeca-36-tablas-definitivo` (`6c73286`). Mejora aislada: sin cambios de base de datos, dependencias,
infraestructura ni reglas de negocio. **No desplegada.**

## 1. Diagnóstico inicial (solo lectura)

| Punto | Hallazgo |
|---|---|
| Controlador de sesiones | `config/session.php`: `driver` = `file` (por omisión; en 8088 `SESSION_DRIVER=file`), `lifetime` = 120, `expire_on_close` = false, `http_only` = true, `same_site` = lax. Sin tabla `sessions`. |
| `bootstrap/app.php` | Solo alias de middleware (`datos.autorizados`, `coordinacion`, `solo.solicitantes`) y JSON para `api/*`/`expectsJson`. No había manejo de 419. |
| Rutas de autenticación | `GET/POST /login` (`throttle:login`: 5/min por IP y 15/h por correo), `POST /logout` (auth), recuperación (`/recuperar`, `/restablecer`). |
| Control previo | **No existía**: ni `/estado-sesion`, ni `/sesion-finalizada`, ni aviso global, ni `resources/views/errors/`. Un 419 mostraba la página genérica «Page Expired». |
| Pantallas | No hay layout común del registro: cada vista es independiente. Comparten `components/barra-usuario-certificacion` (certificación, refrendo, formación, documentos, compromiso, finalización); `mis-datos`, `panel` y `datos-personales` son autónomas; Coordinación usa `coordinacion/layout`. `panel` y `login` no cargan `@vite`. |
| CSRF | `@csrf` en formularios; `X-CSRF-TOKEN` en `SeleccionCertificacion.vue`. Laravel 13 usa `PreventRequestForgery`: acepta peticiones del **mismo origen** por `Sec-Fetch-Site` aunque el token no coincida. Por eso, en navegadores modernos, una sesión vencida produce **401** (middleware `auth`) más que 419; el 419 aparece con navegadores sin `Sec-Fetch-Site` o clientes HTTP. |
| AJAX / `fetch` | `SeleccionCertificacion.vue` (POST consulta), `FormacionAcademica.vue` (catálogos), `VisorDocumento.vue` y componentes de Coordinación (GET). Todos envían `Accept: application/json`. |
| Temporizador del acuse | `registro/completado.blade.php`: `#aviso-cierre-acuse`, 15 s, envía `POST /logout`; se cancela con clic o tecla. Independiente. |
| Requisitos | La solución cabe en la sesión existente (archivos): no requiere tablas, colas, Redis ni servicios. |

## 2. Solución (versión revisada)

1. **`App\Http\Middleware\ControlInactividad`** (grupo `web`, declarado en la lista de prioridad **antes** de `Authenticate`):
   toda petición normal (navegación, envíos, AJAX) es actividad, como en Laravel; `GET /sesion/estado` **no** lo es.
   Cierra la sesión solo cuando la inactividad supera `session.lifetime`. Marca como «sesión perdida» la petición que trae la
   cookie de sesión cuando el servidor ya no tiene esa sesión.
2. **`App\Http\Controllers\SesionController`**: `GET /sesion/estado` (lectura) · `POST /sesion/continuar` (CSRF; renovación
   explícita) · `GET /sesion-finalizada`. Se **eliminó** `POST /sesion/cerrar`: el navegador ya no cierra sesiones.
3. **Aviso** `resources/views/partials/control-sesion.blade.php`: una consulta de estado poco antes del vencimiento, cuenta
   regresiva de 15 s y botón «Continuar sesión». La actividad local (teclear, mover el ratón) **no** renueva.
4. **Respuestas 401/419** (`bootstrap/app.php`, `respond`): el código y el cuerpo originales de Laravel **no** se modifican; solo se
   agrega `X-Anfeca-Sesion` cuando la causa es verificable: `expirada` (419 sin sesión autenticada), `token` (419 con sesión
   vigente: la página expiró), `finalizada` (401 de una sesión que existía y ya no está, o vencida por inactividad). Sin marca, el
   aviso no interviene. La página HTML del 419 es `resources/views/errors/419.blade.php`.
5. Configuración `config/anfeca_sesion.php`: `ANFECA_SESION_CONTROL` (true), `ANFECA_SESION_AVISO_SEGUNDOS` (15),
   `ANFECA_SESION_MARGEN_SEGUNDOS` (**5**, margen de red). La duración sigue siendo `SESSION_LIFETIME` (120).

### 2.1 Momento exacto del cierre (120 min, aviso 15 s, margen 5 s)

| Momento (inactividad) | Evento |
|---|---|
| 119:39 | El navegador consulta `/sesion/estado` (no renueva). Si hubo actividad en otra pestaña o por AJAX, solo reprograma. |
| 119:39–119:40 | Aparece el aviso con la cuenta regresiva de 15 s. |
| 119:55 | Termina la cuenta regresiva. **No se cierra nada**: «Continuar sesión» sigue disponible 5 s más (margen de red). |
| **120:00** | **Cierre real**, en el servidor, en la primera petición posterior: logout, invalidación y nuevo token. |
| 120:02 | El navegador verifica el estado y muestra «Su sesión finalizó». |

Antes de la revisión, la cuenta regresiva terminaba 45 s antes y el navegador cerraba la sesión a los 119:15. Medido en
laboratorio (límite de 60 s): sesión vigente a los 57 s; cierre informado a los 61.1 s después de la última actividad.

### 2.2 Decisiones de la revisión de seguridad

| Punto | Antes | Ahora | Justificación |
|---|---|---|---|
| 1. Renovación | La actividad local en el navegador renovaba automáticamente | Solo el clic en «Continuar sesión» o una petición real al servidor | Una pestaña abandonada con actividad local previa no debe prolongarse sin una acción explícita. |
| 2. Vencimiento anticipado | Cierre por el navegador 45 s antes | Cierre solo en el servidor a los 120:00 | Sin justificación funcional para cerrar antes; el margen solo protege la renovación de último segundo. |
| 3. Formularios | Bloqueo global de doble envío y bloqueo por reloj local | Sin bloqueo global; solo se impide enviar cuando el servidor **confirmó** el fin de la sesión | El bloqueo global podía afectar reintentos tras validación, formularios Vue y envíos que no navegan; el reloj local no ve la actividad AJAX. |
| 4. 401/419 | Cualquier 401/419 se mostraba como «sesión finalizada»; el JSON 419 se reescribía | Respuestas originales intactas; aviso solo con marca verificable; 419 con sesión vigente → «La página expiró» | Evita mensajes incorrectos (p. ej. 401/403 de permisos) y no altera lo que reciben los componentes. |
| Orden | `Authenticate` se ejecutaba antes del control | `prependToPriorityList(AuthenticatesRequests, ControlInactividad)` | Sin esto, una sesión perdida recibía el 401 genérico sin identificar la causa. |

## 3. Archivos

Creados: `app/Http/Middleware/ControlInactividad.php`, `app/Http/Controllers/SesionController.php`, `config/anfeca_sesion.php`,
`resources/views/partials/control-sesion.blade.php`, `resources/views/sesion/pagina.blade.php`,
`resources/views/sesion/finalizada.blade.php`, `resources/views/errors/419.blade.php`,
`scripts/anfeca_8088/pruebas/prueba_control_sesion.py`, `scripts/anfeca_8088/pruebas/prueba_control_sesion_navegador.cjs`,
este documento y `docs/anfeca/evidencias_8088/control_sesion/`.

Modificados (solo inserciones): `bootstrap/app.php` (middleware, prioridad y marca de 401/419), `routes/web.php` (3 rutas),
`resources/views/components/barra-usuario-certificacion.blade.php`, `resources/views/solicitante/mis-datos.blade.php`,
`resources/views/panel.blade.php`, `resources/views/registro/datos-personales.blade.php` (una línea `@include` cada una).

Sin cambios: controladores y reglas de negocio, Vue, `config/session.php`, `config/auth.php`, base de datos, dependencias,
`.env.example`, Coordinación (solo recibe el manejo amigable del 419) y el temporizador del acuse.

## 4. Pruebas (entorno aislado; sin tocar 8088/8086/8008 ni ninguna base existente)

Entorno: instancia PostgreSQL 18.6 **desechable y nueva** (`/var/lib/anfeca-pruebas-sesion`, puerto 55499, identificador
de sistema propio) con la base nueva `certificacion_anfeca_r3` creada desde el SQL oficial, datos ficticios
(`anfeca:laboratorio`, `anfeca:coordinador-alta`) y una copia de la rama servida en `127.0.0.1:8189`. Ninguna base existente se
borró ni se recreó. Duración reducida (`SESSION_LIFETIME=1`) solo en esa copia para las pruebas de tiempo.

| Prueba | Resultado |
|---|---|
| Regresión completa `e2e_8088.py` (120 min, base limpia), **incluidas las 4 de refrendo** | **28/28** |
| Regresión con catálogos de 8086 (`e2e_catalogos_8088.py`) | **4/4** |
| Servidor (`prueba_control_sesion.py`) | **22/22** |
| Navegador (`prueba_control_sesion_navegador.cjs`) | **13/13** |

Verificaciones destacadas: teclear no renueva (aviso a los 38.7 s del límite de 60); cuenta regresiva 15‑14‑13‑12;
«Continuar» conserva pantalla y datos; sesión vigente a los 57 s y cierre informado a los 61.1 s; una petición normal sí cuenta como
actividad; 403 de permisos y 401 sin sesión previa **sin** aviso; 401 de sesión perdida con aviso y respuesta original; 419 con
sesión vigente marcado como «token» y cuerpo original; reintentos de formularios gestionados por la página (3/3); visitante del
registro inicial con aviso «El formulario está por expirar» y formulario vencido sin crear cuenta; temporizador del acuse intacto;
cierre manual e inicio de sesión sin cambios.

Nota de prueba: el limitador existente de inicio de sesión (5/min por IP, 15/h por correo) se respetó. La prueba espera ante
un 429, y la caché del limitador se limpió solo en la copia aislada (`cache:clear`) tras corridas repetidas con la misma cuenta ficticia.

## 5. Riesgos y limitaciones

- No se recuperan datos no guardados si la sesión termina: no se almacenan en el navegador por seguridad.
- Quien capture durante 2 h sin enviar nada verá el aviso y deberá pulsar «Continuar sesión» (decisión de la revisión).
- Laravel 13 acepta formularios del mismo origen aunque el token haya cambiado (`PreventRequestForgery`); por eso los envíos
  con sesión vencida se detectan por inactividad y no por 419.
- Coordinación no muestra el aviso preventivo (fuera de alcance); sí recibe el cierre por inactividad y el 419 amigable.
- Tras suspender el equipo, el estado se verifica al volver a la pestaña.

## 6. Integración y reversión

Integración (tras revisión): merge de `feature/control-sesion-8088` en la rama definitiva; en el servidor, `git pull`,
`php artisan config:cache`, `php artisan view:clear`. No requiere `composer`, `npm`, migraciones ni reinicios de servicios.
Desactivación inmediata sin revertir código: `ANFECA_SESION_CONTROL=false` + `php artisan config:cache`.
Reversión completa: `git revert` del merge (solo archivos de la aplicación; nada que revertir en la base).
