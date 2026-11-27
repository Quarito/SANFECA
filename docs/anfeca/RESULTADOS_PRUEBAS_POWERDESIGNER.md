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
