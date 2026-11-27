# Validación aislada de PowerDesigner en PostgreSQL 18

La rama `validacion/pdm-postgresql18` parte de `386c73a`, cabeza del PR #10, que incluye todo el PR #9. El DDL base de `anfeca_modelo.sql` es idéntico al del PR #9. Se usaron PHP 8.4.28 y PostgreSQL 18.7 en clústeres nuevos y desechables.

## Hallazgos y correcciones

| Hallazgo | Corrección |
| --- | --- |
| En espejo, principal y exclusivo se perdían los metadatos recientes de formación. | Se conservan el nombre exacto, procedencia, clave y versión de licenciatura y de posgrado, más la declaración `true`, `false` o `NULL`. Viven en la extensión existente `formacion_solicitud`. La actualización recupera metadatos de formaciones existentes si coinciden el ID de formación y el de solicitud. |
| El título específico de «Otro» podía superar los 150 caracteres que admite `ESTUDIO_PROFESIONAL`. | Se usa «Otro» en el dominio cuando el título largo coincide con el nombre específico admitido. La vista reconstruye el texto de hasta 200 caracteres y la extensión conserva el título completo. Se preserva el tratamiento anterior de títulos históricos. |
| La exportación de exclusivo podía intentar escribir columnas de catálogo ausentes en `public`. | Se comprueba la persistencia disponible en ambos lados, el disparador rechaza valores sin columna de retorno, y la exportación usa la intersección de columnas y detiene cambios sin destino. |
| En un clúster nuevo, un rol del catálogo de Laravel con ID explícito podía colisionar con un rol deducido de `users.rol`. | Se insertan primero los IDs históricos, se alinea la secuencia y después se crean los roles que requieren ID nuevo. |
| El script F2 calculaba la huella sin comprobarla contra un valor esperado y permitía reemplazar una base de nombre fijo. | Se exige el SHA-256 del manifiesto, se inspecciona el catálogo del archivo y se restaura en una base nueva. Fallar el hash o la restauración detiene el ensayo antes de Laravel. El diagnóstico oculta valores de `DB_URL` y `DB_PASSWORD`. |

En los casos sin equivalencia directa se mantienen las decisiones por registro. D-11 exige documentar el número de afiliación y la facultad de la institución. D-13 trata domicilios sin equivalencia confirmada y D-14 trata certificados históricos con datos no homologados. Las decisiones se vinculan al nombre de la base restaurada. El modo principal y el exclusivo exigen equivalencia cero entre `public` y las vistas. Un dato conservado en revisión durante F2 todavía puede requerir resolución para alcanzar esa condición. La prueba con el respaldo ficticio confirmó que D-11 bloquea el plan hasta registrar la decisión para su copia.

## Matriz

| Prueba | Resultado | Evidencia |
| --- | --- | --- |
| SQL PowerDesigner aprobado frente a la instalación de Laravel | PASÓ | PostgreSQL 18.7 devolvió el mismo `pg_dump --schema-only` de `anfeca_modelo` en ambas bases. 36 tablas, 173 atributos, 36 PK, 35 FK, 96 índices y 36 secuencias. SHA-256 de ambos volcados, tras retirar las órdenes `\restrict` y `\unrestrict` que varían en cada ejecución, `9ec992609e3ba2fa687cec13cc2f9a700b378d4753d029a48be6abe8934d3ab4`. |
| Suite general | PASÓ | 195 aprobadas, 2,438 aserciones. Una regla de fecha exclusiva de PostgreSQL se omitió en SQLite y se comprobó en la suite PostgreSQL. |
| Suite PDM en PostgreSQL 18.7 | PASÓ | 78 aprobadas, 1,232 aserciones. Incluye captura del catálogo en espejo, principal y exclusivo, y actualización de un esquema F4 anterior. |
| Suite de revisión documental en PostgreSQL 18.7 | PASÓ | 22 aprobadas, 159 aserciones. Incluye concurrencia. |
| Registros ficticios con certificación, refrendo, autorización, formación, documentos, compromiso, envío, acuse PDF y Coordinación | PASÓ | Suites general y PDM; el expediente y su descarga se consultaron también en modo principal. |
| Restauración con hash correcto, hash erróneo y archivo truncado | PASÓ | Copia ficticia restaurada. El hash erróneo se rechazó antes de crear la base. El archivo truncado abortó la restauración transaccional antes de Laravel, aun con su propio SHA-256 correcto. |
| Simulación, migración, conciliación, espejo, principal, exclusivo y retorno a apagado en una copia restaurada | PASÓ | Se usó el respaldo sintético. Con exclusivo, el cambio quedó en el PDM hasta exportar. Tras el retorno, `pdm-conciliar` y `pdm-equivalencia` dieron cero diferencias. |
| Compilación de producción | PASÓ | `npm run build` con Vite. |
| Restauración del respaldo institucional y pruebas sobre dicha copia | NO EJECUTADO | El entorno no tuvo acceso a AlmaLinux ni a ese respaldo. Los volcados locales fueron sintéticos. |
| Catálogos y documentos privados del ensayo institucional | NO EJECUTADO | Se validaron los formatos y los flujos con datos ficticios. Queda comprobar los JSON y archivos privados de ese ambiente. |
| Instalación o activación en ANFECA_ENSAYO y despliegue en 8008 | NO EJECUTADO | El alcance de esta rama es la validación aislada. |

## Procedimiento sobre el respaldo institucional

