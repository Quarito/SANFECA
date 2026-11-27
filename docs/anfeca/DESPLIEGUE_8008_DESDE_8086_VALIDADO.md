# Implementación independiente de 8008 a partir del patrón validado en 8086

> **Estado: PREPARADO, NO EJECUTADO.** 8008 no se modificó. Todo lo que se cita de 8008 se obtuvo en modo de solo lectura el 2026-11-27, después de aprobar 8086 (`ACEPTACION_TECNICA_8086.md`).

## 0. Diagnóstico real de 8008 (solo lectura)

| Elemento | Hallazgo |
|---|---|
| VirtualHost / proceso | **No existe**: no hay `Listen 8008` en `/etc/httpd`, ningún proceso escucha en 8008 y `APP_URL=http://localhost:8000`. El sitio no está publicado |
| Código | `/var/www/anfeca`, rama `main` `227060e`, 6 cambios locales sin commit (`RegistroController`, `routes/web.php`, una vista eliminada y una migración sin registrar) |
| Aplicación | Prototipo: Laravel 13.37.0 (`php ^8.3`), **2 rutas** (`/` y `probar-tablas`) y un solo controlador (`RegistroController`). No contiene los módulos de 8086 (registro completo, formación, documentos, Coordinación) |
| PHP 8.5.12 | Compatible: en una **copia** del código, `composer check-platform-reqs` correcto, Laravel arranca, `route:list` y PHPUnit 2/2 |
| Base | `bd_anfeca` en el clúster **compartido** 5432 (rol `user_anfeca`), 9 MB, 15 tablas, 7 FK, 6 migraciones |
| Datos | Conteos exactos: `personas`=1, `sessions`=1, `migrations`=6; las demás tablas (`users`, `solicitudes_anfeca`, `solicitudes_certificacion`, `expedientes_digitales`, `documentos_solicitud`, `historial_solicitudes`…) en 0 |
| Documentos | `storage/app` solo contiene `.gitignore`; no hay documentos |

**Consecuencia:** 8008 no es una variante de 8086 con datos propios: es una aplicación distinta y casi vacía. El esquema de 8086 (75 tablas y PDM) no existe en 8008.

## 1. Decisión institucional previa (bloquea la ejecución)

| Opción | Efecto |
|---|---|
| **A. Conservar la aplicación de 8008** (su código y sus 15 tablas) | Solo se actualiza la infraestructura: PHP-FPM 8.5.12 exclusivo, clúster propio y base `certificacion_anfeca` con su propio esquema, más el modelo PowerDesigner si se aprueba. Es la opción literal del encargo |
| B. Desplegar en 8008 el código validado de 8086 | 8008 tendría las funcionalidades de 8086 con una base propia, inicialmente con los datos de 8008 trasladados (1 persona). Sustituye la funcionalidad de 8008: requiere autorización expresa |

Este plan desarrolla la **opción A**. La opción B reutiliza `scripts/anfeca/despliegue_8086/` parametrizado con otra base de origen y sin copiar datos de 8086.

## 2. Componentes reutilizables de 8086

| Componente | Reutilización en 8008 |
|---|---|
| Imagen `localhost/anfeca-php-fpm:8.5.12` (`e7c752f2…`, `php.ini` de producción) | Igual |
| Unidad PHP-FPM con socket UNIX `httpd_var_run_t` (sin booleanos SELinux) | Igual, con su propio socket `/run/anfeca8008-fpm/php-fpm.sock`, su usuario y su directorio de logs |
| Clúster PostgreSQL 18 con `initdb` propio y unidad systemd | Igual, con otro directorio (`/var/lib/pgsql/anfeca8008`), otro puerto (p. ej. 5434 → `postgresql_port_t`) y otro identificador |
| Huella lógica del clúster, `pg_checksums`, respaldo verificado y sincronización por renombre | Igual (`respaldo_verificado.sh bd_anfeca main <commit>` funciona con cualquier base de origen) |
| Conmutación con recuperación automática y reversión | Igual. Para 8008 **no hay VirtualHost previo**: la reversión es retirar el VirtualHost nuevo |
| Regresión visual y funcional | Adaptar a las 2 rutas de 8008 y crear su línea base antes de cambiar nada |

## 3. Procedimiento (opción A) · variables que el administrador comprueba primero

```bash
export APP8008=/var/www/anfeca BD8008=bd_anfeca ROL_ORIGEN=user_anfeca PUERTO_PG=5434 PUERTO_WEB=8008
ss -ltn | grep -E ":($PUERTO_PG|$PUERTO_WEB) " && echo "OCUPADO: elegir otro"     # deben estar libres
git -C $APP8008 status --short; git -C $APP8008 rev-parse HEAD                     # registrar y decidir qué cambios locales se conservan
```

1. **Línea base (servidorweb):** respaldo verificado de `bd_anfeca` con `respaldo_verificado.sh`; copia de `$APP8008` (código, `.env` y cambios locales); conteos exactos por tabla (§0).
2. **Clúster propio (servidorweb, luego root):**
   - `initdb -D /home/servidorweb/anfeca_pg8008/data -U servidorweb --data-checksums --locale=es_MX.UTF-8`, con puerto `$PUERTO_PG` y escucha solo en loopback;
   - roles `cert8008_propietario` y `cert8008_app`, con contraseñas en `~/ANFECA_CREDENCIALES/pg8008_certificacion_anfeca.txt`;
   - `createdb -O cert8008_propietario certificacion_anfeca`;
   - restaurar el respaldo de `bd_anfeca`, comparar huellas y otorgar permisos mínimos.
3. **Servicios (root):** copia de `01_preparar_servicios_root.sh` como `despliegue_8008/01_…` con `PG_DEST=/var/lib/pgsql/anfeca8008`, `anfeca-pg8008.service`, `anfeca8008-fpm.service`, socket `/run/anfeca8008-fpm/php-fpm.sock` y un release desde un artefacto construido con el código de 8008 y PHP 8.5.12. Se mantienen la huella lógica, `pg_checksums` y la reanudación segura.
4. **VirtualHost nuevo (root):** `/etc/httpd/conf.d/anfeca-8008.conf` con `Listen 8008`, `DocumentRoot` del release, `Require ip` de la red autorizada y `SetHandler "proxy:unix:/run/anfeca8008-fpm/php-fpm.sock|fcgi://localhost"`. Además `semanage port -a -t http_port_t -p tcp 8008` si no está etiquetado, `apachectl configtest` y `systemctl reload httpd`.
5. **Pruebas:**
   - identidad FastCGI: `8.5.12 fpm-fcgi 13.37.0 certificacion_anfeca cert8008_app <puerto> 18.6`;
   - `/` y `/probar-tablas`;
   - PHPUnit;
   - comprobar que 8084, 8085 y **8086** responden igual que antes.
6. **Reversión:** retirar `anfeca-8008.conf` y recargar Apache, y detener `anfeca8008-fpm`. `bd_anfeca` no se toca en ningún paso.

## 4. Riesgos y restricciones

- No copiar usuarios, solicitudes ni certificados de 8086 a 8008.
- No compartir el clúster 5433 de 8086 (tendría la misma base `certificacion_anfeca`).
- Los 6 cambios locales de `/var/www/anfeca` deben decidirse antes de construir el artefacto.
- Publicar 8008 hace visible por primera vez una aplicación prototipo: confirmar el alcance de acceso (`Require ip`).
- Los pendientes de PowerDesigner (3 renombres y D-11) aplican también a 8008 si se instala su modelo.
