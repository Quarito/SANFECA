# Pruebas de regresión con usuarios ficticios — ANFECA_PRUEBAS

> Rama `integracion/anfeca-bd-final`, basada en `desarrollo/anfeca-pruebas` (commit `6c873c2`).
> Hay dos niveles:
> - **A. Automatizadas:** SQLite en memoria, sin tocar ninguna BD real.
> - **B. Manuales en navegador:** en `/var/www/ANFECA_PRUEBAS`, solo con cuentas ficticias.
> Ninguna prueba usa datos reales ni contraseñas reales.

## A. Pruebas automatizadas

```bash
php artisan test --filter='RegresionAnfecaTest|AnfecaModeloSqlTest|MigracionesAnfecaTest|AislamientoAnfecaTest'
```

**Protecciones de `RegresionAnfecaTest`:**
- Se niega a correr si la conexión no es SQLite en memoria.
- Usa `Storage::fake('local')`.
- Crea usuarios `ficticioN@example.test` con CURP `FICT…` dentro de la prueba.

**Resultado en el entorno de integración (20/11/2026):** 31 de 32 pruebas pasan.
- `RegresionAnfecaTest`: 18/18.
- `AnfecaModeloSqlTest`: 10/10.
- `MigracionesAnfecaTest` y `AislamientoAnfecaTest`: 2/2.
- `Unit/ExampleTest`: 1/1.
- La única que falla es `Feature/ExampleTest`, una prueba de plantilla que ya fallaba antes (parche P9).

| Prueba | Funcionalidad validada que protege |
|---|---|
| `test_registro_nuevo_crea_usuario_y_datos` | Registro: crea `users` y `datos_solicitantes`, inicia sesión y lleva a Certificación |
| `test_registro_exige_al_menos_un_telefono` | Regla `required_without` entre teléfonos |
| `test_antes_de_autorizar_solo_se_actualiza_domicilio_y_telefonos` | Regla acordada; un PUT manipulado con otra CURP, otro nombre u otro correo no cambia la identidad |
| `test_despues_de_autorizar_no_se_permite_modificar` | Bloqueo del servidor después de autorizar |
| `test_autorizar_exige_confirmacion_y_registra_fecha` | `confirmacion_datos` obligatoria y `fecha_autorizacion` |
| `test_etapas_requieren_datos_autorizados` | Middleware `datos.autorizados` en Certificación, Formación, Documentos, Compromiso y Finalización |
| `test_etapas_requieren_sesion` | Middleware `auth` |
| `test_finalizacion_sin_compromiso_redirige_a_aceptacion` | Finalización no se abre por URL directa sin aceptación |
| `test_compromiso_sin_casilla_es_rechazado_y_con_casilla_se_registra` | POST sin casilla → error; con casilla → `compromiso_aceptado` + fecha; **no** envía la solicitud |
| `test_pantalla_de_aceptacion_es_componente_vue_con_texto_definitivo` | Montaje Vue, texto literal definitivo, «Anterior» → Documentos, «Aceptar y continuar» |
| `test_envio_rechazado_si_faltan_requisitos` | No se envía sin formación o documentos |
| `test_envio_rechazado_sin_compromiso_aunque_este_completo` | Doble verificación del compromiso |
| `test_envio_completo_cambia_solo_la_solicitud_correcta_y_no_se_duplica` | `borrador → enviado`, `fecha_envio`, área conservada, sin reenvío; la solicitud de otro usuario no cambia |
| `test_acuse_no_se_entrega_para_borrador_ni_a_otro_usuario` | Acuse solo para solicitudes `enviado` propias |
| `test_documentos_exigen_formacion_academica_autorizada_etapa_1` | Etapa 1: Documentos exige formación autorizada (`formacion_completada_at`) |
| `test_descarga_de_documento_propio_y_campo_no_permitido` | Descarga propia correcta; un campo arbitrario da 404 |
| `test_descarga_de_documento_no_expone_archivos_de_otro_usuario` | Aislamiento de archivos entre usuarios |
| `test_documentos_reemplazados_no_se_eliminan_del_almacenamiento` | Al reemplazar un probatorio, el archivo anterior se conserva en Storage |

**No automatizado todavía:**
- El refrendo con certificado histórico (requiere antecedentes ficticios en `certificados_historicos`).
- Las reglas de equivalencias de títulos.
- Los campos «Otro».
- El PDF del acuse (requiere compilar los assets Vite).

Esos casos van en la sección B.

> **Observación:** `MigracionesAnfecaTest` sobrescribe `refreshDatabase()` y no llama a `restoreInMemoryDatabase()`. Con una sola prueba funciona; si se agrega una segunda prueba a esa clase, perderá las tablas. `RegresionAnfecaTest` usa el patrón correcto, `use RefreshDatabase { refreshDatabase as protected refreshDatabaseLaravel; }`. No se modificó la prueba existente.

