# Reversión de aplicación y base de datos ANFECA

Mantener dos puntos de retorno por ambiente. El primero es la versión previa de la aplicación con su configuración, scheduler y workers. El segundo es la base anterior con su almacenamiento privado y el respaldo verificado previo a la conmutación. Conservar evidencia de identidad y huellas para comprobar que el retorno apunta al origen correcto.

| Estado al detectar la falla | Procedimiento |
| --- | --- |
| Destino aún sin tráfico | Mantener el origen como destino de la aplicación, congelar cambios al candidato y guardar evidencia. |
| Tráfico habilitado solo en lectura | Cerrar acceso al candidato y restaurar la versión y conexión anteriores. Verificar identidad desde CLI, HTTP y workers, y cotejar huellas y documentos. |
| Escrituras posteriores a la conmutación | Suspender escrituras. Identificar qué filas, secuencias y archivos cambiaron. Conciliar y ensayar cómo trasladar esos cambios antes de retornar, con autorización del responsable. Mantener la base candidata y sus archivos hasta que no haya discrepancias. |
| PDM exclusivo en futura etapa autorizada | Exportar a `public` en la misma base con `ANFECA_PDM_AUTORIZAR_EXPORTACION=si php artisan anfeca:pdm-exportar-public --confirmar`, conciliar y verificar equivalencia. Solo después usar `ANFECA_PDM_AUTORIZAR_APAGADO=si php artisan anfeca:pdm-modo apagado` y coordinar la configuración de los procesos. La exportación PDM actúa dentro de una base; el retorno de conexión entre bases es un procedimiento aparte. |

La reversión queda bloqueada si no se puede reconciliar una escritura posterior, si el destino anterior no coincide con su identidad o si hay documentos referenciados sin archivo. Al cerrar la incidencia comprobar otra vez login, consulta del expediente, catálogos, documentos, sesiones y scheduler de ese ambiente. Mantener el esquema PDM aislado para diagnóstico ante un retorno de versión. El ensayo sintético probó exportación, conciliación, equivalencia y vuelta a apagado con cero diferencias. El retorno de conexiones y servicios institucionales queda pendiente de su ventana autorizada. [Evidencia de datos](VALIDACION_INTEGRIDAD_DATOS.md)
