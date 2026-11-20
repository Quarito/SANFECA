# F3 — Escritura en espejo `public` + `anfeca_modelo`

> Rama `integracion/pdm-f3`. Parte de `integracion/pdm-f2` (F1 + F2) **e incorpora `fix/seguridad-anfeca`** (merge sin conflictos), así que conserva todas las correcciones de seguridad.
> **Estado:** implementada y probada en PostgreSQL aislado. **No está activada en ninguna base de trabajo.** El modo por omisión sigue siendo `apagado`.

## 1. Comportamiento

| Modo efectivo | Escribe en | Lee de | Cómo se obtiene |
|---|---|---|---|
| `apagado` | `public` | `public` | Por omisión. También es el resultado si falta cualquiera de las dos llaves. |
| `espejo` | `public` **y** `anfeca_modelo`, en la **misma transacción** | `public` | **Dos llaves:** `ANFECA_PDM_MODO=espejo` en `.env` **y** activación registrada con `anfeca:pdm-modo espejo` para esa misma base |
| `principal`, `exclusivo` | — | — | **No implementados.** Si se configuran, la aplicación opera como `apagado` y lo registra en el log. |

**Activación protegida** (`anfeca:pdm-modo espejo`). Exige:
1. La primera llave (`.env`).
2. Las extensiones y la tabla de control.
3. Datos ya migrados (F2).
4. El plan sin incidencias bloqueantes.
5. **Conciliación con 0 diferencias.**
6. En `bd_laravelanfeca_pruebas`, además, `ANFECA_PDM_AUTORIZAR_ESPEJO=si`.

Mientras verifica, bloquea en modo `SHARE` las tablas de dominio de `public`, para que nadie escriba entre la conciliación y la activación. La activación queda en `anfeca_modelo.integracion_control`, una tabla **técnica** que no forma parte del PDM y no debe agregarse al `.pdm`.

**Costo con `apagado`:** cero. No se consulta la base para decidir el modo, así que la aplicación actual se comporta exactamente igual.

## 2. Cómo funciona la escritura en espejo

1. Cada punto de escritura llama a `EspejoPdm` **dentro de su propia transacción**, después de escribir en `public`.
2. `EspejoPdm` recalcula, con **las mismas reglas de F2** (`PlanMigracionPdm` con alcance acotado), las filas del PDM de la persona y de la solicitud afectadas.
3. `AplicadorPdm` las aplica por clave natural: inserta, actualiza o elimina, solo dentro de ese alcance. Así la conciliación se mantiene en 0 **por construcción**.
4. **Historial:** cuando un documento deja de ser vigente, no se borra; su estado pasa a `Sustituido`. El PDM conserva incluso versiones que `public` no registra, como la sustitución de documentos de origen. La conciliación lo reporta como historial adicional, no como diferencia.
5. **Si algo no puede registrarse en el PDM**, se lanza `ValidationException`. La transacción completa se revierte (también `public`) y el usuario ve un mensaje controlado:

| Caso | Campo | Mensaje |
|---|---|---|
| Entidad no reconocible (p. ej., «Monterrey») | `entidad_federativa` | Escriba el nombre de una entidad federativa de México… |
| Valor más largo que el PDM (D-4) | El campo afectado | Este dato admite como máximo N caracteres. |
| Cualquier otra falla (conflicto, FK, error inesperado) | `pdm` | No fue posible registrar la información en el modelo institucional. No se guardó ningún cambio… El detalle va al log (`report()`). |

## 3. Puntos de escritura adaptados

