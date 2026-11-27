# F2 — Migración de datos `public` → `anfeca_modelo` y conciliación

> Rama `integracion/pdm-f2`, basada en `integracion/pdm-f1` (incluye F1 y su refuerzo de aislamiento).
> **Estado:** implementada y probada en PostgreSQL aislado. **No se ha migrado ningún dato real.** La migración definitiva se niega mientras existan decisiones pendientes (D-11, D-13, D-14).

## 1. Funcionalidad implementada

| Comando | Qué hace | Escribe |
|---|---|---|
| `php artisan anfeca:pdm-migrar` | **Plan:** calcula, solo leyendo, qué filas tendría el PDM e informa las incidencias (bloqueantes e informativas), con los IDs de origen | No |
| `php artisan anfeca:pdm-migrar --simular` | Escribe, concilia y **revierte** todo. Solo en bases aisladas `*_pruebas_pg`. | No (revierte) |
| `php artisan anfeca:pdm-migrar --ejecutar` | Migra en **una transacción**. Confirma solo si hay **0 diferencias** en la conciliación y `public` no cambió (huella MD5). | Sí, solo en `anfeca_modelo` |
| `php artisan anfeca:pdm-conciliar` | **Solo lectura.** Compara el PDM con lo que indican `public` y las decisiones: faltantes, sobrantes, duplicados y valores distintos. Código de salida 1 si hay diferencias. | No |
| `php artisan anfeca:pdm-vaciar` | **Reversión:** elimina de `anfeca_modelo` los datos migrados. No toca catálogos ni `public`. Se niega si hay datos de etapa 2. | Sí, solo en `anfeca_modelo` |

**Protecciones** (`ProteccionMigracion`):
- `--simular` solo funciona en bases `*_pruebas_pg`.
- `--ejecutar` y el vaciado en `bd_laravelanfeca_pruebas` exigen `ANFECA_PDM_AUTORIZAR_MIGRACION=si` o `ANFECA_PDM_AUTORIZAR_VACIADO=si` en el propio comando. Cualquier otra base se rechaza.
- La migración solo parte de un PDM vacío, así que nunca duplica.
- Todas las operaciones requieren el modo `apagado`.
- Cada ejecución deja un informe JSON en `storage/app/anfeca_pdm/` (permisos 600) con IDs, códigos y motivos, **sin datos personales**.

### Reglas de transformación

Las reglas son las de `PLAN_INTEGRACION_FUNCIONAL_PDM.md` §3 y `DECISIONES_DATOS_F1.md`. Lo esencial:

**IDs y folio**
- Se conservan los IDs: persona = `datos_solicitantes.id`, usuario = `users.id`, solicitud = `solicitudes.id`; lo mismo en zonas, instituciones, roles y privilegios.
- El folio es igual al ID.
- Las secuencias se alinean al terminar.

**Persona y usuario**
- `USUARIO` lleva el mismo hash de contraseña que `users` (D-1 A).
- Los usuarios sin datos personales se quedan solo en `users`.

**Domicilio y contacto**
- La entidad se normaliza con las reglas N-1 a N-3.
- Colonia, municipio y código postal van a las columnas de extensión.
- Los teléfonos y el correo van a `COMUNICACION`.

**Solicitud**
- Área y tipo de personal se toman de la solicitud o de la selección del mismo usuario (N-6).
- Refrendo: `SOLI_ART14`, el inciso en `ARTICULO_SUBINDICE`, y `SOLI_ART13` = refrendo con datos de refrendo y sin art. 14 (D-8).
- Antecedente: número y FK a la importación.

**Eventos** (D-3)
- `BO` creación, `FA` formación autorizada, `CA` compromiso, `EN` envío.
- Fecha y hora en America/Mexico_City.

**Formación**
- Dos filas por trámite: `ORIGEN` y `ULTIMO`.
- «Otro» o un título fuera del catálogo se registran como estudio en revisión (`REV`) con el texto literal.

**Documentos**
- Cada archivo vigente queda como `Cargado` y cada versión de `historial_documentos_academicos` como `Sustituido`, con la ruta intacta.
- El enlace de evidencias se registra como documento.
- El comprobante también va a `PAGO`.

**Histórico de certificados**
- Las filas se copian **íntegras** a `HISTORICO_CERTIFICADO_IMPORTACION`, con el mismo ID.
- Solo las filas con número y años válidos generan eventos en `HISTORICO_CERTIFICADO`.

