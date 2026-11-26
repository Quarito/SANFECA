# Diagnósticos: recuperación de contraseñas, mensajes de incompatibilidad y zonas · 2026-11-26

**Commit de referencia:** `9b21c42`. Análisis estático y consultas de **solo lectura** en 8086. No se cambió código, configuración, correo ni datos.

---

## A. Recuperación de contraseñas (pendiente de fases anteriores)

### A.1 Flujo actual

| Paso | Ruta | Controlador / vista | Observaciones |
|---|---|---|---|
| Solicitud | `GET /recuperar` (`password.request`) | `RecuperacionPasswordController::mostrarSolicitud` → `auth/recuperar-password` | Pide **CURP y correo** |
| Envío | `POST /recuperar` (`password.email`), `throttle:recuperacion` (3 por minuto por IP y 5 por hora por correo) | `enviarEnlace` | Busca `datos_solicitantes` por CURP y correo; si coincide también `users.email`, llama a `Password::broker()->sendResetLink`. **Siempre** redirige a la confirmación |
| Confirmación | `GET /recuperacion-enviada` | Vista `auth/recuperacion-enviada` | Mensaje idéntico exista o no la cuenta |
| Nueva contraseña | `GET /restablecer/{token}?email=` (`password.reset`) | `mostrarNuevaPassword` → `auth/nueva-password` | El correo va oculto, tomado del enlace |
| Guardado | `POST /restablecer` (`password.update`), `throttle:restablecer` (5 por minuto y 10 por hora) | `guardarNuevaPassword` | Mínimo 12 caracteres y confirmación (igual que el registro). Regenera `remember_token`; espejo PDM. Error genérico «El enlace es inválido o ha expirado» |
| Tokens | Tabla `password_reset_tokens` | `config/auth.php` | Caducidad **60 min**; 60 s entre solicitudes; token con hash |

### A.2 Hallazgos

| # | Hallazgo | Severidad | Evidencia |
|---|---|---|---|
| R1 | **En 8086 no se envía ningún correo**: `MAIL_MAILER=array` (los mensajes se descartan en memoria). El solicitante ve «enviado», pero nunca recibe el enlace | **Alta** (funcionalidad inoperante) | `.env` de 8086 |
| R2 | `APP_URL=http://100.96.71.121:8086`: el enlace apuntaría a una IP desde la que 8086 responde **403** | Alta | `curl` a esa IP: 403; a 192.168.7.130: 200 |
| R3 | `app.locale = en` y no existe `lang/es.json`: la notificación estándar de Laravel saldría **en inglés** («Reset Password Notification») | Media | `config/app.php`, `lang/` |
| R4 | Las cuentas de coordinación (sin `datos_solicitantes`) **no pueden** recuperar su contraseña por este flujo | Media (decisión) | `enviarEnlace` exige CURP |
| R5 | Enumeración: la respuesta es idéntica en todos los casos (correcto). Persiste una diferencia de tiempo menor (se genera token y correo solo si existe la cuenta) | Baja | Código |
| R6 | No hay pruebas automatizadas del flujo completo (solo `SeguridadAnfecaTest::test_p6`, longitud mínima) | Media | `tests/Feature` |
| R7 | Remitente por omisión `hello@example.com` en `.env.example` | Media | `.env.example` |

### A.3 Plan de corrección (NO implementado; requiere autorización)

1. **Configuración de correo** (decisión del responsable): servidor SMTP institucional o servicio autorizado, remitente oficial, SPF/DKIM del dominio. Solo en el `.env` de cada ambiente, nunca en el repositorio.
2. `APP_URL` con el nombre o la IP real de acceso de cada ambiente.
3. Notificación propia en español (`App\Notifications\RestablecerContrasena`) con el texto institucional, la caducidad y una advertencia de seguridad. Usar `Password::broker()->sendResetLink` con el callback que envía esa notificación.
4. Igualar los tiempos de respuesta: envío en cola, o `usleep` aleatorio acotado.
5. Decidir la recuperación para coordinación: por correo institucional sin CURP, o restablecimiento administrativo (`rotar_clave_coordinador.php`).
6. `APP_LOCALE=es` y completar `lang/es/validation.php`.