| # | Proceso | Archivo y método | Sincroniza en el PDM |
|---|---|---|---|
| 1 | Registro | `DatosSolicitanteController::store` | `PERSONA`, `USUARIO` (mismo ID y hash), `DOMICILIO`, `COMUNICACION` |
| 2 | Edición de domicilio y teléfonos | `DatosSolicitanteController::update` | `DOMICILIO`, `COMUNICACION` (altas, cambios y bajas) |
| 3 | Autorización de datos | `DatosSolicitanteController::autorizar` (ahora en transacción) | `PERS_DATOS_AUTORIZADOS`, `PERS_FECHA_AUTORIZACION` |
| 4 | Certificación (inicial o refrendo) | `SeleccionCertificacionController::store` | `SOLICITUD` (área, tipo, trámite, certificación, antecedente validado) + evento `BO` |
| 5 | Refrendo (art. 13/14) | `DatoRefrendoController::store` (ahora en transacción) | `SOLI_ART14`, `SOLO_ART13`, `SOLI_ID_ARTICULO` |
| 6 | Formación académica | `FormacionAcademicaController::store` | `PERSONA_ESTUDIO_PROFESIONAL` (origen y último grado; «Otro» → `REV`), documentos académicos, **historial**, institución de la persona |
| 7 | Autorización de la formación | `FormacionAcademicaController::autorizar` | Evento `FA` |
| 8 | Documentos probatorios | `DocumentoProbatorioController::store` | `SOLICITUD_DOCUMENTO` (vigentes y sustituidos, enlace), `PAGO` |
| 9 | Compromiso de entrega | `AceptacionCompromisoController::guardar` | Evento `CA` |
| 10 | Envío | `RegistroCompletadoController::confirmar` | Evento `EN`; área y tipo de personal confirmados |
| + | Autenticación: restablecer contraseña | `RecuperacionPasswordController::guardarNuevaPassword` (ahora en transacción) | `USUA_CONTRASENIA` (mismo hash, D-1 A) |
| + | Herramienta de demostración | `ReiniciarDemo` | **Se niega** si el modo efectivo no es `apagado`, porque borrar en `public` desalinearía el PDM |

> El plan hablaba de 9 puntos de escritura; separados por pantalla son 10, porque Formación tiene guardado y autorización. Se agregan la contraseña y la protección de la demostración.

**Sin cambios funcionales visibles con `apagado`.**
- Las validaciones, los mensajes, los flujos, las vistas y los componentes Vue se conservan.
- La validación del certificado histórico y el refrendo siguen leyendo de `public`.
- La formación sigue siendo obligatoria en la etapa 1, también en el art. 14.

**Un cambio de vista:** `registro/completado.blade.php` ahora muestra los errores de validación del envío. Antes solo mostraba `session('error')`, así que los errores de `confirmar()` no se veían.

## 4. Archivos

| Archivo | Cambio |
|---|---|
| `app/Services/Pdm/Espejo/EspejoPdm.php`, `AplicadorPdm.php` | Nuevos |
| `app/Console/Commands/AnfecaPdmModo.php` | Nuevo: consulta, activación protegida y apagado |
| `database/migrations_pdm/2026_11_21_000002_crear_control_integracion.php` | Nuevo: tabla técnica de control |
| `app/Services/Pdm/ModoPdm.php` | Modo efectivo con dos llaves; `espejo` implementado |
| `app/Services/Pdm/Migracion/PlanMigracionPdm.php` | Alcance acotado (persona y solicitud) y campo del formulario en las incidencias |
| `app/Services/Pdm/Migracion/ConciliadorPdm.php` | Historial adicional del PDM no cuenta como diferencia; fechas de carga no se comparan |
| `app/Services/Pdm/Migracion/ProteccionMigracion.php` | **Corrección:** migrar, simular y vaciar se niegan con el espejo activo |
| `app/Services/Pdm/Migracion/Incidencia.php` | Campo del formulario |
| 8 controladores (tabla anterior) y `ReiniciarDemo.php` | Un gancho por punto, más transacción donde no la había |
| `resources/views/registro/completado.blade.php` | Muestra errores de validación |
| `database/migrations_pdm/2026_11_21_000001_extender_anfeca_modelo.php` | Ignora la tabla técnica al contar las 36 tablas originales |
| `tests/Pdm/EspejoPdmTest.php` | Nuevo: 11 pruebas |
| `tests/Pdm/EsquemaPdmTest.php`, `ExtensionesPdmTest.php`, `tests/Unit/ModoPdmTest.php` | Ajustados a la tabla de control y al modo espejo |

