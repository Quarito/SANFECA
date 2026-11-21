# ANFECA · Despliegue en el servidor nuevo (AlmaLinux · PHP 8.5.12 · PostgreSQL 18)

Procedimiento por SSH, probado desde ANFECA_ENSAYO (rama `ensayo/principal`).
**No se ejecuta sin autorización expresa.**

Convenciones:
- `<…>` son valores propios del servidor destino.
- `$` indica el usuario de despliegue `<deploy>`, que debe pertenecer al grupo `apache`.
- `#` indica root vía `sudo`.

---

## 0. Qué viaja y qué no

| Viaja | No viaja (nunca) |
|---|---|
| Código del commit aprobado (`git archive` o bundle) | `.env`, credenciales, `APP_KEY` del ensayo |
| `public/build` compilado del mismo commit (paquete) | `storage/app/private/**` (documentos del ensayo), logs, sesiones, cachés |
| `composer.lock`, `package-lock.json` | `vendor/`, `node_modules/` (se reconstruyen) |
| Plantilla `config/anfeca_pdm_decisiones.php` (sin decisiones) | Decisiones PDM, usuarios A/B/C y datos de `bd_anfeca_ensayo_pruebas_pg` |
| | Archivos `*.bak*`, `*.antes_*`, `*.respaldo*`, volcados `.dump` |

Las rutas `/preview/*` solo existen con `APP_ENV=local`; en producción responden 404.

---

## 1. Preparar el paquete en el servidor actual (ensayo)

```bash
$ cd /var/www/ANFECA_ENSAYO
$ C=$(git rev-parse --short HEAD); R=~/anfeca_release_$C; mkdir -p $R
$ git archive --format=tar.gz --prefix=anfeca/ -o $R/anfeca_codigo_$C.tar.gz HEAD
$ git bundle create $R/anfeca_$C.bundle ensayo/principal          # alternativa con historial
$ npx vite build --outDir $R/build --emptyOutDir                   # mismos activos que public/build
$ tar -C $R -czf $R/anfeca_build_$C.tar.gz build && rm -rf $R/build
$ (cd $R && sha256sum anfeca_* > SHA256SUMS && cat SHA256SUMS)
$ scp -r $R <deploy>@<servidor_nuevo>:~/
```

Opción GitHub, solo con autorización:
- En el ensayo: `git push -u github ensayo/principal`.
- En el destino: `git clone --branch ensayo/principal git@github.com:Quarito/SANFECA.git`, y después `git checkout <commit>`.

En el destino, verificar siempre el paquete:

```bash
$ cd ~/anfeca_release_<commit> && sha256sum -c SHA256SUMS
```

---

## 2. Sistema base: PHP 8.5.12 y extensiones

Extensiones requeridas (validadas con `composer check-platform-reqs --lock` en PHP 8.5.12):
bcmath, ctype, curl, dom, fileinfo, filter, gd (con freetype y jpeg), iconv, intl, mbstring, openssl,
pcntl, pdo_pgsql, pgsql, session, sockets, tokenizer, xml, xmlreader, xmlwriter, zip y zlib.
`pdo_sqlite` solo es necesaria para ejecutar las pruebas.

```bash
# php -v                       # debe indicar 8.5.12 en CLI
# php-fpm -v                   # y en FPM
# php -m | sort > /tmp/modulos.txt
# for e in bcmath ctype curl dom fileinfo filter gd iconv intl mbstring openssl pcntl pdo_pgsql pgsql session sockets tokenizer xml xmlreader xmlwriter zip zlib; do grep -qix "$e" /tmp/modulos.txt || echo "FALTA $e"; done
```

PHP-FPM: el acuse PDF (dompdf + gd) requiere **512 MB**. Con 128 MB agota la memoria.

```bash
# cat > /etc/php-fpm.d/anfeca.conf <<'EOF'
[anfeca]
user = apache
group = apache
listen = /run/php-fpm/anfeca.sock
listen.acl_users = apache
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
php_admin_value[memory_limit] = 512M
php_admin_value[upload_max_filesize] = 6M
php_admin_value[post_max_size] = 40M
php_admin_value[expose_php] = Off
php_admin_value[error_log] = /var/log/php-fpm/anfeca-error.log
php_value[session.save_handler] = files
php_value[session.save_path] = /var/lib/php/session
EOF
# php-fpm -t && systemctl reload php-fpm
```