### A.4 Pruebas propuestas (usuarios ficticios; `Mail::fake()` / `Notification::fake()`; nunca correos reales)

| Caso | Resultado esperado |
|---|---|
| CURP y correo correctos | Una notificación al usuario ficticio, con enlace a `APP_URL/restablecer/{token}?email=` |
| CURP correcta y correo ajeno, o al revés | Sin notificación; misma respuesta y mismo destino |
| Cuenta inexistente | Sin notificación; misma respuesta |
| Cuatro solicitudes en un minuto | La cuarta con 429; sin notificaciones adicionales |
| Token válido y contraseña de 12 o más | Contraseña cambiada, `remember_token` nuevo, inicio de sesión correcto |
| Token caducado (más de 60 min con `travel`) | Error genérico; contraseña sin cambios |
| Token reutilizado | Error genérico |
| Token de otro usuario | Error genérico |
| Contraseña corta o sin confirmación | Error de validación en español |
| Cuenta de coordinación | Según la decisión del punto 5 |

Verificación manual en un ambiente desechable con `MAIL_MAILER=log` (el correo queda en `storage/logs`), nunca en el sitio activo.

---

## B. Mensajes de incompatibilidad (pendiente de la prueba 3.5.3)

### B.1 Reglas involucradas

| Regla | Dónde | Llega al navegador como | ¿Se ve? |
|---|---|---|---|
| Certificación de un borrador activo distinto | `SeleccionCertificacionController::store` | `withErrors(['certificacion'])` | ✔ Lista de errores de `certificacion.blade.php` |
| Certificación o número de un refrendo existente | Ídem | `withErrors(['certificacion' / 'numero_certificado_anterior'])` | ✔ |
| Certificado no validado o no vigente (año del siguiente refrendo) | Ídem; `ConsultaAntecedentesService` | `withErrors(['numero_certificado_anterior'])` | ✔ |
| Consulta del antecedente (AJAX `/registro/certificacion/consultar`) | `SeleccionCertificacion.vue` | `mensajeConsulta` | ✔ Aviso en el componente |
| Tipo de personal o área incompatible con el antecedente | Solo en **modo PDM** (disparadores `anfeca_compat` → `ErrorValidacionPdm`) | `ValidationException` (`tipo_personal`…) | ✔ en la lista. En modo `public` (actual) **no existe esa regla** |
| «No existe una solicitud válida para continuar», «No fue posible validar el certificado histórico del refrendo», «No se encontró una solicitud para revisar» | `FormacionAcademicaController` (create, store), `DatoRefrendoController` (×4), `DocumentoProbatorioController`, `RegistroCompletadoController` (×2) | `redirect()->route('registro.certificacion')->with('error', …)` | ✘ **NO**: `certificacion.blade.php` solo muestra `session('success')` y `$errors` |
| «Debes revisar y autorizar tus datos personales antes de continuar» | `VerificarDatosAutorizados` (middleware de todas las etapas) | `redirect()->route('mis-datos.edit')->with('error', …)` | ✘ **NO**: `solicitante/mis-datos.blade.php` no muestra `session('error')` |
| «Los datos personales ya fueron autorizados…», «Completa los datos personales obligatorios antes de autorizar» | `DatosSolicitanteController` | Ídem (+ `withErrors` en el segundo) | ✘ el mensaje general; ✔ los errores por campo |

### B.2 Escenarios sin retroalimentación visible (confirmados por el código)

1. Ir a Formación, Documentos, Refrendo o Finalización con un antecedente que ya no valida (certificado desvinculado, vigencia cambiada o solicitud inconsistente): **regresa a Certificación sin explicación**.
2. Intentar avanzar sin autorizar los datos personales: **regresa a Mis datos sin explicación**.
3. Intentar modificar datos ya autorizados: no ocurre nada visible.

Las vistas Refrendo, Formación, Documentos, Compromiso y Completado **sí** muestran `session('error')`.

### B.3 Observación de consistencia (sin cambiar reglas normativas)

En modo `public`, `tipo_personal` y `area_certificacion` solo se validan como texto obligatorio (máximo 100/150), **sin** `Rule::in` de los valores ofrecidos por la interfaz. Un POST manipulado podría guardar un valor fuera del catálogo; el modo PDM sí lo rechaza.

