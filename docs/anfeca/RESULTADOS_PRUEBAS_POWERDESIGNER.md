# Resultados de pruebas PowerDesigner

## Revisiones y entorno

| Revisión | Estado comprobado |
| --- | --- |
| [PR #9](https://github.com/Quarito/SANFECA/pull/9) | Abierto. Alineación del DDL y operación con las PK compuestas. Cabeza `4b48832`. |
| [PR #10](https://github.com/Quarito/SANFECA/pull/10) | Abierto. Guardas del ensayo y CI en PostgreSQL 18. Cabeza `792f4c7`. |
| [PR #11](https://github.com/Quarito/SANFECA/pull/11) | Abierto en borrador. Referencia validada `74fb30b`. [Ejecución previa de GitHub Actions](https://github.com/Quarito/SANFECA/actions/runs/45951778605). |
| [Regresión de esta auditoría](https://github.com/Quarito/SANFECA/commit/9c2755494c828bd1e275ee301f1b7f9c6ec6f4b3) | Comprueba PK ordenadas y relaciones Eloquent en ambos sentidos. El árbol del commit publicado coincide con el árbol probado localmente. |

Se usaron PHP 8.4.28, PostgreSQL 18.7 y PHPUnit 12.5.40. Se crearon bases nuevas en clústeres privados y se usaron datos ficticios. Las suites se ejecutaron en serie porque comparten el almacenamiento desechable dentro del worktree.

## Resultados finales ejecutados

| Comando o prueba | Aprobadas | Fallidas | Omitidas | Aserciones |
| --- | ---: | ---: | ---: | ---: |
| `env -u DB_CONNECTION -u DB_URL php vendor/bin/phpunit -c phpunit.xml` | 200 | 0 | 1 | 2452 |
| `php vendor/bin/phpunit -c phpunit.pgsql.xml` | 79 | 0 | 0 | 1234 |
| `php vendor/bin/phpunit -c phpunit.revision-pg.xml` | 22 | 0 | 0 | 159 |
| Total | **301** | **0** | **1** | **3845** |

La prueba omitida comprueba una regla de fecha exclusiva de PostgreSQL. Se ejecutó como parte de la suite de revisión documental en PostgreSQL. Se retiró la variable `DB_CONNECTION=pgsql` heredada al ejecutar la suite general después de que la guarda de pruebas detectara que la conexión debía ser SQLite en memoria. El resultado de la tabla corresponde a la repetición completa con esa corrección.

| Verificación adicional | Resultado |
| --- | --- |
| `sha256sum -c` de la fuente aprobada; instalación SQL y `anfeca:modelo-instalar` en bases nuevas | PASÓ |
| Inventarios ordenados con `psql -XqAt -v ON_ERROR_STOP=1` y comparación con `cmp` | PASÓ |
| `pg_dump -n anfeca_modelo --schema-only` y comparación con `cmp` después de retirar los marcadores variables | PASÓ |
| Migraciones ordinarias y `migrate --path=database/migrations_pdm --force --no-interaction` sobre `instalado_pdm36` | PASÓ |
| `bash -n` de los scripts de ensayo, respaldo y huellas; `npm run build` | PASÓ |
| Restauración correcta y rechazo de SHA-256 incorrecto, archivo truncado y contenido distinto con el mismo conteo | PASÓ con respaldo ficticio |
| Registro inicial, refrendo, autorización, formación y ANUIES, documentos, compromiso, envío, PDF y Coordinación | PASÓ con usuarios y archivos ficticios |
| Migración, conciliación, equivalencia, espejo, principal, exclusivo, exportación y retorno a apagado | PASÓ sobre la copia restaurada ficticia |

## Dictamen y evidencia

| Alcance | Dictamen |
| --- | --- |
| Fidelidad estructural de las 36 tablas base en entorno aislado | **APROBADO**. 36/36 sin diferencias. |
| Integración operativa y conservación de datos ficticios | **APROBADO CON OBSERVACIONES**. Se conservó la definición de los objetos preexistentes. Las adiciones operativas deben revisarse como alcance separado del esquema base. |
| Integridad y flujos de una copia institucional | **PENDIENTE**. No se tuvo acceso al respaldo autorizado, al manifiesto del origen ni a sus archivos privados. |
| Despliegue en los servidores actuales | **NO EJECUTADO**. El despliegue queda condicionado a la validación institucional y a la aceptación de la capa operativa. |

[Inventario estructural detallado](AUDITORIA_36_TABLAS_POWERDESIGNER.md) · [Restauración y huellas](VALIDACION_INTEGRIDAD_DATOS.md) · [Compatibilidad de Laravel](COMPATIBILIDAD_LARAVEL_POWERDESIGNER.md)

## Fase de homologación a certificacion_anfeca

La referencia de esta fase es el [PR #12 en `85bf92e`](https://github.com/Quarito/SANFECA/pull/12). Al iniciar se verificaron exitosas las [acciones del PR #11](https://github.com/Quarito/SANFECA/actions/runs/45951778605) y las [del PR #12](https://github.com/Quarito/SANFECA/actions/runs/45953360149). PHP 8.4.28 y PostgreSQL 18.7 se ejecutaron en clústeres desechables.

| Comprobación de esta fase | Resultado |
| --- | --- |
| PHPUnit general | 203 aprobadas, 1 omitida |
| PHPUnit PDM en PostgreSQL 18.7 | 79 aprobadas |
| PHPUnit revisión documental PostgreSQL 18.7 | 22 aprobadas |
| Total | **304 aprobadas, 0 fallidas, 1 omitida**. La omitida en SQLite se comprobó en PostgreSQL. |
| DDL aprobado frente a `certificacion_anfeca` | 36/36; SHA-256 de cada volcado normalizado `aceed2c859f69addc6c1916c1db9fff56b5f4798a975a00b6cdfdf75e9a8ad52`. |
| Protección y uso del nombre oficial | Instalación, extensiones, seed y verificación ejecutados con autorización de proceso solo en el laboratorio. Rechazados instalación, extensiones, simulación, migración y vaciado sin la autorización o identidad descartable aplicable. |
| Aislamiento y FK | Dos clústeres temporales con el mismo nombre físico, identificadores distintos y escrituras independientes. Eloquent leyó la relación creada. PostgreSQL rechazó la FK huérfana. |
| Restauración histórica y oficial sintéticas | PASÓ con `pg_dump -Fc`, `pg_restore --list`, `ensayo_copia_f2.sh … plan`, comprobación de huellas y migración, conciliación, equivalencia y retorno desde exclusivo. |
| Pruebas del validador Bash y compilación | 18/18 del validador de ensayo. Sintaxis de scripts y PHP aprobada; Vite compiló. |
| Copias reales, catálogos oficiales y despliegue | PENDIENTE, sin acceso autorizado a los ambientes o respaldos. |

| Problema verificado | Causa e impacto | Corrección y evidencia |
| --- | --- | --- |
| Clasificación del ensayo histórico como descartable | El nombre de 8086 termina en `_pruebas_pg`. Basarse solo en ese sufijo permitía omitir controles de escritura. | `app/Services/Pdm/BasePdm.php`, migraciones, seeder y comandos Artisan comprueban identidad, conexión y autorización. Regresión en `BasePdmTest.php` y `GuardiaBaseAisladaTest.php`, más prueba en una base local con nombre oficial. |
| Origen fijado a un nombre único del respaldo | El TOC de una base ya homologada deja de coincidir con el nombre histórico. | Variable explícita de origen en `scripts/anfeca/ensayo_copia_f2.sh` con coincidencia literal, SHA-256 y huellas verificados. Ensayos con ambos nombres. |
| Topología institucional sin evidencia | Los puertos HTTP no determinan la separación de PostgreSQL. | Inventario de solo lectura y procedimientos por ambiente. El laboratorio probó el caso de dos clústeres; la topología real sigue pendiente. |

[Inventario de servidores](AUDITORIA_SERVIDORES_8086_8008.md) · [Nombre oficial](MIGRACION_NOMBRE_CERTIFICACION_ANFECA.md) · [Despliegue](PLAN_DESPLIEGUE_8086_8008.md) · [Reversión](PLAN_REVERSIÓN_BASE_DATOS.md)
