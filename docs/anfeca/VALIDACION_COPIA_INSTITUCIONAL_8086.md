# Validación de la copia institucional de ANFECA en 8086

## Estado y dictamen

**Pendiente de acceso institucional. No procede autorizar el despliegue.** Work no tiene acceso a las instalaciones ni al respaldo autorizado de `bd_anfeca_ensayo_pruebas_pg`. Tampoco hay evidencia de que 8086 y 8008 tengan clústeres distintos. Esta fase mantiene las conexiones, los nombres de bases y los modos PDM de los ambientes activos.

| Control | Estado |
| --- | --- |
| PR #12 y #13 | Ambos abiertos y sin fusionar. #13 parte de `85bf92e` del #12. Su cabeza `53b21d3` contiene `0f0e82f` (guardas y configuración) y el procedimiento de homologación. CI exitoso en ambos PR. |
| Diagnóstico de los dos ambientes y topología real | Pendiente. |
| Respaldo, validación de huellas y restauración con datos institucionales | Pendiente. |
| DDL de las 36 tablas, PK, FK, índices y secuencias | Aprobado previamente en aislado. No se modificó el DDL. Comparación sobre la copia institucional pendiente. |
| Migraciones, conciliación, equivalencia, certificación, refrendo y Coordinación | Pendientes sobre la copia institucional. |
| Regresión de consistencia del respaldo bajo escritura concurrente | PASÓ en PostgreSQL 18.7 desechable. Tras insertar una segunda fila en el origen, la copia restaurada y las huellas siguieron reflejando una fila. La prueba también confirmó que un fallo del callback se devuelve al llamador. |
| Plan y reversión para 8086 y 8008 | Preparados, pendientes de ensayo institucional. |

Ejecuciones de referencia [PR #12](https://github.com/Quarito/SANFECA/actions/runs/45953360149) y [PR #13](https://github.com/Quarito/SANFECA/actions/runs/45955574070).

## Primera sesión autorizada en AlmaLinux

Desde un checkout de revisión, ejecutar el inventario por separado en los hosts que alojan cada ruta. Cuando las instalaciones vivan en hosts distintos, ejecutar en cada servidor únicamente el comando que corresponda.

```bash
auditoria="$PWD"
php "$auditoria/scripts/anfeca/inventario_servidor.php" /var/www/ANFECA_ENSAYO
php "$auditoria/scripts/anfeca/inventario_servidor.php" /var/www/anfeca
```

Cotejar la conexión de CLI con la configuración efectiva de Apache/PHP-FPM, scheduler y workers usando el [inventario comparativo](AUDITORIA_SERVIDORES_8086_8008.md). Registrar endpoint, nombre real, OID e identificador de sistema. El DBA obtiene el identificador con `select system_identifier from pg_control_system();` si el rol de la aplicación carece de permiso. Un mismo identificador requiere comprobar además si se trata de la misma instancia, réplica o clon antes de asignar nombres físicos. Si comparten clúster, reservar una instancia separada o acordar nombres físicos diferenciados.

## Respaldo y copia

Verificar primero que 8086 conecta a `bd_anfeca_ensayo_pruebas_pg`. Confirmar que el archivo de entorno leído por el respaldo coincide con la conexión efectiva. Si Laravel usa `DB_URL` o parámetros sobreescritos por servicios, el DBA debe seleccionar explícitamente el origen correcto. Ejecutar el procedimiento de respaldo desde el checkout de revisión, conservando la aplicación activa como directorio de trabajo del proceso que identifica el origen.

```bash
(
  cd /var/www/ANFECA_ENSAYO
  rama=$(git symbolic-ref --short HEAD)
  commit=$(git rev-parse HEAD)
  bash "$auditoria/scripts/anfeca/respaldo_verificado.sh" bd_anfeca_ensayo_pruebas_pg "$rama" "$commit"
)
```

El respaldo y sus huellas se generan bajo una instantánea de solo lectura compartida. La verificación del respaldo usa una restauración aislada. Conservar dentro del servidor o del almacenamiento institucional autorizado `base.dump`, `base.dump.sha256`, `huellas_origen.txt`, el catálogo y el informe. Tener en cuenta el tamaño del respaldo y la capacidad de la instancia temporal antes de ejecutarlo. Verificar el SHA-256 y el catálogo al preparar la copia de trabajo, y conservar localmente el informe de esa copia.

Con el socket, puerto y rol del clúster temporal ya verificados, y el checkout de revisión fuera de `/var/www`:

```bash
(cd "$DIRECTORIO_RESPALDO" && sha256sum -c base.dump.sha256)
export ANFECA_ENSAYO_SHA256="$(awk 'NR == 1 {print $1}' "$DIRECTORIO_RESPALDO/base.dump.sha256")"
export ANFECA_ENSAYO_HUELLAS="$DIRECTORIO_RESPALDO/huellas_origen.txt"
export ANFECA_ENSAYO_BD_ORIGEN=bd_anfeca_ensayo_pruebas_pg
export DB_HOST="$SOCKET_TEMPORAL" DB_PORT="$PUERTO_TEMPORAL" DB_USERNAME="$ROL_TEMPORAL"
bash scripts/anfeca/ensayo_copia_f2.sh "$DIRECTORIO_RESPALDO/base.dump" plan
```

Antes de cualquier migración, guardar prueba del SHA-256 y de la igualdad de huellas de todas las tablas. Comparar el catálogo PDM y restricciones con [la matriz de 36 tablas](AUDITORIA_36_TABLAS_POWERDESIGNER.md). Resolver D-11, D-13 y D-14 solo con evidencia del origen restaurado y registrar decisiones con el nombre de esa copia. Cuando el plan tenga cero bloqueos, simular, ejecutar y comprobar conciliación y equivalencia en la copia según [la guía PostgreSQL 18](VALIDACION_PDM_POSTGRESQL18.md). Probar el nombre `certificacion_anfeca` en una segunda base nueva del clúster temporal, con las guardas y autorizaciones limitadas al proceso, manteniendo una copia previa al cambio.

Probar con cuentas y expedientes ficticios creados en la aplicación aislada. Cubrir primera certificación, refrendo con el antecedente mínimo, autorización, documentos, compromiso, acuse y acceso de Coordinación por rol y zona. Usar archivos de prueba y una ruta de almacenamiento propia. Comprobar los catálogos vigentes y verificar la apertura de documentos previamente seleccionados con permisos institucionales dentro del entorno controlado.

## Criterio de autorización

Se puede solicitar el despliegue de 8086 al acreditar identidad de origen y destino, igualdad de conteos y huellas, estructura fiel, cero bloqueos, conciliación y equivalencia en cero, flujos funcionales aprobados y reversión ensayada. Usar el [plan de despliegue](PLAN_DESPLIEGUE_8086_8008.md) y el [plan de reversión](PLAN_REVERSIÓN_BASE_DATOS.md). Para 8008 se requiere su propio diagnóstico, respaldo y copia, aunque comparta el código de 8086.