## 5. Pruebas (PostgreSQL 16.15 aislado)

| Suite | Resultado |
|---|---|
| `php artisan test -c phpunit.pgsql.xml` | **43/43**, 676 aserciones: F1 22 + F2 10 + F3 11 |
| `php artisan test` (SQLite: regresión en modo `apagado`, seguridad, unitarias) | **54/54**. `ExampleTest` ya pasa con la corrección de seguridad. |
| **Mutación:** se retira el gancho del compromiso | `test_flujo_inicial_completo_en_espejo` falla. Las pruebas detectan omisiones reales. |

| Prueba de F3 | Verifica |
|---|---|
| `flujo_inicial_completo_en_espejo` | Por HTTP: registro → edición → autorización → certificación → formación con «Otro» → **sustitución con historial** → autorización de formación → documentos, enlace y pago → compromiso → envío → acuse. Filas correctas en el PDM, eventos `BO`/`FA`/`CA`/`EN` y **conciliación con 0 diferencias tras cada paso**. |
| `refrendo_articulo_14_en_espejo` | Validación del certificado histórico; trámite de refrendo con su antecedente; art. 14.1; la formación sigue siendo obligatoria |
| `dato_no_registrable_revierte_todo_con_error_controlado` | «Monterrey» y un número exterior de 10 caracteres: error en el campo y **nada** escrito en `public` ni en el PDM |
| `falla_inesperada_del_pdm_revierte_tambien_public` | Conflicto en el PDM: error controlado; no se crea el usuario en `public` |
| `restablecer_contrasena_sincroniza_el_usuario_tecnico` | Mismo hash en `USUARIO` tras restablecer |
| `modo_apagado_no_escribe_en_el_pdm` | Con `apagado` la aplicación funciona y el PDM no cambia |
| `la_activacion_se_niega_con_bloqueos_pendientes` | Sin decisiones (D-11) no se activa |
| `la_activacion_exige_datos_migrados_y_conciliacion_sin_diferencias` | Sin F2, o con desfase, no se activa |
| `activacion_requiere_la_primera_llave_y_autorizacion_en_la_base_en_uso` | Dos llaves y variable de autorización |
| `reiniciar_demo_y_vaciar_se_niegan_en_espejo` | Protección de las herramientas que borran |
| `el_error_del_espejo_se_muestra_en_finalizacion` | La vista de envío muestra el error controlado |

## 6. Procedimiento de activación (solo con tu autorización expresa y F2 aprobada)

En el servidor, en una ventana sin usuarios capturando:

1. **Respaldo** `pg_dump -Fc`, verificado con `pg_restore -l` y restaurado en una base aparte.
2. **Código:** revisión y PR de `integracion/pdm-f3` → `desarrollo/anfeca-pruebas`, luego `git pull --ff-only` en el servidor, `composer install` y `npm run build` (por `NuevaPassword.vue` de seguridad). Con `ANFECA_PDM_MODO` ausente o en `apagado` **no cambia nada**.
3. **Verificar la conexión:**

   ```bash
   php artisan anfeca:pdm-conexion
   ```

4. **Extensiones, tabla de control y catálogos:**

   ```bash
   ANFECA_PDM_AUTORIZAR_EXTENSIONES=si php artisan migrate --path=database/migrations_pdm
   ```

   ```bash
   php artisan db:seed --class="Database\Seeders\Pdm\CatalogosPdmSeeder" --force
   ```

5. **Decisiones** D-11, D-13 y D-14 en `config/anfeca_pdm_decisiones.php`. Luego:

   ```bash
   php artisan config:clear && php artisan anfeca:pdm-migrar
   ```

   Debe decir «Sin bloqueos».