## B. Protocolo manual con usuarios ficticios (navegador)

**Preparación:**
1. Respaldo `pg_dump -Fc` verificado (`PASOS_SERVIDOR.md`, paso 1).
2. Cuentas ficticias nuevas por caso, con correo `@example.test`, CURP de prueba con formato válido y archivos PDF ficticios de menos de 5 MB.
3. **No reutilizar** el usuario 29 ni la solicitud 21 como plantilla.
4. Para refrendo, un antecedente ficticio controlado en `certificados_historicos`.

**Registro de evidencia por paso:**
- Fecha.
- Usuario ficticio (id).
- Resultado: **Pasa / Falla / No probado**.
- Captura de pantalla.
- Consulta SQL de lectura, sin datos de terceros.

### B.1 Casos

| # | Caso | Pasos clave | Resultado esperado |
|---|---|---|---|
| 1 | Inicial ANFECA sin antecedente | Registro → autorizar → Certificación inicial → Formación → Documentos → Aceptación → Finalización → Confirmar envío | Estado `enviado`, `fecha_envio` registrada, acuse descargable |
| 2 | Refrendo compatible | Autorizar → Refrendo → capturar el número de certificado → validar | La pantalla empieza **en blanco**; al salir y volver dentro del mismo trámite, se conserva el certificado validado |
| 3 | Refrendo con tipo de personal incompatible | Elegir un tipo distinto al del antecedente | **Mensaje visible** y bloqueo consistente (pendiente de la prueba 3.5.3) |
| 4 | Refrendo con área incompatible | Elegir otra área | Mensaje visible y bloqueo |
| 5 | Certificado inexistente o erróneo | Capturar un número inválido | Error visible; no avanza |
| 6 | Refrendo art. 13 y art. 14 | Completar ambos | Etapa 1: **ambos** exigen formación académica y sus 4 documentos; el art. 14 exige además 2 probatorios |
| 7 | Documentos | Intentar avanzar con archivos vacíos, `.exe` o de más de 5 MB | Rechazo con mensaje en español |
| 8 | Reemplazo del título del último grado | Subir un título, reemplazarlo | Se crea una fila en `historial_documentos_academicos` y el archivo previo sigue en Storage |
| 9 | Títulos «Otro» | Elegir «Otro» en el origen y en el último grado | El campo adicional solo aparece al elegir «Otro»; el texto aparece en el acuse |
| 10 | Aceptación | La casilla empieza vacía y el botón deshabilitado; probar «Anterior» y un POST manipulado sin casilla | Error del servidor; tras aceptar se guarda la fecha |
| 11 | Acceso directo | Abrir `/registro/completado` por URL sin aceptación | Redirige a Aceptación |
| 12 | Doble envío | Pulsar «Confirmar envío» dos veces o reenviar el POST | Un solo cambio de estado; mensaje «ya fue enviada» |
| 13 | Solicitud de otro usuario | Usuario B pide `/registro/acuse/{id de A}` | 404 |
| 14 | Recuperación de contraseña | CURP + correo ficticios; enlace del log (`MAIL_MAILER=log`) | Enlace de un solo uso, caducidad de 60 min, mensajes en español. Hoy acepta 8 caracteres (P6) |
| 15 | Múltiples solicitudes | Mismo usuario: inicial enviada y después un nuevo trámite | No mezcla documentos entre solicitudes |

### B.2 Consultas de verificación (solo lectura)

Sustituye `:uid` por el id del usuario ficticio.

```sql
SELECT id, tipo_tramite, certificacion, estado, fecha_envio, compromiso_aceptado, compromiso_aceptado_en
FROM solicitudes_certificacion WHERE user_id = :uid ORDER BY id;
```

```sql
SELECT s.id, f.formacion_completada_at, f.titulo_origen, f.titulo_origen_otro, f.titulo_ultimo_grado_otro
FROM solicitudes_certificacion s LEFT JOIN formaciones_academicas_solicitud f ON f.solicitud_id = s.id
WHERE s.user_id = :uid;
```

```sql
SELECT h.* FROM historial_documentos_academicos h
JOIN solicitudes_certificacion s ON s.id = h.solicitud_id WHERE s.user_id = :uid;
```

Para comprobar que no se alteraron solicitudes de terceros, compara antes y después de la prueba:

```sql
SELECT count(*), max(updated_at) FROM solicitudes_certificacion WHERE user_id <> :uid;
```

### B.3 Después de las pruebas

- No borrar usuarios ni solicitudes.
- Para reiniciar las cuentas de demostración, usar únicamente `php artisan anfeca:reiniciar-demo`, con su confirmación y tras un respaldo.
