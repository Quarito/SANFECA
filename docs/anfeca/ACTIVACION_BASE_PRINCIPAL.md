# Activación controlada en `bd_laravelanfeca_pruebas`

> **Requisitos previos:**
> - `preparar_integracion.sh` aprobado con los datos reales (en `preparacion.log` aparece «PREPARACIÓN APROBADA»).
> - **Autorización expresa** del responsable.
>
> **Script:** `scripts/anfeca/activar_principal.sh <fase>`, ejecutado desde `/var/www/ANFECA_PRUEBAS`.
> - Las fases marcadas [E] escriben en la base principal y exigen `ANFECA_AUTORIZO=<fase>`.
> - Se detienen si los datos de `public` cambian.
> - No borran registros ni bases.

## Modo inicial recomendado: `espejo` (F3)

- `public` sigue siendo la fuente y el PDM se escribe en la misma transacción.
- La reversión es inmediata (`apagar`), sin restaurar respaldos.
- `principal` (F4) solo después de varios días en espejo con `comprobar` diario en 0, y con una nueva autorización.
- `exclusivo` (F5) queda como decisión posterior, porque congela `public`.

## Orden

| # | Fase | Qué hace | Si falla |
|---|---|---|---|
| 1 | `verificar` | Solo lectura. Comprueba: <ul><li>el código contiene `e831add` y no tiene cambios locales;</li><li>el modo está en `apagado`;</li><li>existe un ensayo aprobado;</li><li>la huella y el estado de las migraciones;</li><li>el login responde 200.</li></ul> | Nada cambió. Se corrige la causa. |
| 2 | `respaldo` | `pg_dump -Fc` (PG 18), índice y SHA-256. Es el **punto de recuperación**; las fases [E] exigen que tenga menos de 24 h. | Nada cambió. |
| 3 | `esquema` [E] | Migraciones PDM `000001` → `000002` → `000003` → `000004` y catálogos. | Modo `apagado`: la aplicación no usa nada nuevo. Ver «Reversión». |
| 4 | `datos` [E] | Plan F2 y migración, en una transacción que solo se confirma con conciliación en 0 y `public` sin cambios. Después corre conciliación y equivalencia. | La migración se revierte sola. La aplicación sigue en `apagado`. |
| 5 | `espejo` [E] | Requiere `ANFECA_PDM_MODO=espejo` en `.env`. Sin la activación registrada, la aplicación sigue en `apagado`. Activa bajo `LOCK SHARE` con conciliación en 0. | No se activa y la aplicación sigue en `apagado`. |
| 6 | `comprobar` | Conciliación, equivalencia, recorrido funcional de todos los usuarios (en transacción revertida) y login 200. | `apagar`. |

**Prueba funcional final.** Un usuario ficticio completa el trámite en el sitio (registro → envío → acuse). Después se ejecuta `comprobar`. Esto agrega registros de prueba en la base principal y requiere tu decisión.

## Reversión

| Situación | Acción |
|---|---|
| Cualquier problema en espejo | `activar_principal.sh apagar`, luego `ANFECA_PDM_MODO=apagado` en `.env` y `php artisan config:clear`. Es inmediato y `public` siempre fue la fuente. |
| Falla en `esquema` o `datos` | El modo sigue en `apagado`: la aplicación no se ve afectada. Los esquemas `anfeca_modelo` y `anfeca_compat` quedan inertes y no se borran. |
| Restauración total | Un **administrador** restaura el respaldo de la fase 2 (`pg_restore`). Es una decisión manual y no se automatiza. |

No se usan `pdm-vaciar`, `migrate:fresh` ni `db:wipe` sobre la base principal.

## Verificaciones antes de cada fase [E]

1. Hay autorización expresa para esa fase.
2. Existe un respaldo de menos de 24 h y se conoce su ruta.
3. Es un horario de bajo uso: actividad concurrente en `public` detiene la fase, que entonces se repite.
4. El login en `http://192.168.7.130:8084/login` responde 200.
5. Las decisiones D-11, D-13 y D-14 están registradas si el plan las pide (en el ensayo: «Incidencias bloqueantes: 0»).
