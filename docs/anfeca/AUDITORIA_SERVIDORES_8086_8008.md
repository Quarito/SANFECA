# Inventario comparativo de ANFECA en 8086 y 8008

Work no dispone de una conexión autorizada a AlmaLinux ni tiene montadas las instalaciones. La siguiente matriz registra las rutas y la base de origen documentada. Cada dato institucional pendiente debe obtenerse desde la instalación respectiva.

| Elemento | Ensayo 8086 | Servidor 8008 | Evidencia pendiente |
| --- | --- | --- | --- |
| Aplicación | `/var/www/ANFECA_ENSAYO` | `/var/www/anfeca` | Ruta real, VirtualHost, DocumentRoot y puerto |
| Base actual | Origen documentado `bd_anfeca_ensayo_pruebas_pg` | Desconocida | Base real, usuario, privilegios, host, puerto y versión |
| Clúster | Desconocido | Desconocido | `system_identifier`, endpoint efectivo y OID de base |
| AlmaLinux, Apache y PHP-FPM | Pendiente | Pendiente | Binarios, servicios, vhost, socket/pool, usuario y versión |
| PHP CLI, Laravel y Git | Pendiente | Pendiente | Versiones, HEAD, rama y cambios locales |
| SELinux, almacenamiento libre y documentos privados | Pendiente | Pendiente | Estado, contextos, ruta, permisos y capacidad |
| Sesiones, caché, colas, cron y Scheduler | Pendiente | Pendiente | Drivers, procesos consumidores, timers y tareas |
| Esquema, migraciones y PDM | Pendiente | Pendiente | Catálogo, migraciones ejecutadas y pendientes, modo configurado y registrado |
| Catálogos académicos y documentos | Pendiente | Pendiente | Bandera, versión, SHA-256, permisos y apertura de archivos autorizados |
| Conexiones y trabajos activos | Pendiente | Pendiente | Conteos y categorías sin sentencias SQL ni datos de usuarios |

## Captura de solo lectura desde AlmaLinux

Ejecutar desde el worktree de revisión. Usar la sesión de servicio que ya tiene acceso a la instalación. El script Laravel usa transacción de solo lectura, registra campos seleccionados y omite credenciales, URLs completas y filas. Conservar la salida de forma privada.

```bash
cat /etc/os-release
php -v
httpd -v
apachectl -S
systemctl is-active httpd php-fpm
systemctl list-timers --all --no-pager
getenforce
ss -lnt
for app in /var/www/ANFECA_ENSAYO /var/www/anfeca; do
  git -C "$app" status --short --branch
  git -C "$app" rev-parse HEAD
  df -Ph "$app" "$app/storage"
  ls -ldZ "$app" "$app/storage" "$app/storage/app/private"
  php scripts/anfeca/inventario_servidor.php "$app"
done
```

Con permisos existentes, revisar localmente la asociación VirtualHost → DocumentRoot → pool PHP-FPM y la versión efectiva que atiende HTTP. Contrastar la conexión de CLI con la del pool y workers. Para Scheduler ejecutar `php artisan schedule:list` desde cada instalación en una sesión habilitada para ese cliente. Revisar cron, temporizadores y workers en el servidor antes de compartir evidencias. Registrar solo programa, horario, dueño y aplicación. Para clasificar conexiones, agrupar `pg_stat_activity` por `backend_type` y estado; revisar nombres de clientes localmente y excluir consultas, DSN, variables sensibles y datos personales.

Mismo `system_identifier` exige tratar los destinos como relacionados hasta aclarar si son la misma instancia, réplica o clon. Distintos endpoints por sí solos no prueban independencia. Si el rol Laravel carece de permiso para consultar el identificador, el DBA ejecuta `select system_identifier from pg_control_system();` contra cada endpoint. Verificar que ambos escuchan en la instancia primaria esperada y que cada rol solo alcanza su destino. Comparar las migraciones y las vistas reales por instancia. El conteo agregado de esta herramienta orienta el inventario y la comparación fina se realiza con el procedimiento de auditoría estructural.
