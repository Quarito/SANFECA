# Compatibilidad Laravel y PowerDesigner

## Verificación ejecutada

| Elemento | Evidencia en PostgreSQL 18.7 |
| --- | --- |
| Modelos Eloquent | 43 modelos apuntan a las 36 tablas originales y siete extensiones de dominio. La tabla técnica `integracion_control` se consulta desde los servicios. El test verifica la correspondencia exacta de atributos cargables y PK de los 43 modelos. |
| Llaves y relaciones | Se invocaron las relaciones Eloquent y se compararon sus columnas con el inventario de PostgreSQL. Las 47 FK operativas se corresponden con 47 relaciones `belongsTo` y 47 inversas. Hay 35 FK en la referencia y 12 adicionales. Las 47 rechazan un huérfano y se probaron las reglas de borrado y actualización. |
| Llaves compuestas | `comunicacion`, `historico_certificado` y `persona_estudio_profesional` conservan la PK completa y su orden. `PdmModel` agrega sus columnas al recargar, guardar o eliminar una instancia. La prueba usa dos contactos con el mismo valor de secuencia y comprueba que la operación afecta solo a la fila con la PK completa. Los métodos de búsqueda por clave escalar de Eloquent mantienen su interfaz y no identifican inequívocamente una PK compuesta cuando hay IDs repetidos. |
| Escritura y lectura | La suite creó y consultó expedientes, relaciones, certificados y documentos ficticios, incluidas validaciones, restricciones, actualizaciones y reversión. La prueba de acuse comprobó la respuesta PDF. Coordinación consultó el expediente y descargó un documento en modo principal. |
| Migraciones y compatibilidad | La migración de alineación del PR #9 conserva datos en las instalaciones anteriores y rechaza columnas ambiguas. Las extensiones se aplican con una ruta explícita y preservan las definiciones base. Las vistas permiten lecturas y escrituras controladas en principal y exclusivo. Los triggers del esquema de compatibilidad y los de guardia sobre `public` pertenecen a las extensiones. |
| Formación, licenciaturas y posgrados | La extensión `formacion_solicitud` conserva el título específico, origen, clave, versión y la declaración de posgrado incluso cuando el valor es `false`. Las pruebas cubren captura con catálogos ANUIES ficticios, títulos manuales extensos, actualización de F4 y exportación desde exclusivo. Los catálogos JSON instalados en el ambiente institucional requieren una comprobación adicional. |

## Cambios y discrepancias

El PR #9 ya resolvió la discrepancia de nombres entre la integración anterior y el SQL aprobado. Se verificaron `comu_id_persona`, `conv_lugar_entrega`, `conv_evento_entrega`, `soli_art13` y `socu_id_documento` contra el catálogo. La última sigue apuntando a `documento.docu_id_documento`. El PR #11 ya integró la conservación de metadatos de formación en la extensión operativa, la exportación y la corrección de secuencia de roles.

En esta auditoría se endureció `tests/Pdm/EsquemaPdmTest.php`. La comprobación previa de Eloquent solo comprobaba que el nombre de la PK figurara entre las columnas. Ahora compara la PK ordenada, incluidas las columnas adicionales de las compuestas, y contrasta ambos sentidos de las 47 relaciones con el catálogo de PostgreSQL. La distinta cobertura detectaría una PK errónea que aún exista como columna o una FK modelada con columnas equivocadas. La ejecución quedó en verde. Ninguna discrepancia nueva exigió un cambio de código de la aplicación o del DDL.

### Condición para aprobar la capa operativa

La instalación base es idéntica al archivo aprobado. La capa operativa agrega ocho tablas en `anfeca_modelo`, además de columnas, restricciones e índices aditivos sobre 22 tablas originales. También agrega vistas, tablas y funciones en `anfeca_compat` y triggers sobre las tablas Laravel de la copia. Si el entorno debe limitarse a los 36 objetos de tabla y sus definiciones exactas, esa condición corresponde al esquema base únicamente. El inventario operativo requiere aceptación expresa durante la revisión antes de un despliegue. [Inventario y objetos adicionales](AUDITORIA_36_TABLAS_POWERDESIGNER.md)
