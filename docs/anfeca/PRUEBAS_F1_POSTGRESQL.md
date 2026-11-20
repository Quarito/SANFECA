# F1 — Cómo probar en AlmaLinux con PostgreSQL aislado

> Rama `integracion/pdm-f1`. Esta fase **no modifica `bd_laravelanfeca_pruebas`**: todo ocurre en una base nueva y desechable, `bd_anfeca_pruebas_pg`, y en un worktree separado del directorio que sirve Apache.

## Qué contiene F1

| Elemento | Archivo |
|---|---|
| Extensiones aditivas del PDM (D-2): 3 tablas nuevas, 16 columnas en tablas existentes, 7 FK, 4 restricciones CHECK y 28 índices (únicos o de apoyo) | `database/sql/anfeca_modelo/anfeca_modelo_extensiones.sql` (úsalo también para actualizar PowerDesigner) |
| Migración protegida que aplica las extensiones (ruta separada: un `migrate` normal **no** la ejecuta) | `database/migrations_pdm/2026_11_21_000001_extender_anfeca_modelo.php` |
| 39 modelos Eloquent del PDM y sus relaciones (las 42 FK) | `app/Models/Pdm/*` |
| Interruptor de modo (en F1 solo `apagado`) | `app/Services/Pdm/ModoPdm.php`, `config/anfeca_pdm.php` |
| Reglas de normalización N-1 a N-6 | `app/Services/Pdm/NormalizadorCatalogos.php` |
| Catálogos (idempotente, ejecución explícita) | `database/seeders/Pdm/CatalogosPdmSeeder.php` |
| Pruebas PostgreSQL (21) | `tests/Pdm/*`, `phpunit.pgsql.xml` |
| Pruebas unitarias (8) | `tests/Unit/NormalizadorCatalogosTest.php`, `tests/Unit/ModoPdmTest.php` |

**El código existente no cambia:** ningún controlador, vista, ruta ni migración de `database/migrations` se modificó.

## Resultados en el entorno de integración (PostgreSQL 16.15)

| Suite | Resultado |
|---|---|
| `php artisan test -c phpunit.pgsql.xml` | **21/21**, 436 aserciones |
| `php artisan test` (SQLite) | 39/40. Solo falla `ExampleTest`, ya corregida en `fix/seguridad-anfeca`. |

El servidor usa PostgreSQL 18.6, así que **ejecuta la suite PG en AlmaLinux** antes de aprobar F1.

---

## Protecciones de aislamiento (revisión del 20/11/2026)

La suite PostgreSQL se **detiene antes de cualquier operación** si se cumple cualquiera de estas condiciones:

| Condición | Motivo |
|---|---|
| Configuración de Laravel en caché (`bootstrap/cache/config.php`) | Laravel ignoraría `phpunit*.xml` y usaría el `.env` real. Esta regla aplica también a la suite SQLite (`tests/TestCase.php`). |
| `DB_URL` definida | Tendría prioridad sobre el host y la base. Además, ambos `phpunit*.xml` la fuerzan a vacío. |
| La conexión no es `pgsql` | `phpunit.pgsql.xml` la fuerza a `pgsql` y `phpunit.xml` a SQLite en memoria (`force="true"`). |
| El nombre de la base no termina en `_pruebas_pg` | |
| La base es `bd_laravelanfeca_pruebas` | |
| La base que **reporta PostgreSQL** (`current_database()`) difiere de la configurada | |
| Host o dirección del servidor (`inet_server_addr()`) distintos de la máquina local | |

Las reglas están en `tests/Pdm/GuardiaBaseAislada.php` y tienen sus propias pruebas en `tests/Unit/GuardiaBaseAisladaTest.php`. También se comprobaron en ejecución real: apuntar la suite a la base de trabajo la detiene sin tocarla.

Antes de probar, en el worktree:

```bash
php artisan config:clear
```

## Paso 1 — Base aislada (una sola vez)

Requiere un usuario con privilegio para crear bases; normalmente es `postgres`.

```bash
sudo -u postgres createdb -O rol_anfecalaravel bd_anfeca_pruebas_pg
```

```bash
sudo -u postgres psql -Atc "select datname, pg_get_userbyid(datdba) from pg_database where datname = 'bd_anfeca_pruebas_pg'"
```

**Esperado:** `bd_anfeca_pruebas_pg|rol_anfecalaravel`.