6. **Migración F2:**

   ```bash
   ANFECA_PDM_AUTORIZAR_MIGRACION=si php artisan anfeca:pdm-migrar --ejecutar
   ```

   ```bash
   php artisan anfeca:pdm-conciliar
   ```

   La conciliación debe dar 0 diferencias.

7. **Primera llave:** `ANFECA_PDM_MODO=espejo` en `.env` y luego:

   ```bash
   php artisan config:clear
   ```

8. **Segunda llave:**

   ```bash
   ANFECA_PDM_AUTORIZAR_ESPEJO=si php artisan anfeca:pdm-modo espejo
   ```

9. **Comprobar:**

   ```bash
   php artisan anfeca:pdm-modo
   ```

   Debe mostrar «Modo EFECTIVO: espejo». Después, con un usuario ficticio, hacer registro, edición y certificación, y ejecutar `anfeca:pdm-conciliar`.

10. **Operación:** ejecutar `php artisan anfeca:pdm-conciliar` a diario (por ejemplo, con cron) y revisar `storage/logs/laravel.log` buscando errores del espejo.

Si alguien escribe entre los pasos 6 y 8, la activación se negará porque detectará diferencias. En ese caso:
1. `ANFECA_PDM_AUTORIZAR_VACIADO=si php artisan anfeca:pdm-vaciar`
2. Repetir el paso 6.

## 7. Reversión (probada en `modo_apagado_no_escribe_en_el_pdm`)

| Situación | Acción | Efecto |
|---|---|---|
| Cualquier problema con el espejo | `php artisan anfeca:pdm-modo apagado`; después, `ANFECA_PDM_MODO=apagado` en `.env` y `config:clear` | **Inmediato y sin despliegue.** ANFECA vuelve a escribir solo en `public`, que nunca dejó de ser la fuente. |
| Volver a activar después | `anfeca:pdm-vaciar`, luego `anfeca:pdm-migrar --ejecutar`, luego `anfeca:pdm-modo espejo` | El PDM dejó de recibir escrituras mientras estuvo apagado |
| Retirar el código | `git revert` del merge | Los ganchos no actúan con `apagado` |
| Retirar la tabla de control | `php artisan migrate:rollback --path=database/migrations_pdm --step=1` | Solo si el modo registrado es `apagado` |

## 8. Riesgos

| Riesgo | Mitigación |
|---|---|
| Con el espejo activo, una falla del PDM **bloquea la operación del usuario** (diseño solicitado: sin escrituras parciales) | Mensaje controlado, log con detalle, apagado inmediato |
| La entidad federativa es un campo de texto libre: en espejo se rechazan valores no reconocibles | Recomendado antes de activar: convertirlo en lista de entidades INEGI en la interfaz (cambio de Vue, pendiente de tu aprobación) |
| Los límites del PDM (D-4) endurecen la captura en espejo | Decidir D-4 antes de activar |
| PostgreSQL 18.6 no probado aquí | Ejecutar `phpunit.pgsql.xml` en AlmaLinux |
| Concurrencia: dos operaciones sobre la misma solicitud | Los controladores ya bloquean filas (`lockForUpdate`); el espejo corre dentro de esas transacciones |
| Datos que `public` no registra (versiones de documentos de origen) solo existen en el PDM | Es la mejora buscada; la conciliación lo reporta como historial adicional |

## 9. Condiciones para pasar a F4 (lectura desde el PDM)

1. F2 aprobada sobre la copia y en la base de trabajo, con conciliación en 0.
2. Espejo activo en la base de trabajo durante un periodo de uso real, con conciliación diaria en 0 y sin errores del espejo en el log.
3. D-4, D-11, D-13 y D-14 decididas, y la entidad federativa como lista en la interfaz.
4. Suite PG aprobada en 18.6.
5. Para F4, los lectores del PDM deben entregar la misma estructura que hoy usan Blade y Vue (middleware `datos.autorizados`, antecedentes, requisitos de envío, acuse, panel). Las pruebas de regresión deben correr en modo `principal`.
