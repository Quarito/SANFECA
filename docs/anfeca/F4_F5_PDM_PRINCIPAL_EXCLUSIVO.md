# F4 y F5 — PowerDesigner como fuente principal (`principal`) y única (`exclusivo`)

> Rama `integracion/pdm-f4-f5`, a partir de `desarrollo/anfeca-pruebas` @ `4354b0b` (F1 + F2 + F3 + `fix/seguridad-anfeca`).
> **Estado:** implementada y probada **solo en PostgreSQL 16 aislado** (contenedor de desarrollo).
> - **No** se ha ejecutado en el servidor, en `bd_laravelanfeca_pruebas` ni en la copia.
> - **No** se ha activado ningún modo en ninguna base de trabajo.
> - El modo por omisión sigue siendo `apagado`.

## 1. Modos

| Modo efectivo | Laravel lee de | Laravel escribe en | `public` |
|---|---|---|---|
| `apagado` | `public` | `public` | Fuente (comportamiento actual). |
| `espejo` (F3) | `public` | `public` y PDM (PHP, `EspejoPdm`) | Fuente. |
| `principal` (F4) | **PDM**, mediante las vistas `anfeca_compat` | **PDM**, mediante disparadores; replican en `public` | Réplica al día, para revertir de inmediato. |
| `exclusivo` (F5) | **PDM** | **Solo PDM** | **Congelado**: la guardia rechaza toda escritura directa y la huella queda registrada. |

**Dos llaves (sin cambios).** Se necesitan las dos:
- `ANFECA_PDM_MODO=<modo>` en `.env`.
- La fila de `anfeca_modelo.integracion_control` con **el mismo modo y la misma base**, escrita solo por `anfeca:pdm-modo <modo>`.

En `principal` y `exclusivo` también debe estar instalada la capa `anfeca_compat`. Si falta cualquiera de estas condiciones, la aplicación opera en `apagado`. Con `ANFECA_PDM_MODO=apagado` no consulta la base.

## 2. Diseño: capa de compatibilidad `anfeca_compat`

La aplicación (modelos Eloquent, validaciones `unique`/`exists`, Blade, Vue, PDF del acuse) **no cambia**. En `principal` y `exclusivo`, el middleware global `AplicarModoPdm` ejecuta `set search_path to anfeca_compat, public` en la conexión de la petición. Sin `DB::purge`, la lectura posterior a una escritura ve la misma transacción.

| Pieza | Archivo | Función |
|---|---|---|
| Vistas | `database/sql/anfeca_modelo/anfeca_compat_vistas.sql` | Nueve vistas con el **nombre y las columnas exactas** de las tablas de `public`: `datos_solicitantes`, `selecciones_certificacion`, `solicitudes_certificacion`, `datos_refrendo`, `formaciones_academicas_solicitud`, `documentos_probatorios_solicitud`, `historial_documentos_academicos`, `certificados_historicos` e `instituciones_afiliadas`. Cada una tiene una sola tabla base, así que `lockForUpdate()` sigue funcionando. Las fechas locales del PDM se devuelven en la zona de la aplicación. |
| Disparadores | `database/sql/anfeca_modelo/anfeca_compat_escritura.sql` | `INSTEAD OF` con las **mismas reglas** que `PlanMigracionPdm` y `AplicadorPdm`: entidad INEGI, género, longitudes, eventos BO/FA/CA/EN, «Otro» → REV, documento vigente/Sustituido (el historial **nunca se borra**), pago, art. 13/14, N-6. En `principal` replican en `public` con el mismo ID. No se permite `DELETE`. |
| Guardia de `public` | mismo archivo | En `principal` y `exclusivo`, las 9 tablas de dominio de `public` solo se escriben desde los disparadores o desde la exportación de reversión. Cualquier otra escritura, por ejemplo una consola o un proceso sin `search_path`, se rechaza. |
| Usuario técnico | mismo archivo | `public.users` sigue siendo la tabla de autenticación (D-1 A). Un disparador sincroniza la contraseña, el correo y el rol con `USUARIO` en `principal` y `exclusivo`, incluida la recuperación de contraseña. |
| Instalación | `database/migrations_pdm/2026_11_21_000004_instalar_capa_compatibilidad.php` | Ruta separada, solo PostgreSQL. En la base en uso exige `ANFECA_PDM_AUTORIZAR_EXTENSIONES=si`. Siembra las correspondencias desde `config/anfeca_pdm.php`. `down()` se niega en `principal` y `exclusivo` y no borra datos. |
| Errores | `ErrorValidacionPdm` + `bootstrap/app.php` | Un dato no registrable, por ejemplo «Monterrey» como entidad o un número exterior de más de 6 caracteres, se muestra como error del campo. La transacción se revierte completa. |