**Sin truncar.** Un valor más largo que la columna del PDM se marca como incidencia D-4 y la fila **no** se migra.

## 2. Archivos

| Archivo | Tipo |
|---|---|
| `app/Services/Pdm/Migracion/PlanMigracionPdm.php` | Nuevo: plan (solo lectura) e incidencias |
| `app/Services/Pdm/Migracion/MigradorPdm.php` | Nuevo: escritura |
| `app/Services/Pdm/Migracion/ConciliadorPdm.php` | Nuevo: conciliación (solo lectura) |
| `app/Services/Pdm/Migracion/VaciadorPdm.php` | Nuevo: reversión |
| `app/Services/Pdm/Migracion/ProteccionMigracion.php` | Nuevo: precondiciones y huella de `public` |
| `app/Services/Pdm/Migracion/Incidencia.php`, `SimulacionRevertida.php` | Nuevos |
| `app/Console/Commands/AnfecaPdmMigrar.php`, `AnfecaPdmConciliar.php`, `AnfecaPdmVaciar.php` | Nuevos |
| `config/anfeca_pdm_decisiones.php` | Nuevo: **única vía** para resolver bloqueos, registro por registro y con su fuente |
| `tests/Pdm/MigracionPdmTest.php`, `tests/Pdm/DatosPublicFicticios.php` | Nuevos: 10 pruebas sobre un escenario ficticio que reproduce los casos reales del servidor |

**No se modificó** ningún controlador, modelo, vista, ruta ni migración existente.

## 3. Resultados de las pruebas (PostgreSQL 16.15, base aislada)

| Suite | Resultado |
|---|---|
| `php artisan test -c phpunit.pgsql.xml` | **31/31**, 538 aserciones: 21 de F1 + 10 de F2 |
| `php artisan test` (SQLite) | 44/45. Solo falla `ExampleTest`, corregida en `fix/seguridad-anfeca`. |
| **Mutación** (el migrador deja de escribir `PAGO`) | La conciliación lo detecta y revierte; fallan 5 pruebas de F2. Confirma que las pruebas no pasan por accidente. |

Pruebas de F2:

| Prueba | Verifica |
|---|---|
| `el_plan_es_de_solo_lectura_y_reporta_los_bloqueos` | Nada escrito, `public` intacto. Bloqueos exactos: D-11 (institución), D-13 (Monterrey, Entidad de Prueba), D-14 (número `ANF-77`). Informativas: usuarios sin datos, N-6, rol desde `users.rol`. |
| `ejecutar_se_niega_mientras_haya_bloqueos` | Sin decisiones no se escribe nada |
| `la_simulacion_escribe_concilia_y_revierte` | Simulación con 0 diferencias y PDM vacío al terminar |
| `migracion_completa_conserva_datos_relaciones_e_identificadores` | Conteos exactos. IDs y folio conservados. `cdmx` → 09, `Monterrey` → 19 **solo por decisión**, `quintana roo` → 23. Género vacío → nulo. Refrendo art. 14.1 con área por N-6. Eventos con hora local. «Otro» en REV. 7 documentos vigentes + 1 sustituido, enlace y pago. Copia fiel del histórico, incluido el de revisión. Mismo hash de contraseña. Conciliación 0. Secuencias alineadas. Segunda ejecución rechazada. |
| `la_conciliacion_detecta_alteraciones` | Detecta un valor alterado y un registro faltante |
| `vaciar_revierte_y_permite_volver_a_migrar` | PDM vacío y catálogos y `public` intactos; se vuelve a migrar y conciliar |
| `vaciar_se_niega_si_hay_datos_de_etapa_2` | No borra datos que no provienen de la migración |
| `valores_que_no_caben_bloquean_sin_truncar` | Un número exterior de 10 caracteres → D-4 y sin fila truncada |
| `solicitud_de_usuario_sin_datos_bloquea` | No se crea una persona inventada |
| `la_base_en_uso_exige_autorizacion_explicita` | Sin la variable no se migra ni se simula en la base en uso |

## 4. Decisiones pendientes y cómo registrarlas

En `config/anfeca_pdm_decisiones.php`, **por ID de `public`** y con su fuente:

