# Parches pendientes — aplicar sobre el código VIGENTE del servidor

> Rama `ANFECA_BD_final` · 20/11/2026.
> Estos parches se detectaron leyendo el repositorio, que se quedó en el commit del **03/nov**. **No se aplicaron**: por decisión acordada, en esta rama solo hay cambios aditivos, porque el servidor `/var/www/ANFECA_PRUEBAS` tiene código más reciente (04–19/nov) sin subir.
> Antes de aplicar cada parche:
> 1. Comprueba que el problema sigue existiendo en el archivo actual del servidor.
> 2. Haz un respaldo del archivo (`cp archivo archivo.antes_parcheN`).
> 3. Aplica el parche y ejecuta `php -l`.
> 4. Haz la prueba indicada y anota Pasa, Falla o No probado.

| # | Prioridad | Archivo | Problema |
|---|---|---|---|
| P1 | **Crítica** | `DatosSolicitanteController::store` | Usa variables no definidas → el registro de usuarios nuevos falla con error 500 |
| P2 | **Alta** | `DatosSolicitanteController::update` + `ActualizarDatos.vue` | La regla de edición está invertida respecto a la acordada |
| P3 | Alta | `store` / `update` | Falta exigir al menos un teléfono (`required_without`) |
| P4 | Media | `routes/web.php` | `/registro` apunta a la vista `registro.create`, que no existe (error 500) |
| P5 | Media | `routes/web.php` | `GET /registro/datos-personales` sin `guest`, mientras el POST sí lo tiene |
| P6 | Media | `RecuperacionPasswordController` | Mínimo de 8 caracteres en la recuperación frente a 12 en el registro |
| P7 | Baja | `SeleccionCertificacionController::create` | Calcula los antecedentes y los descarta (consulta inútil) |
| P8 | Media | `FormacionAcademicaController` / `DocumentoProbatorioController` | Al reemplazar un archivo, el anterior queda huérfano y sin registro de versión |
| P9 | Baja | `ExampleTest` | Prueba de plantilla que espera 200 en `/`, pero la app redirige a login (302) |

---

## P1 — Registro roto por variables no definidas (crítica)

**Dónde:** `app/Http/Controllers/DatosSolicitanteController.php`, método `store()`, bloque *«Si la identidad está autorizada…»*.

**Problema:**
- `store()` usa `$identidadAutorizada` y `$camposActualizables`, que solo existen en `update()`.
- En PHP 8, Laravel convierte el aviso «Undefined variable» en una `ErrorException`.
- Resultado: `POST /registro/datos-personales` responde **500** y nadie puede registrarse.
- En `store()` el usuario siempre es nuevo, así que ese bloque no tiene sentido ahí.

**Cambio:** eliminar el bloque y validar directamente.

```php
// ANTES (eliminar)
	if ($identidadAutorizada) {
	    $reglas = array_intersect_key(
	        $reglas,
	        array_flip($camposActualizables)
	    );
	}

// DESPUÉS
        $datos = $request->validate($reglas);
```

**Prueba:**
1. Registrar un usuario ficticio nuevo → redirige a Certificación y crea la fila en `users` y en `datos_solicitantes`.
2. Revisar `storage/logs/laravel.log`: no debe haber `Undefined variable`.

---

## P2 — Regla de «Modificar datos» (confirmada el 20/nov)

**Regla acordada:**
- **Antes de autorizar:** solo se pueden editar los 7 campos de domicilio (calle, número exterior e interior, colonia, municipio, entidad, código postal) y `telefono_particular` y `telefono_celular`. CURP, RFC, cédula, nombres, apellidos y correo son **solo lectura**.
- **Después de autorizar:** no se muestra «Modificar datos» y el servidor **rechaza** cualquier `PUT /mis-datos`.

**Código actual del repo (al revés):**
- Antes de autorizar, todo es editable.
- Después de autorizar, se pueden editar domicilio y teléfonos.

**Backend:** en `DatosSolicitanteController::update()` sustituye todo el cuerpo:

```php
public function update(Request $request)
{
    $solicitante = DatosSolicitante::where('user_id', $request->user()->id)
        ->firstOrFail();

    // Después de autorizar no se permite ninguna edición en esta etapa.
    if ($solicitante->datos_autorizados) {
        return redirect()
            ->route('mis-datos.edit')
            ->with('error', 'Tus datos ya fueron autorizados y no pueden modificarse.');
    }

    // Antes de autorizar: solo domicilio y teléfonos.
    // Cualquier otro campo enviado se ignora: no se valida ni se guarda.
    $datos = $request->validate([
        'calle' => ['required', 'string', 'max:200'],
        'numero_exterior' => ['required', 'string', 'max:20'],
        'numero_interior' => ['nullable', 'string', 'max:20'],
        'colonia' => ['required', 'string', 'max:150'],
        'municipio' => ['required', 'string', 'max:150'],
        'entidad_federativa' => ['required', 'string', 'max:100'],
        'codigo_postal' => ['required', 'digits:5'],
        'telefono_particular' => ['nullable', 'string', 'max:20', 'required_without:telefono_celular'],
        'telefono_celular' => ['nullable', 'string', 'max:20', 'required_without:telefono_particular'],
    ], [
        'telefono_particular.required_without' => 'Captura al menos un teléfono (particular o celular).',
        'telefono_celular.required_without' => 'Captura al menos un teléfono (particular o celular).',
    ]);

    $solicitante->update($datos);

    return redirect()
        ->route('mis-datos.edit')
        ->with('success', 'Datos actualizados correctamente.');
}
```