> Las pruebas **borran y recrean** el contenido de esa base en cada ejecución. Se niegan a correr si el nombre no termina en `_pruebas_pg`, si es `bd_laravelanfeca_pruebas` o si el host no es local.

## Paso 2 — Worktree con la rama (no toca `/var/www/ANFECA_PRUEBAS`)

```bash
cd /var/www/ANFECA_PRUEBAS && git fetch origin integracion/pdm-f1
```

```bash
git worktree add "$HOME/anfeca_f1" origin/integracion/pdm-f1 && cd "$HOME/anfeca_f1" && composer install --no-interaction
```

```bash
cp .env.example .env && php artisan key:generate
```

El `.env` del worktree **no** tiene la contraseña de la base: se proporciona solo en la sesión (paso 3).

## Paso 3 — Pruebas PostgreSQL

```bash
cd "$HOME/anfeca_f1" && export DB_HOST=127.0.0.1 DB_PORT=5432 DB_USERNAME=rol_anfecalaravel && read -s -p "Contraseña de rol_anfecalaravel: " DB_PASSWORD && export DB_PASSWORD && echo
```

```bash
php artisan test -c phpunit.pgsql.xml
```

**Esperado:** `Tests: 21 passed`.

| Prueba | Qué comprueba |
|---|---|
| `EsquemaPdmTest` | 39 tablas y 42 FK. Cada FK del SQL existe en PostgreSQL. Los 39 modelos coinciden columna por columna. `public` tiene 25 tablas. Las tablas de etapa 2 están vacías. |
| `ModelosPdmTest` | Escritura y lectura real de un expediente ficticio completo por relaciones: persona, usuario, domicilio, contacto, refrendo art. 14, eventos BO/FA/CA/EN, formación con «Otro», documento con historial (sustituido y vigente), pago, cadena de etapa 2. Actualización. Reversión de todo al terminar. |
| `IntegridadReferencialTest` | Cada una de las 42 FK rechaza un huérfano (`23503`, con su nombre). Todas son `RESTRICT`. No se puede borrar una persona con expediente. |
| `ExtensionesPdmTest` | Columnas y tipos de la extensión. Restricciones nuevas: tipo de trámite, certificación, CURP única, una formación de origen por solicitud, folio único. La reversión se niega con datos. Ciclo `down`/`up` sin datos. Protección de la base en uso. |
| `CatalogosPdmTest` | Conteos. Clave INEGI como ID. Sembrado idempotente. Coincidencia con la interfaz y con `anfeca_equivalencias`. Sin «Otro» en el catálogo. |

Al terminar:

```bash
unset DB_PASSWORD
```

## Paso 4 — Pruebas normales (SQLite en memoria)

```bash
cd "$HOME/anfeca_f1" && env -u DB_HOST -u DB_PORT -u DB_USERNAME php artisan test
```

**Esperado:** 39 pasan y `ExampleTest` falla hasta fusionar `fix/seguridad-anfeca`.

## Paso 5 — Confirmar que la base en uso no cambió

Ejecuta la consulta E de `PLAN_INTEGRACION_FUNCIONAL_PDM.md` en `bd_laravelanfeca_pruebas` y compárala con los conteos que ya tienes.

```bash
psql -h 127.0.0.1 -U rol_anfecalaravel -d bd_laravelanfeca_pruebas -Atc "select count(*) from pg_tables where schemaname='anfeca_modelo'"
```

**Esperado:** los mismos conteos que antes, y `36`. Las extensiones **no** se aplicaron allí.

## Qué NO hacer todavía

- **No** ejecutar `php artisan migrate --path=database/migrations_pdm` sobre `bd_laravelanfeca_pruebas`. Además de exigir respaldo, requiere `ANFECA_PDM_AUTORIZAR_EXTENSIONES=si`; se pedirá en F2.
- **No** ejecutar `CatalogosPdmSeeder` sobre esa base.
- **No** cambiar `ANFECA_PDM_MODO`. Cualquier valor distinto de `apagado` se rechaza en F1.

## Limpieza (opcional)

```bash
git -C /var/www/ANFECA_PRUEBAS worktree remove "$HOME/anfeca_f1"
```

```bash
sudo -u postgres dropdb bd_anfeca_pruebas_pg
```

Esto solo elimina la base aislada de pruebas, nunca la de trabajo.
