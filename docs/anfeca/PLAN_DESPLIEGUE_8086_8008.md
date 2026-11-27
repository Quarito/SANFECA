# Despliegue controlado de ANFECA en 8086 y 8008

## Decisión previa

Completar la [auditoría comparativa de servidores](AUDITORIA_SERVIDORES_8086_8008.md). Se exige inventario de conexiones, respaldo propio por ambiente con restauración y huellas verificadas, espacio para conservar ambos destinos y autorización específica. Las instancias primaria/réplica se tratan como una unidad de datos. Cada rol de aplicación debe tener acceso a su propio destino. Confirmar también las rutas privadas, los JSON vigentes, el pool PHP-FPM, cron, colas y cache por ambiente. Mantener PDM apagado durante el traslado.

| Ambiente | Fuente y secuencia |
| --- | --- |
| 8086 | Respaldo de su origen efectivo. Validar primero sobre copia temporal. Aprobar D-11, D-13 y D-14 y comprobar los flujos y retorno a apagado. Preparar destino nuevo del clúster reservado a ensayo. |
| 8008 | Diagnosticar y respaldar su base actual. Comparar sus migraciones con el commit aprobado. Validar sus registros y documentos propios en su copia; resolver divergencias antes de abrir la ventana. Preparar su destino independiente. |

## Comandos para la ventana autorizada

Ejecutar como DBA o responsable de release con los parámetros verificados del ambiente correspondiente. Usar credenciales institucionales por el mecanismo aprobado y conservar archivos e informes en almacenamiento privado. Los siguientes comandos muestran la secuencia para un clúster de destino reservado y una base nueva. Guardar previamente información de locale, roles y ACL del origen. Crear el destino con los mismos parámetros de codificación, proveedor y colación y con propietario confirmado.

```bash
(cd "$DIRECTORIO_RESPALDO" && sha256sum -c base.dump.sha256)
pg_restore --list "$DIRECTORIO_RESPALDO/base.dump" > "$DIRECTORIO_RESPALDO/objeto-restauracion.txt"
psql -XAt -v ON_ERROR_STOP=1 -d postgres -c 'select system_identifier from pg_control_system()'
# Con PGHOST, PGPORT, PGUSER y parámetros regionales verificados para este destino.
createdb --template=template0 --owner="$ROL_PROPIETARIO" --encoding="$ENCODING" --locale-provider="$PROVEEDOR" --locale="$LOCALE" certificacion_anfeca
pg_restore --single-transaction --exit-on-error --no-owner --no-privileges --role="$ROL_PROPIETARIO" -d certificacion_anfeca "$DIRECTORIO_RESPALDO/base.dump"
psql -XAt -v ON_ERROR_STOP=1 -d certificacion_anfeca -c 'select current_database(), current_user'
source scripts/anfeca/huellas_bd.inc.sh
huellas_bd_instantanea psql -d certificacion_anfeca > "$DIRECTORIO_RESPALDO/huellas_destino.txt"
cmp "$DIRECTORIO_RESPALDO/huellas_origen.txt" "$DIRECTORIO_RESPALDO/huellas_destino.txt"
```

Ajustar `createdb` a los atributos del origen. Cuando este use ICU o colaciones diferentes por categoría, incluir las opciones regionales exactas verificadas en la copia. Restaurar permisos mediante grants auditados y comparar estructura, secuencias, huellas y archivos antes de aplicar migraciones. Tras cargar dependencias desde `composer.lock` y compilar con `npm ci --ignore-scripts` y `npm run build`, ejecutar desde el artefacto candidato, con variables de conexión verificadas y PDM apagado.

```bash
export DB_CONNECTION=pgsql DB_DATABASE=certificacion_anfeca DB_URL=''
export ANFECA_PDM_MODO=apagado ANFECA_MODELO_BD_PERMITIDA=certificacion_anfeca
php artisan anfeca:pdm-conexion --no-interaction
php artisan migrate:status --no-interaction
php artisan anfeca:modelo-instalar --no-interaction
# Si falta el esquema base según el diagnóstico.
ANFECA_PDM_AUTORIZAR_INSTALACION=si php artisan anfeca:modelo-instalar --ejecutar
ANFECA_PDM_AUTORIZAR_EXTENSIONES=si php artisan migrate --path=database/migrations_pdm --force --no-interaction
ANFECA_PDM_AUTORIZAR_CATALOGOS=si php artisan db:seed --class='Database\Seeders\Pdm\CatalogosPdmSeeder' --force --no-interaction
php artisan anfeca:modelo-verificar --no-interaction
```

Aplicar únicamente migraciones aprobadas y ensayadas para ese ambiente, incluidas las ordinarias o propuestas que correspondan. Durante la ventana de cada aplicación detener sus escritores, tomar el respaldo final y verificarlo. Conmutar código y configuración una vez validado el destino, regenerar caché de configuración y reiniciar solo los workers y el pool que corresponden. Antes de reabrir escrituras comprobar identidad desde HTTP/CLI y workers, login, consulta de solicitudes existentes, históricos, catálogos y documento privado de prueba. Ejecutar una prueba de escritura ficticia solo si el alcance autorizado de la ventana la contempla. Habilitar primero 8086. Preparar la ventana de 8008 tras aprobar los resultados de 8086 y repetir con sus propios datos. [Reversión](PLAN_REVERSIÓN_BASE_DATOS.md)