### Extensiones aditivas del PDM (F4)

Archivo: `anfeca_modelo_extensiones_f4.sql`, aplicado por la migración `000003`.

Tablas nuevas, para que el PDM guarde lo que hoy solo existe en `public`:
- `SELECCION_CERTIFICACION`
- `SOLICITUD_REFRENDO`
- `FORMACION_SOLICITUD`
- `DOCUMENTOS_SOLICITUD`

Además, `PERSONA` y `SOLICITUD` ganan fechas de creación y actualización.

F2 y F3 (plan, migrador, conciliador, vaciador, aplicador) ya las incluyen. En total quedan 43 tablas y 47 FK, y se regeneraron los modelos `App\Models\Pdm`.

### Normalizaciones documentadas

Lo que la aplicación ve en `principal` puede diferir de `public` **solo** en estos puntos:

| Punto | En `public` | En la vista (PDM) |
|---|---|---|
| Entidad federativa | «CDMX», «tlaxcala» | Nombre oficial INEGI: «Ciudad de México», «Tlaxcala» |
| Título fuera del catálogo | Texto en `titulo_*` | «Otro» + texto en `titulo_*_otro` (estudio en REV; la captura original se conserva) |
| Solicitud sin tipo de personal ni área | nulo | Valor de la selección del mismo usuario (N-6) |
| Historial documental | Solo versiones académicas registradas por Laravel | Puede tener **más** filas (versiones que solo conserva el PDM); los IDs son los del PDM |
| `instituciones_afiliadas` | Fechas, número de afiliación | Sin fechas; el número sale de la decisión D-11 |
| `certificados_historicos.user_id` | ID | Nulo si el usuario no tiene `USUARIO` en el PDM |

`anfeca:pdm-equivalencia` (solo lectura) compara las vistas con `public` fila por fila y columna por columna, y acepta únicamente estas diferencias.

## 3. Activación y reversión

| Paso | Comando | Exige |
|---|---|---|
| Instalar la capa | `php artisan migrate --path=database/migrations_pdm` | Extensiones F1/F4. En la base en uso: `ANFECA_PDM_AUTORIZAR_EXTENSIONES=si` y respaldo verificado. |
| Verificar | `anfeca:pdm-conciliar` y `anfeca:pdm-equivalencia` | Solo lectura; las dos deben dar 0. |
| Activar F4 | `ANFECA_PDM_MODO=principal` + `config:clear` + `anfeca:pdm-modo principal` | Capa instalada, datos migrados (F2), plan sin bloqueos, **conciliación = 0 y equivalencia = 0** verificadas bajo `LOCK … IN SHARE MODE`. En la base en uso: `ANFECA_PDM_AUTORIZAR_PRINCIPAL=si`. |
| Revertir F4 | `anfeca:pdm-modo apagado` (o `espejo`) + `.env` | Inmediato: `public` es réplica al día. |
| Activar F5 | `ANFECA_PDM_MODO=exclusivo` + `config:clear` + `anfeca:pdm-modo exclusivo` | **Venir de `principal`** y las mismas verificaciones. En la base en uso: `ANFECA_PDM_AUTORIZAR_EXCLUSIVO=si`. Registra la huella de `public` en `ic_huella_public`. |
| Revertir F5 | `anfeca:pdm-exportar-public` y después `anfeca:pdm-modo principal` (o `apagado`) | La exportación copia a `public` lo capturado solo en el PDM, en una transacción. Inserta o actualiza por ID, **nunca elimina**, y solo agrega historial. Si al final la conciliación o la equivalencia no dan 0, revierte. Salir de `exclusivo` sin exportar **se niega**. En la base en uso: `ANFECA_PDM_AUTORIZAR_EXPORTACION=si`. |

