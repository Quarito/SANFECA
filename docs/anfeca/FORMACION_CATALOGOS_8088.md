# Formación académica con estudios importados en 8088

## Incompatibilidades y resolución

| Situación | Cambio propuesto |
|---|---|
| Los estudios importados tienen `espr_estado = ACT`. El flujo anterior usaba el tercer carácter como papel y buscaba un estudio por código, nivel y título. | Una selección inequívoca del catálogo reutiliza la fila activa por nombre y nivel, incluso si varía el uso de mayúsculas. El alta enlaza su ID en `persona_estudio_profesional`. Para una fila ACT, Licenciatura corresponde al origen y Especialidad, Maestría y Doctorado al último grado. Las filas anteriores con códigos de tres letras siguen siendo legibles y se mantiene su representación en capturas sin un estudio ACT equivalente. |
| El título importado tiene nombre y nivel. Carece de área ANUIES, clave y versión histórica. | La presentación de una fila ACT reconstruye los metadatos solo si hay una coincidencia única en el JSON autorizado. Si el título ya existe como ACT y su clasificación es ambigua o la captura manual necesita una clasificación distinta, el guardado pide seleccionar una correspondencia catalogada. |
| Una descripción documental requerida estaba ausente. El guardado dependía de que existieran los tipos y estados exactos. | Antes de escribir, el servidor valida los tipos de archivo y estados requeridos. Un catálogo incompleto devuelve un error de campo. El comando `anfeca:catalogos --solo-documentos` agrega los nombres faltantes de `documento` y `estado_documento`, valida una sola fila activa por descripción y se detiene ante inactivos o duplicados. |

Las cargas siguen asociadas a `solicitud_documento` y a su historial en `estado_solicitud_documento`. El código no altera el SQL oficial ni mueve archivos o documentos de otros ambientes.

## Integración controlada

1. En 8088, registrar el commit desplegado, los archivos locales modificados y las banderas, rutas, versiones y huellas de ambos JSON. Guardar una copia del código efectivo y de la configuración privada. Revisar diferencias con esta rama en un directorio separado, incluidas las modificaciones locales y el trabajo de sesiones de Claude.
2. Confirmar que la conexión efectiva de Laravel corresponde a la instancia de 8088, puerto **5488**, base **certificacion_anfeca** y esquema **public**. Con una conexión de solo lectura, verificar la distribución de niveles y estados, y verificar las descripciones documentales exactas y sus estados:

   ```sql
   SELECT n.nipr_nivel_profesional, e.espr_estado, COUNT(*)
   FROM estudio_profesional e
   JOIN nivel_profesional n ON n.nipr_id_nivel_profesional = e.espr_id_nivel_profesional
   GROUP BY 1, 2 ORDER BY 1, 2;

   SELECT docu_id_documento, docu_descripcion, docu_estatus
   FROM documento ORDER BY docu_descripcion, docu_id_documento;

   SELECT esdo_id_estado_documento, esdo_estado_documento, esdo_estado
   FROM estado_documento ORDER BY esdo_estado_documento, esdo_id_estado_documento;
   ```
3. Tras revisar el impacto y autorizar la intervención, sacar respaldo verificable de 8088 y probar en una copia con los mismos catálogos. Si faltan descripciones y todas las existentes son coherentes, ejecutar desde el release candidato y con la conexión 8088 comprobada:

   ```bash
   php artisan anfeca:catalogos --solo-documentos
   ```

   Una segunda ejecución debe insertar cero filas. Cada fila nueva es dato de catálogo. El comando rechaza `--convocatoria-anio` y conserva las fechas existentes. Una descripción inactiva o duplicada requiere validar el catálogo con Coordinación.
4. Validar con cuentas ficticias desde cero. Comparar IDs y conteos de los títulos ACT, comprobar la autorización FA, los probatorios DP, el compromiso CA y el envío EN antes de aceptar el acuse PDF. El guion `e2e_catalogos_8088.py` incluye las comprobaciones ACT y de ausencia de un tipo documental en el laboratorio temporal, con datos ficticios. El SQL oficial conserva sus 36 tablas.

## Reversión y pendientes

- Detener pruebas de escritura ante una discrepancia y restaurar el release y configuración previos preservando los cambios locales. Conservar las filas del catálogo y sus relaciones. El código anterior ignora los vínculos ACT y puede ocultar formación ya guardada. Si los ensayos ya crearon vínculos ACT, mantener el candidato en revisión y preparar una corrección hacia delante o validar una restauración de ensayo antes de volver al código anterior.
- Verificar la correspondencia entre los **1,269 títulos reportados** y los JSON efectivos del servidor. La coincidencia implementada ignora mayúsculas y espacios externos. Variantes ortográficas, títulos ambiguos y dos licenciaturas con papeles distintos requieren una decisión de datos. También queda pendiente validar la versión histórica de los metadatos ANUIES, que ACT no persiste, y la formación de refrendo cuando ya existen estudios de la persona.
- 8088 requiere revisión presencial autenticada, conservando su configuración local. Work no tuvo ruta SSH al servidor. La rama de sesiones de Claude, 8086 y 8008 quedaron fuera de los cambios.

## Evidencia de laboratorio

PHP 8.5.12 y PostgreSQL 18.6 nativos, con un clúster recién inicializado y solo datos ficticios. La prueba HTTP del catálogo importado aprobó 10 verificaciones y el escenario de persistencia anterior aprobó 4. Se verificaron los 36 objetos de tabla, la igualdad del volcado estructural antes y después, las filas de convocatoria sin cambios y la idempotencia de la carga documental. No se hizo ninguna intervención en 8088.