Los límites de carga se derivan de las reglas de la aplicación: archivos de hasta 5 MB y hasta 4 documentos por formulario.

Composer 2.10.x y Node.js 24.x. Node solo hace falta si se compila en el servidor; con el paquete `anfeca_build_*.tar.gz` no es necesario.

---

## 3. PostgreSQL 18

```bash
# sudo -u postgres psql -v ON_ERROR_STOP=1 <<'SQL'
create role <rol_anfeca> login password '<secreto>' nosuperuser nocreatedb nocreaterole;
create database <bd_anfeca> owner <rol_anfeca> encoding 'UTF8' template template0;
\c <bd_anfeca>
alter schema public owner to <rol_anfeca>;
SQL
```

`pg_hba.conf`: solo conexiones locales (`host <bd_anfeca> <rol_anfeca> 127.0.0.1/32 scram-sha-256`).

### 3.a Base NUEVA (recomendado para el arranque)
1. Aplicar las migraciones (§6).
2. Generar `APP_KEY` una sola vez (§5).
3. Importar los catálogos oficiales: instituciones afiliadas y, si corresponde, certificados históricos. Hacerlo con su procedimiento propio y desde fuentes oficiales, **no** desde el ensayo.

### 3.b Base con DATOS existentes
1. Respaldo completo antes de cualquier cambio: `pg_dump -Fc` y tar de `storage/app/private` (§10).
2. Conservar el `APP_KEY` original de esa instalación. **Nunca** regenerarlo: invalidaría sesiones, cookies, enlaces firmados de restablecimiento y cualquier dato cifrado. La aplicación no cifra columnas hoy, pero no se asume.
3. Revisar `php artisan migrate:status`.
   - La migración `2026_11_20_190000_add_escuela_facultad…` es idempotente: si la columna existe, no hace nada.
   - Su `down()` no es destructivo.
4. Aplicar `php artisan migrate --force` en una ventana de mantenimiento (`artisan down`).

---

## 4. Código, dependencias y activos

```bash
# install -d -o <deploy> -g apache -m 2755 /var/www/anfeca
$ tar -xzf ~/anfeca_release_<commit>/anfeca_codigo_<commit>.tar.gz -C /var/www/anfeca --strip-components=1
$ tar -xzf ~/anfeca_release_<commit>/anfeca_build_<commit>.tar.gz -C /var/www/anfeca/public
$ cd /var/www/anfeca
$ composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
$ composer check-platform-reqs --lock
# Alternativa a anfeca_build: npm ci && npx vite build && rm -rf node_modules
$ test -f public/build/manifest.json && echo "activos listos"
```

---

## 5. Entorno (.env), APP_KEY y correo

```bash
$ cp .env.example .env
# chown <deploy>:apache .env && chmod 640 .env
```

Valores mínimos:

```
APP_NAME=ANFECA
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<dominio>
LOG_CHANNEL=single
LOG_LEVEL=warning
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=<bd_anfeca>
DB_USERNAME=<rol_anfeca>
DB_PASSWORD=<secreto>
DB_URL=
ANFECA_PDM_MODO=apagado
CACHE_STORE=file
SESSION_DRIVER=file
SESSION_COOKIE=anfeca_session
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
MAIL_MAILER=smtp
MAIL_HOST=<smtp>
MAIL_PORT=587
MAIL_SCHEME=<smtp|smtps>
MAIL_USERNAME=<usuario>
MAIL_PASSWORD=<secreto>
MAIL_FROM_ADDRESS=<no-responder@dominio>
MAIL_FROM_NAME="ANFECA"
```

Notas:
- Usar `SESSION_SECURE_COOKIE=true` solo si el sitio se sirve por HTTPS.
- `APP_KEY`:
  - Base **nueva**: `php artisan key:generate --force`, una sola vez, y guardar la clave en el gestor de secretos.
  - Base **con datos**: copiar la clave original; no ejecutar `key:generate`.
