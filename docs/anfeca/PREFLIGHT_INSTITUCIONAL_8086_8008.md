# Preflight institucional 8086 / 8008 (solo lectura)

## Estado y propósito
Este paso recoge evidencia real antes de autorizar un respaldo/restauración del ensayo 8086 o preparar 8008. El PR #14 ya prueba consistencia de instantánea PostgreSQL en laboratorio; **no hay validación institucional todavía**.

## Ejecución
Desde un checkout de esta rama (fuera de /var/www), en el host que contenga cada instalación:

```bash
bash scripts/anfeca/preflight_institucional.sh
```

No modifica `.env`, cachés, esquemas ni registros, y no ejecuta respaldos. Si los dos sitios están en hosts diferentes, repetir en cada host. Conservar salida solo en un medio institucional autorizado; no subir nombres de host internos, rutas privadas ni inventarios a GitHub.

## Qué debe comprobar el administrador antes de iniciar el respaldo
1. Identidad real de conexión Laravel CLI, HTTP/PHP-FPM y workers de cada ambiente, incluidos DB_URL y caché de configuración. Confirmar la base efectiva de 8086 y la de 8008.
2. Comparar identificador de sistema PostgreSQL, endpoint y rol para distinguir clúster independiente de réplica o conexión compartida. Conservar PDM apagado.
3. Verificar espacio disponible, tamaño de base y datos, y el límite de memoria del contenedor de restauración. **El script actual `respaldo_verificado.sh` reserva un tmpfs de 800 MB**, que puede ser insuficiente para restaurar una base institucional. No ejecutar sin dimensionar y ensayar ese límite en un entorno aislado.
4. Comprobar imágenes Podman locales y compatibilidad efectiva con PostgreSQL y PHP; el script existente cita una imagen PHP 8.5.12, mientras el entorno documentado utiliza PHP 8.4. No dar por hecho que está disponible ni es compatible.
5. Confirmar recursos, acceso de solo lectura y autorización institucional para generar el respaldo; no enviar archivos con datos personales al repositorio.
6. Identificar y verificar respaldos de archivos privados, catálogos, roles y permisos, además del volcado PostgreSQL.

## Puerta de decisión
Si falta cualquiera de las comprobaciones anteriores, detener la restauración institucional y registrar el bloqueo. Los resultados 36/36 tablas y pruebas de los PR #12–#14 corresponden a laboratorio; la copia real y su equivalencia siguen pendientes. No renombrar bases activas, activar PDM ni modificar 8086/8008 desde este paso.
