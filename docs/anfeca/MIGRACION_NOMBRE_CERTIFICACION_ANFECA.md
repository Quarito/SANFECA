# Migración al nombre certificacion_anfeca

`certificacion_anfeca` es el nombre canónico en minúsculas. La migración propuesta consiste en restaurar cada origen en una base nueva, validar antes de conmutar y conservar su base anterior como punto de retorno. Se requiere una instancia PostgreSQL independiente por ambiente para usar el mismo nombre físico en ambos. Si 8086 y 8008 comparten clúster, el mismo nombre solo puede identificar una base en ese clúster. Se debe proveer un segundo clúster o autorizar un nombre físico diferenciado para un ambiente. La conmutación queda detenida mientras esa decisión y su aislamiento no estén comprobados.

| Dependencia inventariada | Tratamiento |
| --- | --- |
| Ejemplo de entorno y base permitida del PDM | Documentan `certificacion_anfeca`; SQLite sigue como motor del ejemplo. La configuración real debe fijar explícitamente endpoint, nombre y rol. `DB_URL` puede prevalecer sobre los campos separados. |
| Instalador, extensiones, seed de catálogos, migración, modos y exportación | Escritura protegida por identidad real y autorización por operación. `certificacion_anfeca`, `bd_anfeca_ensayo_pruebas_pg` y `bd_laravelanfeca_pruebas` permanecen protegidas aunque se configure otra base permitida. |
| Ensayo F2, verificación de respaldos y despliegues históricos | El origen 8086 se identifica por defecto como `bd_anfeca_ensayo_pruebas_pg`. Para una base ya homologada o el origen 8008 identificado, `ANFECA_ENSAYO_BD_ORIGEN` indica el nombre exacto para cotejar con el TOC del respaldo. Las ramas y guías históricas que identifican bases de origen conservan esos nombres. |
| Pruebas PostgreSQL, CI y reinicio de demos | Conservan nombres descartables propios y protecciones de ruta y base. Las pruebas que reconstruyen el esquema rechazan el nombre oficial y los institucionales conocidos. |
| `integracion_control.ic_base_datos` y D-11, D-13, D-14 | Comprobar las decisiones contra el origen de cada respaldo. Ajustarlas solo para su copia aprobada. La activación efectiva valida el nombre de la base. Revisar toda fila de control durante la restauración y conservar el modo apagado antes de conmutar. |
| Aplicaciones y procesos | Inventariar Apache/PHP-FPM, workers, scheduler, respaldos, monitoreo, reportes y conexiones externas. Reconfigurar solo servicios del ambiente autorizado y comprobar clientes persistentes. |
| Modelos, SQL, migraciones de negocio | Tablas y vistas se resuelven por esquema en la conexión efectiva. El cambio de nombre no modifica el SQL aprobado ni requiere renombrar tablas. |

La homologación se verificó creando una base desechable con el nombre oficial en PostgreSQL 18.7. El instalador y las extensiones exigieron sus autorizaciones; las escrituras y pruebas funcionales se mantuvieron en clústeres locales temporales. Los roles, permisos y topología institucionales están pendientes. [Plan de despliegue](PLAN_DESPLIEGUE_8086_8008.md)