| Decisión | Registro | Opciones |
|---|---|---|
| **D-11** (1 institución) | `'instituciones' => [ID => [...]]` | `['numero_afiliacion' => '≤7', 'facultad' => '...', 'fuente' => '...']`, o `'no_migrar'` |
| **D-13** (7 domicilios) | `'domicilios' => [ID_datos_solicitante => [...]]` | `['entidad' => 'Nombre exacto INEGI', 'fuente' => '...']`, o `'sin_domicilio'` (la persona y su solicitud se migran igual) |
| **D-14** (8 certificados) | `'certificados' => [ID => [...]]` | `['numero' => '1234567', 'fuente' => '...']`, o `'conservar_en_revision'` (fila íntegra en la importación, sin eventos) |

**No se infiere ninguna equivalencia.** Monterrey no se asigna a Nuevo León sin una decisión registrada.

## 4b. Verificar la conexión efectiva (antes de cualquier operación)

`php artisan anfeca:pdm-conexion` es de **solo lectura**. Muestra:
- la configuración efectiva;
- si está en caché;
- la base **real** (`current_database()`), la versión del servidor y las tablas de `anfeca_modelo` y `public`;
- cada línea `DB_*` del `.env`, sin mostrar la contraseña.

Explica por qué un `grep DB_DATABASE .env` puede no mostrar una línea que existe. Señala:
- BOM al inicio del archivo;
- fin de línea `CRLF`;
- espacios al inicio;
- prefijo `export`;
- líneas duplicadas (gana la última);
- variables exportadas en la sesión, que tienen prioridad sobre `.env` (las lee de `/proc/self/environ`).

## 4c. Ensayo automatizado sobre la copia

`scripts/anfeca/ensayo_copia_f2.sh` automatiza el procedimiento de la sección 5. Se ejecuta desde la raíz del worktree:

```bash
bash scripts/anfeca/ensayo_copia_f2.sh /ruta/respaldo.dump plan
```

| Modo | Qué hace |
|---|---|
| `plan` (por omisión) | Verifica el respaldo (fecha, tamaño, SHA-256, `pg_restore -l`) y lo restaura en `bd_anfeca_copia_pruebas_pg`. Compara conteos con la base de trabajo (solo `SELECT`). Confirma la conexión. Prepara el PDM y las extensiones en la copia y muestra el plan. |
| `simular` | Además: simulación con conciliación y reversión |
| `completo` | Además: migración en la copia, conciliación, verificación de IDs, usuarios y hashes, históricos, autorizaciones, compromisos e historial, y **reversión probada** (vaciado y comprobación de `public`) |

**Protecciones:**
- Se niega fuera de un worktree o desde `/var/www/ANFECA_PRUEBAS`.
- Solo escribe en `bd_anfeca_copia_pruebas_pg`. Si la copia ya existe, exige `--recrear` y escribir la palabra RECREAR.
- La contraseña se pide en la sesión.
- Los resultados quedan en `$HOME/ANFECA_RESPALDOS/ensayo_f2/<fecha>/`, con permisos 700/600.

**Probado en este entorno (PostgreSQL 16.15)** con un respaldo de una base local que simula la de trabajo, con datos ficticios. El modo `completo` terminó así:
- 39 tablas y 42 FK;
- simulación y migración con 0 diferencias;
- todas las verificaciones de IDs coinciden;
- vaciado correcto y `public` sin cambios.

La base de trabajo local quedó **idéntica**: misma huella y el PDM sin filas.

## 5. Procedimiento para validarlo (sin tocar la base de trabajo)

Lo recomendado es ensayar sobre una **copia restaurada** de tu respaldo, en una base aislada. Así ves el plan con los datos reales sin modificar `bd_laravelanfeca_pruebas`.

> La copia contiene datos personales: mantenla solo en el servidor, con acceso restringido, y elimínala al terminar.

1. Traer la rama al worktree:

   ```bash
   cd /home/servidorweb/anfeca_f1 && git fetch origin integracion/pdm-f2 && git checkout --detach origin/integracion/pdm-f2 && composer install --no-interaction && php artisan config:clear
   ```

2. Ejecutar las pruebas aisladas (deben dar 31/31):

   ```bash
   export DB_HOST=127.0.0.1 DB_PORT=5432 DB_USERNAME=rol_anfecalaravel && read -s -p "Contraseña: " DB_PASSWORD && export DB_PASSWORD && echo
   ```

   ```bash
   php artisan test -c phpunit.pgsql.xml
   ```

