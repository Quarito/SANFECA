# Parches: comparación contra el código vigente

> Rama `integracion/anfeca-bd-final` · base `desarrollo/anfeca-pruebas` @ `6c873c2` · 20/11/2026.
> La versión anterior de este documento se escribió sobre `main` (03/nov). Aquí cada parche se volvió a revisar contra el código vigente; los marcados como **confirmado** se reprodujeron con pruebas temporales sobre SQLite en memoria.
> **Ningún parche se aplicó.** Esta rama solo agrega archivos. Aplica cada parche en una rama propia, de uno en uno, con respaldo previo.

## Resumen

| # | Problema original (03/nov) | Estado en `6c873c2` | Acción |
|---|---|---|---|
| P1 | `store()` usaba variables no definidas → registro con 500 | **Resuelto.** La prueba `test_registro_nuevo_crea_usuario_y_datos` pasa. | Ninguna |
| P2 | Regla de «Modificar datos» invertida | **Resuelto.** Backend: tras autorizar se rechaza; antes, solo domicilio y teléfonos. Vue: `puedeEditar()` y el botón solo sin autorizar. Lo cubren 3 pruebas. | Ninguna |
| P3 | Faltaba exigir al menos un teléfono | **Resuelto** en `store`, `update` y `autorizar` (`required_without` + `digits:10`) | Ninguna |
| P4 | `/registro` → vista `registro.create` inexistente | **Sigue: confirmado 500** | Aplicar P4 |
| P5 | `GET /registro/datos-personales` sin `guest` | **Cambió.** Ahora es intencional (un usuario con sesión y sin datos completa su registro), pero un usuario que **ya tiene datos** puede reenviar el POST → **500** por unicidad de `datos_solicitantes.user_id` (confirmado) | Aplicar P5 (nuevo) |
| P6 | Mínimo de 8 caracteres en la recuperación frente a 12 en el registro | **Sigue: confirmado** (se aceptó una contraseña de 9 caracteres) | Aplicar P6 |
| P7 | Antecedentes calculados y descartados | **Resuelto.** `resultadoAntecedentes` se pasa a la vista. | Ninguna |
| P8 | Sin historial al reemplazar archivos | **Parcial.** Título y cédula del último grado quedan en `historial_documentos_academicos` y el archivo previo se conserva. Los probatorios conservan el archivo en Storage, pero **no** registran la versión anterior, y el origen tampoco. | Aplicar P8 (reducido) |
| P9 | `ExampleTest` espera 200 en `/` | **Sigue** | Aplicar P9 |

---

## P4 — `/registro` (sigue)

`routes/web.php`, sección «REGISTRO INICIAL»:

```php
// ANTES
Route::view('/registro', 'registro.create')
    ->name('registro.create');

// DESPUÉS (conserva el nombre de la ruta)
Route::redirect('/registro', '/registro/datos-personales')
    ->name('registro.create');
```

**Antes de aplicar:**

```bash
grep -rn "registro.create\|url('/registro')\|\"/registro\"" resources/ app/ routes/
```

**Prueba:** `GET /registro` → 302 hacia `/registro/datos-personales`.

## P5 — Registro duplicado con sesión iniciada (nuevo)

En `DatosSolicitanteController::store()`, al inicio del método:

```php
if ($request->user()
    && DatosSolicitante::where('user_id', $request->user()->id)->exists()) {
    return redirect()
        ->route('mis-datos.edit')
        ->with('error', 'Ya existe un registro de datos personales para esta cuenta.');
}
```

En `create()` aplica la misma redirección, para no mostrar un formulario de alta a quien ya tiene datos.

**Prueba:** un usuario ficticio con datos que hace el POST debe ser redirigido a `mis-datos.edit` (sin 500) y no debe crearse una segunda fila.

## P6 — Contraseña mínima uniforme (sigue)

`app/Http/Controllers/Auth/RecuperacionPasswordController.php`, método `guardarNuevaPassword()`:

```php
PasswordRule::min(12),
```

Revisa también que `NuevaPassword.vue` indique «mínimo 12 caracteres».

**Prueba:** un restablecimiento con 9 caracteres debe rechazarse; con 12 o más, aceptarse.

## P8 — Historial de probatorios (reducido)

Lo vigente cumple el requisito explícito: el título y la cédula del **último grado** se conservan como historial. Para completar la trazabilidad:

- En `DocumentoProbatorioController::store()`, dentro de la transacción y antes de `updateOrCreate`, registra cada archivo reemplazado.
- Mantén la regla actual de no borrar nada de Storage.

**Opciones:**
1. **Reutilizar** `historial_documentos_academicos`, con `tipo_documento` = nombre del campo. Sin migración. La tabla se llama «académicos», pero ya es genérica: `solicitud_id`, `tipo_documento`, `ruta_archivo`.
2. **Crear una tabla nueva** `historial_documentos_solicitud`: requiere una migración aditiva.

Se recomienda la opción 1, porque no cambia el esquema.

## P9 — Prueba de plantilla (sigue)

`tests/Feature/ExampleTest.php`:

```php
$this->get('/')->assertRedirect(route('login'));
```

Con este cambio, `php artisan test` queda en verde: 32/32 en el entorno de integración.

---

## Observaciones nuevas (sin parche propuesto todavía)

1. **`update()` con identidad incompleta.**
   - `update()` valida CURP, RFC y correo **guardados** como `required`.
   - Si un expediente ficticio se creó con CURP nula (la migración del 30/oct la permite), el usuario no podrá guardar ni su domicilio y verá un error de un campo que no puede editar.
   - Revisarlo con un caso ficticio antes de corregir.
2. **Mensajes de validación en inglés en las pruebas.**
   - Con `APP_LOCALE=en` (valor de `.env.example`), un mensaje sin texto personalizado sale en inglés, por ejemplo «The link field is required.».
   - En el servidor, confirma que `.env` tenga `APP_LOCALE=es` (existe `lang/es`).
3. **Patrón de `MigracionesAnfecaTest`.** Ver `PRUEBAS_REGRESION.md`. Funciona hoy, pero falla si la clase crece.
