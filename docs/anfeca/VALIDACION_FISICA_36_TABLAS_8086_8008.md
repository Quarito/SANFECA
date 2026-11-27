# Validación física de certificacion_anfeca en PostgreSQL 18.6

## Referencia y resultados

Esta verificación parte de `00c2896220949fa47e54bd9516531ef09e2fbc2a` (PR #17). El archivo entregado `anfeca_modelo_powerdesigner_corregido_claude.sql` estaba disponible en el espacio de trabajo. Sus bytes coincidieron con el SHA-256 que referencia el instalador, `0963a389842f77f62a42c3ce40ca20a0f7f2b779cfb4881f2b0f7d4c04a37a8d`. El export literal `anfeca_certifica_1.original.sql` tiene otra huella (`76289767d6dd66996d0a8a05e78f2a6a71bf55cf99ef7656d36a842b8a9701d5`), y la reconstrucción previa del PR #17 registró un error de índice en ese export. Las diferencias frente al export literal no son diferencias frente al archivo entregado y recuperado.

Se ejecutó el SQL entregado íntegro en una base desechable. En otra llamada `certificacion_anfeca` se ejecutó `php artisan anfeca:modelo-instalar --ejecutar --confirmar --no-interaction` con PHP **8.5.12**. PostgreSQL y su cliente eran **18.6**. El inventario físico y el volcado de todo el DDL coincidieron. El SHA-256 de ambos volcados normalizados fue `3017cedd01c83cac7805e3440858072927843dd5c00cfdc739fbd97ef2da7f4e`. Se excluyeron marcas variables de `pg_dump`, propietarios y ACL de la comparación estructural. Los permisos institucionales se verifican por separado.

| Prueba | Resultado |
| --- | --- |
| DDL base contra SQL entregado | **PASÓ.** 36/36 tablas, 173/173 atributos, 36 PK, 35 FK, 96 índices y 36 secuencias. |
| Instalación ampliada con migraciones ordinarias y PDM | **FALLÓ la equivalencia física esperada.** 75 tablas y 509 atributos en la réplica sintética, distribuidos como 44/266 en `anfeca_modelo`, 26/232 en `public` y 5/11 en `anfeca_compat`. |
| Respaldo y restauración de la instalación ampliada | **PASÓ en laboratorio.** `pg_dump -Fc`, manifiesto SHA-256, lectura del índice, `pg_restore --exit-on-error --single-transaction` y comparación de conteos y huellas por tabla mediante `huellas_bd_instantanea`. |
| PHP-FPM 8.5.12 y GET /login | **PASÓ** conectado al candidato de 36 tablas. |
| Autenticación Eloquent | **PASÓ** con una cuenta sintética en la restauración de 75 tablas. **FALLÓ** en el candidato de 36 tablas con SQLSTATE `42P01` al consultar `users`. |
| PHPUnit con PHP 8.5.12 | **305 aprobadas, 0 fallidas, 1 omitida.** 204 generales (SQLite), 79 PDM y 22 de revisión documental (PostgreSQL 18.6). Las suites verifican la aplicación actual, que usa las tablas ampliadas. |
| Conmutación y reversión simuladas | **13/13** con dobles locales, sin acceso a servicios ni credenciales institucionales. Incluye ausencia de evidencia inicial, falla de lectura y eliminación de una tabla después de conmutar. |
| Respaldo nuevo de 8086, migración de históricos y regresión visual del candidato estricto | **NO EJECUTADO.** SSH al host documentado devolvió `Network is unreachable`. La [aceptación anterior de 8086](ACEPTACION_TECNICA_8086.md) corresponde a la aplicación con PDM apagado y con tablas adicionales. |

## Dependencias del modelo ampliado

Las 36 tablas base se enumeran individualmente en [la auditoría estructural](AUDITORIA_36_TABLAS_POWERDESIGNER.md). El inventario de la instalación ampliada distingue los objetos siguientes.

| Grupo | Tablas |
| --- | --- |
| Extensiones PDM (8) | `coordinador_zona`, `documentos_solicitud`, `formacion_solicitud`, `historico_certificado_importacion`, `integracion_control`, `seleccion_certificacion`, `solicitud_refrendo`, `zona` |
| Compatibilidad (5) | `alias_entidad`, `mapa_comunicacion`, `mapa_documento`, `mapa_estado_solicitud`, `parametro` |
| Laravel y operación (9) | `cache`, `cache_locks`, `failed_jobs`, `job_batches`, `jobs`, `migrations`, `password_reset_tokens`, `sessions`, `users` |
| Laravel y expedientes (17) | `certificados_historicos`, `coordinador_zona`, `datos_refrendo`, `datos_solicitantes`, `documentos_probatorios`, `documentos_probatorios_solicitud`, `formaciones_academicas`, `formaciones_academicas_solicitud`, `historial_documentos_academicos`, `instituciones_afiliadas`, `privilegios`, `revisiones_documentales`, `rol_privilegio`, `roles`, `selecciones_certificacion`, `solicitudes_certificacion`, `zonas` |

Hay también columnas agregadas en tablas originales. Por ejemplo, las extensiones incorporan autorización de datos en `persona` y vinculan la formación a la solicitud mediante `persona_estudio_profesional.pees_id_solicitud`. El modelo aprobado vincula ese estudio a la persona, pero carece de la clave a una solicitud y de una distinción de etapa académica por expediente. Las revisiones documentales conservan versiones y observaciones en `public.revisiones_documentales`. El código de autorización regional usa la asignación coordinador-zona que la referencia de 36 tablas no define. Estos datos requieren una representación explícita para mantener equivalencia.

El guard de Laravel usa `App\Models\User`; el login además consulta `solicitudes_certificacion` para elegir la siguiente pantalla. Los modos principal y exclusivo tampoco permiten retirar directamente la extensión. `ModoPdm` exige `integracion_control` y `anfeca_compat`, y los disparadores de compatibilidad dependen de `public.users`. Cambiar solo el modelo autenticable dejaría esos contratos y campos pendientes. La decisión D-11 para los expedientes de 8086 todavía requiere los datos oficiales de tres instituciones documentados en el PR #17.

## Compuerta para conmutar

```bash
bash scripts/anfeca/pruebas/verificar_modelo_fisico.sh referencia_pdm candidato nueva-evidencia-privada
```

Definir ambos servicios en un archivo privado de libpq. Instalar previamente la referencia con el archivo cuyo SHA-256 se verificó arriba. El comparador solo lee catálogos y vuelca DDL sin filas. Inventaría todas las tablas físicas de usuario y devuelve 1 si el catálogo o el DDL difieren. Mantener la evidencia de entornos reales fuera de GitHub.

1. Para 8086, verificar desde el pool efectivo el clúster 5433 y el release activo. Respaldar la base efectiva, verificar su restauración en un clúster independiente y obtener el manifiesto de documentos privados. Conservar el release, el VirtualHost, la base activa y el respaldo para reversión.
2. En la restauración, definir el destino de cada dato sin representación en el modelo. Si se admite un almacén auxiliar explícito, provisionarlo por ambiente fuera de `certificacion_anfeca` y adaptar los repositorios y transacciones con pruebas. Si también se prohíbe un almacén externo, hace falta resolver el contrato de persistencia para preservar esos datos y funciones. No se implementó una reasignación implícita a columnas con otro significado.
3. Exigir coincidencia física, autenticación real, flujos de certificación, refrendo, formación, documentos y Coordinación con sus reglas, huellas de expedientes y archivos y regresión visual. Ensayar reversión cuando existen escrituras posteriores. Si las huellas faltan o cambian, mantener el servicio en mantenimiento hasta conciliar.
4. Para 8008, [el inventario previo](DESPLIEGUE_8008_DESDE_8086_VALIDADO.md) registró un prototipo con cambios locales, base de origen `bd_anfeca` en 5432 y ausencia de VirtualHost. Confirmar el alcance funcional, preservar sus cambios, respaldar y restaurar ese origen y probar su reversión. Usar un clúster, un pool, un socket y un almacén privado distintos de 8086. La validación física y funcional se repite sobre su propio candidato, sin copiar expedientes de 8086.

El candidato estricto todavía no es apto para conmutar. Mantener el runtime que ya presta servicio a 8086 hasta validar un sustituto y conservar el estado actual de 8008 hasta comprobar su origen y reversión.
