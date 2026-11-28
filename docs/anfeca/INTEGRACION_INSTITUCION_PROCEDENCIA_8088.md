# Institución de Procedencia en 8088

La captura busca un número exacto en `institucion.inst_numero_afiliacion` como texto de hasta siete caracteres. Dos coincidencias requieren revisar el catálogo con Coordinación. Al guardar, Laravel resuelve de nuevo el número y reutiliza la persistencia de `persona.pers_id_institucion`. La pantalla obtiene la institución guardada por esa relación. Antes de autorizar se puede cambiar el número. Después de autorizar se muestra en solo lectura.

| Dato visible | Origen |
|---|---|
| Número de Afiliación | `institucion.inst_numero_afiliacion` |
| Institución | `institucion.inst_nombre` |
| Escuela o Facultad | `institucion.inst_facultad` |
| Campus | «No disponible». El SQL oficial carece de un atributo o relación que lo identifique. |
| Sede | Descripciones de las sedes asociadas por `sede.inst_id_institucion`. Si hay varias, se muestran juntas. |
| Zona ANFECA | `institucion.inst_zona` |

## Preparar la integración

1. Registrar el SHA de 8088 y revisar `git status --short --branch` en `/var/www/ANFECA_8088`. Revisar además `git diff` y `git diff --cached` para los archivos del PR, junto con los archivos no rastreados que coincidan con una ruta nueva. Hacer la revisión en el servidor y conservar las diferencias fuera del repositorio.
2. Desde un clon separado, fijar el SHA aprobado del PR y obtener su parche con `git diff --binary 6c7328686be6fc736f7dec5e44ae20eef24c5aa8 SHA_APROBADO`. Aplicarlo primero con `git apply --check` y después con `git apply` en una copia de trabajo que incluya las modificaciones locales de 8088. Si falla la comprobación, reconciliar los cambios en esa copia y revisar el resultado, en particular `routes/web.php`, `resources/js/app.js` y Formación. Conservar los ajustes locales de catálogo y sesiones.
3. En la copia, compilar con `npm ci && npm run build` y probar con PHP 8.5.12 y PostgreSQL 18.6 sobre una base aislada. Probar una afiliación con ceros, una inexistente y la recuperación tras guardar. Revisar los paneles académicos y el flujo hasta Documentos probatorios. Comprobar que aparece la ruta `registro.formacion.institucion` y que los assets compilados corresponden al código revisado.
4. Antes de publicar en 8088, guardar los archivos locales afectados, `public/build` completo y un inventario de archivos nuevos. Capturar un respaldo verificable de la base 8088 según el procedimiento de `INTEGRACION_36_TABLAS_8088.md`, identificando clúster, base y puerto 5488. Probar la restauración en un clúster independiente. El cambio de código funciona sin migraciones ni actualizaciones de catálogos.
5. Durante la ventana autorizada, publicar juntos el código reconciliado y los assets de la copia validada. Mantener `.env`, almacenamiento privado y configuración de sesiones de 8088. Regenerar el caché de rutas de 8088 si la instancia lo usa. Verificar HTTP, el formulario y la respuesta autenticada de búsqueda antes de cerrar la ventana.

## Reversión

Restaurar los archivos afectados y el conjunto completo de assets previos desde el respaldo del paso 4. Retirar solamente archivos nuevos de esta publicación que figuren en el inventario. Regenerar el caché de rutas de 8088 si estaba habilitado y comprobar el selector anterior en HTTP. El formulario anterior aún puede guardar por identificador y lee la misma FK. La reversión de código conserva las afiliaciones ya seleccionadas. Reservar la restauración de base para un incidente de datos evaluado aparte.