1. Trabajar en un worktree fuera de `/var/www` sobre la rama validada. Obtener un `pg_dump -Fc` de `bd_anfeca_ensayo_pruebas_pg` y su manifiesto autorizado. Conservar ambos con permisos privados localmente en AlmaLinux. Para un respaldo ya existente, comprobar el manifiesto emitido al generarlo.
2. Usar un clúster temporal independiente, con cliente PostgreSQL 18 compatible con el respaldo, almacenamiento suficiente y socket privado. Ejecutar como un usuario del sistema dedicado al ensayo. Ejemplo desde el worktree, usando un respaldo llamado `base.dump`.

```bash
set -euo pipefail
umask 077

respaldo=/ruta/autorizada/base.dump
(cd "$(dirname "$respaldo")" && sha256sum -c base.dump.sha256)
export ANFECA_ENSAYO_SHA256="$(awk 'NR == 1 { print $1 }' "$(dirname "$respaldo")/base.dump.sha256")"

pgbin="$(pg_config --bindir)"
"$pgbin/psql" --version
export PATH="$pgbin:$PATH"

ensayo="$(mktemp -d /var/tmp/anfeca-pdm.XXXXXXXX)"
install -d -m 700 "$ensayo/socket"
initdb -D "$ensayo/pgdata" --auth-local=peer --auth-host=reject
pg_ctl -D "$ensayo/pgdata" -l "$ensayo/postgres.log" \
  -o "-k $ensayo/socket -p 55438 -c listen_addresses=''" start

export DB_HOST="$ensayo/socket" DB_PORT=55438 DB_USERNAME="$(id -un)"
export PGPASSWORD='' DB_PASSWORD='' DB_CONNECTION=pgsql DB_URL=''
export ANFECA_PDM_MODO=apagado APP_CONFIG_CACHE="$ensayo/config-no-cache.php"
export TMPDIR="$ensayo"

bash scripts/anfeca/ensayo_copia_f2.sh "$respaldo" plan | tee "$ensayo/plan.log"
export DB_DATABASE="$(sed -n 's/^Base creada: //p' "$ensayo/plan.log")"
[[ "$DB_DATABASE" == bd_anfeca_copia_*_pruebas_pg ]]
php artisan anfeca:pdm-conexion --no-interaction
php artisan anfeca:modelo-verificar --no-interaction
```

3. Comparar la restauración con las huellas y el inventario del respaldo antes de cargar datos al PDM. Revisar `plan.json` y registrar decisiones verificadas en el archivo de configuración del **worktree**, con `base_de_datos` igual a `DB_DATABASE`. Acreditar D-11, D-13 y D-14 con las fuentes institucionales que correspondan. Si el PDM restaurado ya contiene filas, verificar directamente con conciliación y equivalencia. Si está vacío y el plan tiene cero bloqueos, ejecutar la secuencia siguiente con la conexión de la copia aún vigente.

```bash
php artisan anfeca:pdm-migrar --simular --informe="$ensayo/simulacion.json" --no-interaction
php artisan anfeca:pdm-migrar --ejecutar --confirmar --informe="$ensayo/migracion.json" --no-interaction
php artisan anfeca:pdm-conciliar --no-interaction
php artisan anfeca:pdm-equivalencia --no-interaction
```

4. Verificar las huellas de los catálogos y preparar un almacenamiento privado propio de la copia. En esa copia, activar espejo y comprobar un cambio ficticio en ambos lados. Activar principal y repetir. Activar exclusivo y comprobar que `public` conserva el valor anterior hasta la exportación. Cada modo requiere ambas llaves, que se pueden establecer con variables de proceso para la aplicación aislada y sus comandos.

```bash
ANFECA_PDM_MODO=espejo php artisan anfeca:pdm-modo espejo --confirmar --no-interaction
# Probar un cambio ficticio desde la aplicación aislada iniciada con modo espejo.
ANFECA_PDM_MODO=principal php artisan anfeca:pdm-modo principal --confirmar --no-interaction
# Probar captura, consultas, descargas y Coordinación con la aplicación aislada en principal.
ANFECA_PDM_MODO=exclusivo php artisan anfeca:pdm-modo exclusivo --confirmar --no-interaction
# Probar un cambio ficticio y comprobar que solo se refleja en el PDM.
ANFECA_PDM_MODO=exclusivo php artisan anfeca:pdm-exportar-public --confirmar --no-interaction
ANFECA_PDM_MODO=apagado php artisan anfeca:pdm-modo apagado --no-interaction
php artisan anfeca:pdm-conciliar --no-interaction
php artisan anfeca:pdm-equivalencia --no-interaction
```

Las suites destructivas de PHPUnit se ejecutan solo en bases nuevas para pruebas automatizadas. Usar el clúster temporal para crearlas con los nombres de los XML, aparte de la copia institucional restaurada. Conservar los informes y detener el clúster temporal mediante `pg_ctl -D "$ensayo/pgdata" -m fast stop` al concluir. Gestionar la copia de acuerdo con la política institucional de datos y respaldos.

## Criterio para instalar

La validación local permite pasar al ensayo sobre una **copia institucional verificada**. Antes de instalar el cambio en ANFECA_ENSAYO, deben quedar en cero los bloqueos, la conciliación y la equivalencia sobre esa copia. Deben probarse allí el catálogo oficial, los documentos y el retorno desde exclusivo. Mantener apagado el PDM en los despliegues actuales durante esta revisión.