**Antes de cualquier activación en el servidor:** `pg_dump -Fc`, prueba de restauración en una base separada, y autorización explícita.

## 4. Pruebas (PostgreSQL 16 aislado, datos ficticios)

| Suite | Resultado |
|---|---|
| `phpunit.pgsql.xml` (completa) | **62/62**, 1095 aserciones (antes 45) |
| `php artisan test` (SQLite) | **54/54** |

Archivos nuevos: `tests/Pdm/FlujosAnfeca.php`, `PrincipalPdmTest.php` (9) y `ExclusivoPdmTest.php` (7).

**`principal`.** Flujos HTTP reales; **después de cada paso**, conciliación = 0 y equivalencia = 0:
- registro → edición → autorización → certificación → formación con «Otro» y 4 documentos → **sustitución** (versión anterior = Sustituido) → formación autorizada → documentos, enlace y pago → compromiso → envío → acuse (PDF) y panel;
- refrendo con art. 14 desde un antecedente histórico;
- recuperación de contraseña;
- dato no registrable, rechazado en el campo sin escribir nada;
- guardia de `public`;
- vistas de catálogo de solo lectura;
- activación protegida (llave, autorización, desfase);
- apagado inmediato.

**`exclusivo`.** Los mismos flujos con la **huella de `public` idéntica** tras cada paso, además de:
- salir sin exportar se niega;
- exportación con conciliación y equivalencia = 0, idempotente;
- vuelta a `principal` y luego a `apagado`, con el acuse funcionando;
- exportación solo en `exclusivo` y con autorización;
- vaciar y reiniciar-demo se niegan.

Las suites de F1–F3 (incluido `EspejoPdmTest`) siguen pasando sin cambios funcionales.

## 5. Riesgos y pendientes

1. **Probado en PG 16, no en PG 18.6.**
   - La instalación local de PG 18.6 fue denegada en este entorno.
   - Ejecutar `phpunit.pgsql.xml` en AlmaLinux contra `bd_anfeca_pruebas_pg` antes de cualquier activación.
2. **Nada aplicado en el servidor.**
   - Las migraciones `000003` y `000004` y la activación requieren respaldo verificado y autorización explícita.
   - En la base principal siguen pendientes las migraciones F1 y de control.
   - F2 **no** se ha ejecutado en la base principal.
3. **Privilegios.** El usuario de base de datos de Laravel necesita `USAGE` en `anfeca_compat` y `anfeca_modelo`, más `SELECT/INSERT/UPDATE` sobre las vistas y tablas. Si es propietario del esquema, no hay que hacer nada.
4. **Procesos fuera de HTTP** (artisan, colas, cron) no pasan por el middleware.
   - En `principal` y `exclusivo` sus escrituras directas a `public` son **rechazadas por la guardia**: falla cerrado, nunca hay desincronización silenciosa.
   - Un comando nuevo que deba escribir dominio debe fijar `search_path` igual que el middleware.
5. **Cambiar solo `.env` desde `exclusivo` deja la app leyendo un `public` congelado.**
   - Las escrituras fallan por la guardia, pero las lecturas serían antiguas.
   - Para salir de `exclusivo` use **siempre** `anfeca:pdm-exportar-public` y `anfeca:pdm-modo`.
6. **Conexiones persistentes** (`PDO::ATTR_PERSISTENT`) no deben usarse: el `search_path` es por conexión. Hoy no están activadas.
7. **Rendimiento.** Las vistas usan subconsultas escalares por fila; con el volumen actual es despreciable. Medir con `EXPLAIN ANALYZE` en la copia antes de activar.
8. **D-11, D-13 y D-14** siguen resolviéndose solo en `config/anfeca_pdm_decisiones.php` con fuente documentada. Sin esas decisiones, la activación se niega por incidencias bloqueantes.
9. **D-1 B** (login sobre `USUARIO`) **no** se implementó: `public.users` sigue siendo técnica, sincronizada por disparador.