3. Restaurar el respaldo en una base aislada cuyo nombre termine en `_pruebas_pg`:

   ```bash
   sudo -u postgres createdb -O rol_anfecalaravel bd_anfeca_copia_pruebas_pg
   ```

   ```bash
   pg_restore -h 127.0.0.1 -U rol_anfecalaravel -d bd_anfeca_copia_pruebas_pg --no-owner "$(ls -t "$HOME"/ANFECA_RESPALDOS/*.dump | head -1)"
   ```

Si el respaldo es **anterior** a la instalación de `anfeca_modelo` (la copia no tiene el esquema), instálalo primero en la copia:

```bash
ANFECA_MODELO_BD_PERMITIDA=bd_anfeca_copia_pruebas_pg DB_CONNECTION=pgsql DB_DATABASE=bd_anfeca_copia_pruebas_pg php artisan anfeca:modelo-instalar --ejecutar
```

4. Preparar el PDM en la copia (extensiones y catálogos) y ver el plan:

   ```bash
   export DB_CONNECTION=pgsql DB_DATABASE=bd_anfeca_copia_pruebas_pg
   ```

   ```bash
   php artisan migrate --path=database/migrations_pdm --force && php artisan db:seed --class="Database\Seeders\Pdm\CatalogosPdmSeeder" --force
   ```

   ```bash
   php artisan anfeca:pdm-migrar
   ```

   **Esperado:** la tabla de conteos y las incidencias. Según tus consultas, debería haber bloqueos D-11 (1), D-13 (7) y D-14 (8). Compárteme la salida o el JSON del informe: no contiene datos personales.

5. Cuando registres las decisiones en `config/anfeca_pdm_decisiones.php` del worktree:

   ```bash
   php artisan config:clear && php artisan anfeca:pdm-migrar --simular
   ```

   Si la simulación es correcta:

   ```bash
   php artisan anfeca:pdm-migrar --ejecutar && php artisan anfeca:pdm-conciliar
   ```

   Ambos pasos son sobre la **copia**.

6. Al terminar:

   ```bash
   unset DB_PASSWORD DB_DATABASE DB_CONNECTION && sudo -u postgres dropdb bd_anfeca_copia_pruebas_pg
   ```

**La migración en `bd_laravelanfeca_pruebas` se hará solo con tu autorización específica**, después de validar la copia:
- respaldo nuevo;
- extensiones con `ANFECA_PDM_AUTORIZAR_EXTENSIONES=si`;
- catálogos;
- `--ejecutar` con `ANFECA_PDM_AUTORIZAR_MIGRACION=si`;
- conciliación.

Conviene hacerlo **justo antes de activar el modo `espejo` (F3)**. Si se registran usuarios entre la migración y F3, la conciliación lo reportará como faltante y habrá que vaciar y volver a migrar.

## 6. Procedimiento de reversión

| Qué revertir | Cómo | Efecto |
|---|---|---|
| Datos migrados | `php artisan anfeca:pdm-vaciar` (más la variable de autorización en la base en uso) | `DELETE` en `anfeca_modelo` dentro de una transacción. Catálogos y `public` intactos; se comprueba con la huella. |
| Extensiones de F1 | `php artisan migrate:rollback --path=database/migrations_pdm` | Solo procede si las tablas de dominio del PDM están vacías |
| Código | No fusionar la rama, o `git revert` del merge | Las clases y comandos nuevos no afectan al flujo actual (modo `apagado`) |
| Todo, en la base en uso | Restaurar el `pg_dump` previo | Último recurso |

## 7. Riesgos

| Riesgo | Mitigación |
|---|---|
| PG16 aquí frente a PG18.6 en el servidor | Ejecutar la suite en AlmaLinux (paso 2) |
| Datos reales con casos no vistos en las consultas A–F | El plan los reporta como incidencias; nada se trunca ni se inventa |
| `USUARIO` duplica el hash de `users` (D-1 A) | Si cambia la contraseña antes de F3, la conciliación lo marcará; F3 sincroniza esa escritura |
| Registros nuevos entre F2 y F3 | Migrar justo antes de F3; la conciliación lo detecta |
| Configuración en caché | Las pruebas y los comandos leen la configuración vigente: ejecutar `config:clear` tras editar las decisiones |
| Zona horaria | Laravel guarda en UTC (`config/app.php`); el PDM, fecha y hora de la Ciudad de México (sin horario de verano desde 2022) |