Con esto ya no se modifican `users.name` ni `users.email` desde esta pantalla, porque el correo es de solo lectura.

**Frontend:** en `resources/js/components/ActualizarDatos.vue`:

```js
// Campos editables ANTES de autorizar (después: ninguno).
const camposEditables = [
    'calle', 'numero_exterior', 'numero_interior', 'colonia',
    'municipio', 'entidad_federativa', 'codigo_postal',
    'telefono_particular', 'telefono_celular',
];

function puedeEditar(nombre) {
    return !props.autorizadoInicialmente && camposEditables.includes(nombre);
}
```

Y en el botón «Modificar datos» cambia `v-else` por `v-else-if="!autorizadoInicialmente"`.

Verifica también en el servidor el bloque `v-if="editando && autorizadoInicialmente"` de la línea ~126, que deja de tener sentido.

**Prueba:**
1. Con un usuario sin autorizar: editar el domicilio → se guarda.
2. Mismo usuario: enviar un `PUT` manipulado con otra CURP (DevTools o `curl` con la cookie de sesión) → la CURP **no cambia**.
3. Autorizar: el botón «Modificar datos» desaparece.
4. Tras autorizar, un `PUT` manipulado → mensaje de error y ningún cambio en la BD.
5. Ningún otro usuario se ve afectado.

---

## P3 — Al menos un teléfono

Incluido en P2 para `update()`. En `store()` agrega lo mismo:

```php
'telefono_particular' => ['nullable', 'string', 'max:20', 'required_without:telefono_celular'],
'telefono_celular' => ['nullable', 'string', 'max:20', 'required_without:telefono_particular'],
```

Usa los mismos mensajes personalizados. En la vista, `array_unique($errors->all())` evita que el mensaje salga duplicado.

---

## P4 — `/registro` → vista inexistente

```php
// ANTES
Route::view('/registro', 'registro.create')->name('registro.create');
// DESPUÉS (conserva el nombre de la ruta por si algún enlace lo usa)
Route::redirect('/registro', '/registro/datos-personales')->name('registro.create');
```

Antes de cambiarlo, comprueba con `grep -rn "registro.create" resources/ app/` que nadie dependa de la vista.

---

## P5 — `GET /registro/datos-personales` sin `guest`

Mueve el `GET` dentro del grupo `Route::middleware('guest')` del `POST`.

Un usuario con sesión iniciada que abra la URL será redirigido. Configura el destino con `redirectUsersTo` en `bootstrap/app.php`, apuntando a `mis-datos.edit`, en lugar de mostrarle un formulario que fallará.

---

## P6 — Contraseña mínima uniforme (12)

En `RecuperacionPasswordController::guardarNuevaPassword()`:

```php
PasswordRule::min(12),
```

**Prueba:** en la recuperación, una contraseña de 10 caracteres debe ser rechazada con un mensaje en español.

---

## P7 — Consulta de antecedentes sin uso

En `SeleccionCertificacionController::create()`, o se pasa `'antecedentes' => $resultadoAntecedentes` a la vista, o se elimina la llamada.

En el servidor este controlador cambió mucho (refrendo y validación del certificado), así que revisa allí antes de tocarlo.

---

## P8 — Historial de versiones de archivos

**Requisito:** si se sustituye un documento, en especial el del último grado, el archivo anterior se conserva como historial.

**Situación:**
- En el repo, `updateOrCreate` sobrescribe la ruta y el archivo anterior queda huérfano en Storage, sin registro.
- En el servidor existe `historial_documentos_academicos` (14/nov).

**Propuesta:**
1. Antes de `updateOrCreate`, por cada campo reemplazado, insertar en el historial: `user_id`, `solicitud_id`, campo, ruta anterior, `sha256` y fecha.
2. No borrar nada de Storage.
3. Extender lo mismo a los probatorios.

---

## P9 — Prueba de plantilla

`tests/Feature/ExampleTest.php` espera 200 en `/`, pero la app redirige a `/login`. Cámbiala a `assertRedirect(route('login'))`.

Es la única prueba que falla hoy en `php artisan test`.