- Correo: la recuperación de contraseña lo usa. Prueba:
  `php artisan tinker --execute 'Mail::raw("Prueba ANFECA", fn($m) => $m->to("<cuenta_de_prueba>")->subject("Prueba"));'`

---

## 6. Migraciones (orden)

```bash
$ php artisan migrate:status
$ php artisan migrate --force          # 35 migraciones de database/migrations, en orden cronológico
$ php artisan migrate:status | grep -c Ran
```

- La última migración es `2026_11_20_190000_add_escuela_facultad_to_instituciones_afiliadas`. Es el único campo institucional nuevo.
- `direccion` no forma parte del despliegue.
- `database/migrations_pdm` **no** se ejecuta mientras `ANFECA_PDM_MODO=apagado`. Si después se aprueba el PDM, el orden es:
  1. `ANFECA_MODELO_BD_PERMITIDA=<bd_anfeca> php artisan anfeca:modelo-instalar --ejecutar --confirmar`
  2. `php artisan migrate --path=database/migrations_pdm --force`
  3. `php artisan db:seed --class='Database\Seeders\Pdm\CatalogosPdmSeeder' --force`
  4. Registrar en `config/anfeca_pdm_decisiones.php` la base (`base_de_datos`) y sus decisiones confirmadas. Las del ensayo no son válidas en otra base.

---

## 7. Apache, permisos, SELinux y directorios privados

```bash
# cat > /etc/httpd/conf.d/anfeca.conf <<'EOF'
<VirtualHost *:443>
    ServerName <dominio>
    DocumentRoot /var/www/anfeca/public
    DirectoryIndex index.php
    <Directory /var/www/anfeca/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php-fpm/anfeca.sock|fcgi://localhost"
    </FilesMatch>
    SSLEngine on
    SSLCertificateFile <cert>
    SSLCertificateKeyFile <key>
    ErrorLog /var/log/httpd/anfeca-error.log
    CustomLog /var/log/httpd/anfeca-access.log combined
</VirtualHost>
EOF
# apachectl configtest && systemctl reload httpd
```

Permisos: solo las rutas que necesitan escritura, sin 777 y sin `chown -R` sobre el árbol completo.

```bash
# cd /var/www/anfeca
# for d in storage storage/app storage/framework storage/framework/cache; do install -d -o <deploy> -g apache -m 2775 "$d"; done
# for d in storage/app/public storage/app/private storage/framework/cache/data storage/framework/cache/locks \
           storage/framework/sessions storage/framework/testing storage/logs bootstrap/cache; do install -d -o <deploy> -g apache -m 2770 "$d"; done
# install -d -o apache -g apache -m 2775 storage/framework/views
# semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/anfeca/storage(/.*)?'
# semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/anfeca/bootstrap/cache(/.*)?'
# restorecon -Rv /var/www/anfeca/storage /var/www/anfeca/bootstrap/cache
# setsebool -P httpd_can_network_connect_db on      # PHP-FPM → PostgreSQL por TCP
# setsebool -P httpd_can_sendmail on                # o httpd_can_network_connect si el SMTP es remoto
```

- `storage/app/private` guarda los documentos de los solicitantes. Solo pueden leerlo `apache` y el grupo; nunca queda bajo `public/`.
- Logs y caché se crean con permiso `0664` (`config/logging.php`, `config/cache.php`).

---

## 8. Cachés: SIEMPRE como `apache`

`BladeCompiler` fija la fecha de las vistas compiladas con `touch()`, y eso exige ser su propietario. Si las compila otro usuario, aparece `touch(): Utime failed` y la página responde con error 500.

```bash
# sudo -u apache php artisan optimize:clear
# sudo -u apache php artisan config:cache
# sudo -u apache php artisan route:cache
# sudo -u apache php artisan view:cache
# sudo -u apache php artisan event:cache
```

Si alguna vez se mezclan propietarios: `sudo -u apache php artisan view:clear`.

---

## 9. Verificaciones

