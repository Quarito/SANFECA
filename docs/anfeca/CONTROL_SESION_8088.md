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

## 2. Solución

1. **`App\Http\Middleware\ControlInactividad`** (grupo `web`): guarda en la sesión el momento de la última petición del usuario.
   `GET /sesion/estado` **no** cuenta como actividad, así que consultar nunca prolonga la sesión. Si pasan más de
   `session.lifetime` (120 min) sin actividad: cierra la autenticación, invalida la sesión y regenera el token; no reactiva nada.
   Responde 401 JSON con `X-Anfeca-Sesion: finalizada` a las peticiones AJAX y redirige a `/sesion-finalizada` en las demás.
   Un visitante que solo consulta una página continúa con una sesión nueva (sin bucles).
2. **`App\Http\Controllers\SesionController`**: `GET /sesion/estado` (lectura), `POST /sesion/continuar` (CSRF; solo llega
   una sesión vigente porque el middleware ya rechazó las vencidas), `POST /sesion/cerrar` (fin de la cuenta regresiva) y
   `GET /sesion-finalizada` (mensaje). `throttle:30,1` en estado y continuar.
3. **Aviso** `resources/views/partials/control-sesion.blade.php` (CSS y JS en línea, sin dependencias ni compilación):
   - El servidor entrega **segundos restantes**, no horas absolutas (inmune a relojes desincronizados).
   - **Sin consultas periódicas**: una sola consulta de estado poco antes del vencimiento (`120 min − 15 s − 45 s de margen`).
   - Si hubo actividad **real** en la pantalla (teclado, captura, clic) desde la última sincronización, se renueva una vez sin
     interrumpir; si no, aparece el aviso con cuenta regresiva de **15 s** y **Continuar sesión**.
   - La cuenta regresiva termina 45 s antes del vencimiento real, para que la renovación llegue a tiempo.
   - Sin respuesta: `POST /sesion/cerrar` → «Su sesión finalizó».
   - Con la sesión terminada se bloquea el envío de formularios; se evita el doble envío del mismo formulario.
   - Las respuestas 401/419 de los `fetch` existentes llegan intactas a sus componentes; además se muestra el aviso.
   - No guarda datos en `localStorage`/`sessionStorage` y nunca borra lo capturado.
4. **419**: `resources/views/errors/419.blade.php` (página amigable con el diseño de recuperación de contraseña, código 419
   conservado) y respuesta JSON controlada en `bootstrap/app.php`. El middleware CSRF no se toca ni se excluyen rutas.
5. Configuración `config/anfeca_sesion.php`: `ANFECA_SESION_CONTROL` (true), `ANFECA_SESION_AVISO_SEGUNDOS` (15),
   `ANFECA_SESION_MARGEN_SEGUNDOS` (45). La duración sigue siendo `SESSION_LIFETIME` (120).

## 3. Archivos

Creados: `app/Http/Middleware/ControlInactividad.php`, `app/Http/Controllers/SesionController.php`, `config/anfeca_sesion.php`,
`resources/views/partials/control-sesion.blade.php`, `resources/views/sesion/pagina.blade.php`,
`resources/views/sesion/finalizada.blade.php`, `resources/views/errors/419.blade.php`,
`scripts/anfeca_8088/pruebas/prueba_control_sesion.py`, `scripts/anfeca_8088/pruebas/prueba_control_sesion_navegador.cjs`,
este documento y `docs/anfeca/evidencias_8088/control_sesion/`.

Modificados (solo inserciones): `bootstrap/app.php` (registro del middleware y del 419), `routes/web.php` (4 rutas),
`resources/views/components/barra-usuario-certificacion.blade.php`, `resources/views/solicitante/mis-datos.blade.php`,
`resources/views/panel.blade.php`, `resources/views/registro/datos-personales.blade.php` (una línea `@include` cada una).

Sin cambios: controladores y reglas de negocio, Vue, `config/session.php`, `config/auth.php`, base de datos, dependencias,
`.env.example`, Coordinación (solo recibe el manejo amigable del 419) y el temporizador del acuse.

## 4. Pruebas (copia aislada en el entorno de trabajo, puerto 8188, sin tocar 8088/8086/8008)

Duración reducida **solo en la copia**: `SESSION_LIFETIME=1`, margen 20 s, aviso 15 s. Sin reiniciar ni alterar la base.

| Prueba | Resultado |
|---|---|
| Usuario activo | Aprobada: su actividad renueva la sesión vigente; no ve aviso |
| Usuario inactivo | Aprobada: aviso antes del vencimiento |
| Aviso de 15 s | Aprobada: 15, 14, 13, 12… |
| Continuar sesión | Aprobada: misma pantalla, datos intactos, 60 s renovados |
| Sesión vencida | Aprobada: «continuar» responde 401; no hay acceso autenticado |
| Estado no renueva | Aprobada: restante 44 → 29 → 14 |
| Token CSRF vencido | Aprobada: 419 amigable (HTML y JSON), sin trazas |
| Formulario con datos sin guardar | Aprobada: el aviso no los borra; con la sesión terminada se impide el envío |
| Envío con sesión vencida | Aprobada: redirección a «sesión finalizada», sin 419 ni bucles |
| Cierre manual / inicio de sesión | Aprobada |
| AJAX con 401/419 | Aprobada: el componente recibe su respuesta; aviso visible |
| Acuse PDF y su temporizador | Aprobada: descarga y cuenta regresiva propias sin interferencia |
| Navegación entre etapas | Aprobada: 5 etapas con 200 |

Totales: servidor **16/16**, navegador **11/11**; regresión de catálogos **4/4**; regresión completa `e2e_8088.py` (120 min)
**24/28**: las 4 restantes dependen de dar de alta la persona ficticia del refrendo, que ya existía porque la base del laboratorio
no se reinició (por indicación de no ejecutar operaciones destructivas). En la rama base esas 4 pasaron sobre base limpia y esta mejora
no modifica sus controladores. Esquema verificado después: 36/36 tablas, sin diferencias.

## 5. Riesgos y limitaciones

- No se recuperan datos no guardados si la sesión termina: no se almacenan en el navegador por seguridad.
- La renovación por actividad real requiere interacción del usuario; leer sin tocar la pantalla durante 2 h mostrará el aviso.
- Si el equipo se suspende, el aviso se revisa al volver a la pestaña; si la sesión ya venció, se informa y se pide iniciar sesión.
- Coordinación no muestra el aviso preventivo (fuera de alcance); sí recibe el 419 amigable y el cierre por inactividad del servidor.
- `throttle:login` (15/h por correo) se mantiene; afecta pruebas repetidas, no el uso normal.

## 6. Integración y reversión

Integración (tras revisión): merge de `feature/control-sesion-8088` en la rama definitiva; en el servidor, `git pull`,
`php artisan config:cache`, `php artisan view:clear`. No requiere `composer`, `npm`, migraciones ni reinicios de servicios.
Desactivación inmediata sin revertir código: `ANFECA_SESION_CONTROL=false` + `php artisan config:cache`.
Reversión completa: `git revert` del merge (solo archivos de la aplicación; nada que revertir en la base).