**Propuesta:** validar contra las mismas listas que muestra la interfaz (o contra el catálogo de áreas de `config/anfeca_equivalencias.php`). No es una regla nueva de elegibilidad: alinea la validación con las opciones existentes.

### B.4 Propuesta de corrección (NO implementada)

1. Agregar el aviso homologado de error (`anfeca-aviso anfeca-aviso--error`, `role="alert"`) con `session('error')` en `certificacion.blade.php` y en `solicitante/mis-datos.blade.php`. Mejor aún: un componente Blade `x-aviso-sesion` reutilizado en todas las pantallas del solicitante (estándar §7 bis.8).
2. En la redirección por antecedente no válido, indicar la causa concreta cuando se conozca (vigencia, desvinculación) y el siguiente paso.
3. `Rule::in` para tipo de personal y área (B.3).
4. Locale `es` (A.3-6) para los mensajes genéricos.

### B.5 Casos de prueba propuestos

| Caso | Esperado |
|---|---|
| Usuario sin datos autorizados abre `/registro/formacion-academica` | Redirección a Mis datos **y** el texto «Debes revisar y autorizar…» visible en el HTML |
| Refrendo cuyo certificado se desvincula después de iniciarlo; abrir Formación | Certificación con «No fue posible validar el certificado histórico del refrendo» visible |
| Usuario sin solicitud abre Documentos probatorios | Certificación con «No existe una solicitud válida para continuar» visible |
| POST de certificación con `tipo_personal` fuera de la lista | Error de validación visible; sin cambios |
| Borrador activo de otra certificación | Mensaje visible (regresión) |
| Certificado vigente | Mensaje con el año del siguiente refrendo (regresión) |

---

## C. Coordinación y zonas (actualización, solo lectura en 8086)

| Verificación | Estado al 26-nov |
|---|---|
| Siete zonas oficiales | ✔ En `config/anfeca_zonas.php` (7 zonas con sus entidades) |
| Catálogo `zonas` en la base | **0 filas**: normalización pendiente |
| Instituciones | 3, todas con `zona_id` NULL y `zona_anfeca` textual coherente: 1 → «Zona 6 - Sur», 2 → «Zona 4 - Centro Occidente», 3 → «Zona 3 - Centro» |
| Visualización | ✔ La interfaz muestra la denominación oficial resuelta desde el texto (`ZonasAnfeca`); «Sin clasificar» cuando no se identifica |
| Coordinadores | 1 nacional y 3 regionales; **0** asignaciones en `coordinador_zona` |
| Acceso por `zona_id` | ✔ La autorización regional depende solo de `zona_id` + `coordinador_zona` (`AlcanceZona`). Los regionales no ven nada hasta asignar; el texto nunca concede acceso (`ZonasAnfecaTest`, `CoordinacionTest`: incluidos en la regresión del 26-nov, 182 pruebas correctas) |
| Protección de expedientes y documentos | ✔ Acceso directo por URL a otra zona: 403/404; documentos solo por rutas protegidas (pruebas de coordinación). Verificación en vivo: regionales con 0 expedientes; manipulación `zona=` → 403 |
| Separación nacional / regional | ✔ La nacional ve los 3 expedientes enviados; las regionales, ninguno |
| Solicitudes en 8086 | 3 enviadas y **1 en borrador**. El borrador no figuraba el 25-nov: lo creó un usuario del ambiente; ninguna prueba de esta intervención escribe en 8086 |

**Orden recomendado (cada paso con autorización expresa):**
1. Respaldo verificado (`respaldo_verificado.sh`).
2. Normalización (SQL de `ZONAS_DIAGNOSTICO_Y_NORMALIZACION.md` §4): 7 filas en `zonas` y `zona_id` en las 3 instituciones, solo con coincidencia exacta.
3. Verificar en 8086 que la etiqueta no cambia y que la resolución pasa a «normalizada».
4. Asignar coordinadores regionales (§5) según el directorio oficial de coordinaciones regionales (hoy no disponible) o una decisión expresa para las cuentas de prueba.
5. Verificar el alcance regional en vivo (cada regional ve solo su zona; la nacional, todo).
6. Solo después, y por separado, considerar la activación de la revisión documental.