```bash
$ B=https://<dominio>
$ for u in /login /registro/datos-personales /recuperar; do curl -s -o /dev/null -w "$u %{http_code}\n" $B$u; done           # 200
$ for u in /preview/datos /preview/compromiso; do curl -s -o /dev/null -w "$u %{http_code}\n" $B$u; done                    # 404
$ for u in /registro/certificacion /registro/compromiso /registro/completado; do curl -s -o /dev/null -w "$u %{http_code}\n" $B$u; done  # 302
# sudo -u apache php artisan route:list --path=preview          # sin rutas
# sudo -u apache php -r 'echo ini_get("memory_limit"), PHP_EOL;'   # CLI; FPM según el pool (512M)
$ grep -c "production.ERROR" storage/logs/laravel.log           # sin errores nuevos
```

Recorrido funcional con una cuenta de prueba, que se elimina después con su procedimiento:

1. **Registro → Datos personales → Autorización.** Tras autorizar, los datos ya no se pueden editar.
2. **Certificación inicial → Formación académica** (4 documentos) → Autorizar. El usuario regresa a Formación con el mensaje "autorizados" y el botón Siguiente.
3. **Documentos probatorios → Guardar.** Quedan bloqueados: solo Ver y Siguiente.
4. **Aceptación y compromiso.** La casilla es obligatoria: sin marcarla, el servidor rechaza el envío.
5. **Finalización → Confirmar envío → Acuse PDF.** El PDF debe descargarse; si falla, revisar `memory_limit`.
6. **Refrendo** con un certificado histórico oficial: los datos de refrendo (ordinario o artículo 14) llevan a Formación obligatoria, luego Documentos (los del artículo 14 cuando corresponda), Aceptación, Envío y Acuse.
7. **Acceso cruzado:** con otra cuenta, el acuse o los documentos ajenos deben responder 404 o redirigir.

Pruebas automatizadas (opcional; solo SQLite en memoria; nunca con `RefreshDatabase` sobre la base real):

```bash
$ composer install --dev && php -d memory_limit=512M vendor/bin/phpunit -c phpunit.xml && composer install --no-dev --optimize-autoloader
```

---

## 10. Copias de seguridad y rollback

Antes de cada despliegue:

```bash
# T=$(date +%F_%H%M)
# sudo -u postgres pg_dump -Fc -d <bd_anfeca> -f /var/backups/anfeca/bd_$T.dump
# tar -C /var/www/anfeca -czf /var/backups/anfeca/private_$T.tgz storage/app/private
# tar -C /var/www -czf /var/backups/anfeca/codigo_$T.tgz --exclude=anfeca/storage --exclude=anfeca/vendor anfeca
# cp -p /var/www/anfeca/.env /var/backups/anfeca/env_$T && chmod 600 /var/backups/anfeca/env_$T
```

Rollback:

1. `sudo -u apache php artisan down`
2. Restaurar el código: `tar -xzf /var/backups/anfeca/codigo_<T>.tgz -C /var/www`. Después, `composer install --no-dev` para el `composer.lock` de esa versión.
3. Restaurar la base si hubo migraciones o datos: `sudo -u postgres pg_restore --clean --if-exists -d <bd_anfeca> /var/backups/anfeca/bd_<T>.dump`.
4. Restaurar los documentos si se alteraron: `tar -xzf /var/backups/anfeca/private_<T>.tgz -C /var/www/anfeca`.
5. Ejecutar como `apache`: `optimize:clear`, `config:cache`, `route:cache` y `view:cache`. Después, `up`.

La migración de `escuela_facultad` no requiere revertir el esquema: su `down()` es intencionalmente no destructivo.

---

## 11. Pendientes ajenos al servidor nuevo (ensayo)

- `direccion` sigue existiendo solo en `bd_anfeca_ensayo_pruebas_pg`. No la usa ningún código, vista ni disparador. Retirarla requiere autorización:
  1. Respaldo con `\copy (select id, direccion …) to 'direccion_respaldo.csv' csv header`.
  2. `alter table public.instituciones_afiliadas drop column direccion;`
- Retirar del repositorio, en un commit de limpieza autorizado, los respaldos rastreados (`respaldos_interfaz/` y `*.antes_*`, `*.respaldo*`, `*.bak2`). No afectan la ejecución.
