# Validación de integridad de datos ANFECA

## Respaldo y restauración ejecutados

Se generó un respaldo PostgreSQL en formato personalizado a partir de una base temporal con el nombre esperado por el ensayo y con los datos de `Tests/Pdm/DatosPublicFicticios.php`. El clúster PostgreSQL 18.7 se limitó al loopback. La restauración creó una base nueva con el script `scripts/anfeca/ensayo_copia_f2.sh`, usando transacción única y salida ante error.

| Evidencia sintética | Resultado |
| --- | --- |
| SHA-256 del respaldo correcto | `8966f056b0c2dc45bcdf190821450102fe7e0320b6e69bcd5ac58f7cf08ea559` |
| Manifiesto de origen | 26 tablas y 66 filas en total. SHA-256 `1f63c7f9b24b5bf786bc6b3f3ce7586f12e9f93b78abe3bc8ba7f520a324bc1b`. |
| Cotejo con la copia antes de Laravel | 26/26 tablas con conteo y huella de filas idénticos. |
| SHA-256 incorrecto | Se rechazó antes de crear la base. |
| Archivo truncado con SHA-256 válido para ese archivo | Falló la restauración transaccional antes de Laravel. |
| Respaldo legible con el mismo conteo y contenido diferente | Se rechazó al comparar las huellas. El script se detuvo antes de Laravel. |

El manifiesto se genera con `huellas_bd_instantanea` mediante una lectura `REPEATABLE READ READ ONLY`. Cada registro del manifiesto representa una tabla, su conteo y el MD5 de la serialización ordenada de sus filas. El SHA-256 del archivo completo verifica el respaldo frente al manifiesto esperado. Los artefactos de respaldo y los datos de filas quedaron fuera del repositorio.

| Datos de interés | Filas en el origen y en la copia antes de migrar |
| --- | ---: |
| `public.users` | 6 |
| `public.datos_solicitantes` | 4 |
| `public.certificados_historicos` | 3 |
| `public.solicitudes_certificacion` | 3 |
| `public.datos_refrendo` | 1 |
| `public.formaciones_academicas_solicitud` | 1 |
| `public.documentos_probatorios_solicitud` | 1 |
| `public.historial_documentos_academicos` | 1 |
| `public.instituciones_afiliadas` y `public.zonas` | 1 cada una |

## Migración, conciliación y retorno ejecutados

1. El plan sobre la copia restaurada detectó un bloqueo D-11. Se registró la decisión ficticia de afiliación y facultad mediante la configuración de proceso, acotada al nombre de la base desechable.
2. Se ejecutó `anfeca:pdm-migrar --simular`. La conciliación de la simulación fue satisfactoria y se revirtieron sus filas. PostgreSQL puede avanzar secuencias durante una transacción revertida.
3. Se ejecutó `anfeca:pdm-migrar --ejecutar --confirmar`. El comando verificó que las huellas operativas de `public` seguían iguales. `anfeca:pdm-conciliar` y `anfeca:pdm-equivalencia` devolvieron cero diferencias.
4. En modo espejo se verificó que un cambio en `public` se reflejó en el PDM. En principal, la escritura a través de la vista se replicó en `public`. En exclusivo se comprobó que `public` conservó el valor anterior.
5. `anfeca:pdm-exportar-public --confirmar` recuperó el cambio de exclusivo. Se retornó a apagado y se repitieron conciliación y equivalencia con cero diferencias.

Las pruebas automáticas cubren D-11, D-13 y D-14 con casos ficticios, incluidos falta de facultad oficial, entidad federativa que requiere decisión y certificado histórico con numeración no homologada. Los datos y decisiones aprobadas para otros nombres de base se mantienen fuera de la validación sobre una copia nueva. Los archivos de documentos usados en los flujos automáticos son ficticios. Las huellas de tablas verifican las rutas y demás datos registrados, mientras que la apertura y descarga de un archivo se comprueban mediante el flujo funcional.

## Copia institucional pendiente

El respaldo y su manifiesto institucional no estuvieron disponibles desde Work. La integridad de `bd_anfeca_ensayo_pruebas_pg`, los certificados históricos reales, los archivos privados y los catálogos oficiales instalados quedan **PENDIENTES**. Sobre una futura copia institucional se cotejarán los archivos y metadatos, se resolverán D-11, D-13 y D-14 con sus fuentes, y se exigirá cero bloqueos y diferencias no justificadas antes de evaluar un despliegue. Las bases institucionales y sus archivos quedaron intactos. [Procedimiento de validación](VALIDACION_PDM_POSTGRESQL18.md)
