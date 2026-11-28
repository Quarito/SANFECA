#!/bin/bash
# =====================================================================================================
# ANFECA · Instalación AISLADA del ambiente 8088 en AlmaLinux 9 (nativa: sin Docker, Podman ni contenedores)
#
#   Apache (VirtualHost exclusivo :8088) + PHP-FPM 8.5.12 (pool y servicio exclusivos)
#   + PostgreSQL 18.6 (CLÚSTER NUEVO en su propio puerto y directorio) + base certificacion_anfeca
#   + exclusivamente las 36 tablas del SQL oficial PowerDesigner en el esquema public.
#
# Por omisión SOLO SIMULA (imprime cada comando). Para ejecutar:  sudo ./instalar_8088.sh --ejecutar
# Nunca toca 8086, 8008, producción ni ningún clúster/base existente: si encuentra algo que ya existe, se detiene.
# Las contraseñas se piden de forma interactiva (no se escriben en pantalla, historial ni Git).
# =====================================================================================================
set -euo pipefail

EJECUTAR=0; [ "${1:-}" = "--ejecutar" ] && EJECUTAR=1

# ---------------------------------------------------------------- parámetros (ajustables por entorno)
RAMA="${RAMA:-integracion/anfeca-36-tablas-definitivo}"
REPO="${REPO:-https://github.com/Quarito/SANFECA.git}"
APP="${APP:-/var/www/ANFECA_8088}"
PUERTO_WEB="${PUERTO_WEB:-8088}"
PG_BIN="${PG_BIN:-/usr/pgsql-18/bin}"
PG_DATOS="${PG_DATOS:-/var/lib/pgsql/18/data_anfeca8088}"
PG_PUERTO="${PG_PUERTO:-5488}"
PG_SERVICIO="postgresql-18-anfeca8088"
BD="certificacion_anfeca"
ROL_BD="${ROL_BD:-anfeca_8088}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
PHP_FPM_BIN="${PHP_FPM_BIN:-/usr/sbin/php-fpm}"
FPM_SERVICIO="php-fpm-anfeca8088"
USUARIO_WEB="${USUARIO_WEB:-apache}"
SQL_SHA256="5798ce839af01339018536155ab17698c7cb953c7f150e2b9c6b268aab978285"
# Catálogo SEPOMEX oficial (no se versiona: su licencia prohíbe redistribuirlo). Ruta fuera de public/ y su huella.
SEPOMEX_ARCHIVO="${SEPOMEX_ARCHIVO:-/var/lib/anfeca8088/sepomex/CPdescarga.xls}"
SEPOMEX_SHA256="${SEPOMEX_SHA256:-}"

paso() { printf '\n==> %s\n' "$*"; }
run()  { printf '    $ %s\n' "$*"; [ "$EJECUTAR" = 1 ] && eval "$@" || true; }
alto() { printf '\nDETENIDO: %s\n' "$*" >&2; exit 1; }

# ---------------------------------------------------------------- 0. Comprobaciones de solo lectura
paso "0. Comprobaciones previas (solo lectura)"
grep -q 'AlmaLinux release 9' /etc/redhat-release 2>/dev/null || alto "Se requiere AlmaLinux 9."
[ "$(id -u)" = 0 ] || alto "Ejecute con sudo."
[ -e "$APP" ] && alto "$APP ya existe: no se sobrescribe. Revise su contenido o elija otra ruta (APP=...)."
[ -e "$PG_DATOS" ] && alto "$PG_DATOS ya existe: no se reutiliza ningún clúster."
ss -ltn "( sport = :$PUERTO_WEB )" | grep -q LISTEN && alto "El puerto $PUERTO_WEB ya está en uso."
ss -ltn "( sport = :$PG_PUERTO )" | grep -q LISTEN && alto "El puerto $PG_PUERTO ya está en uso."
systemctl list-unit-files | grep -q "^$PG_SERVICIO" && alto "Ya existe la unidad $PG_SERVICIO."
systemctl list-unit-files | grep -q "^$FPM_SERVICIO" && alto "Ya existe la unidad $FPM_SERVICIO."
[ -e /etc/httpd/conf.d/anfeca-8088.conf ] && alto "Ya existe /etc/httpd/conf.d/anfeca-8088.conf."

"$PG_BIN/postgres" --version | grep -q ' 18\.6' || alto "Se requiere PostgreSQL 18.6 en $PG_BIN (instalado: $("$PG_BIN/postgres" --version 2>&1))."
"$PHP_FPM_BIN" -v | head -1 | grep -q '^PHP 8\.5\.12 ' || alto "Se requiere PHP-FPM 8.5.12 ($PHP_FPM_BIN: $("$PHP_FPM_BIN" -v | head -1))."
"$PHP_BIN" -v | head -1 | grep -q '^PHP 8\.5\.12 ' || alto "Se requiere PHP CLI 8.5.12 ($PHP_BIN)."
for ext in pdo_pgsql pgsql mbstring intl gd zip bcmath; do "$PHP_BIN" -m | grep -qi "^$ext$" || alto "Falta la extensión PHP $ext."; done

echo "    Clústeres PostgreSQL en ejecución (se conservan sin cambios):"
ps -eo pid,user,args | grep -E '[p]ostgres(:| ).*-D' | sed 's/^/      /' || true
ss -ltnp | grep -E 'postgres|postmaster' | sed 's/^/      /' || true
echo "    Sitios Apache existentes (no se modifican):"; httpd -S 2>/dev/null | grep -E 'port|namevhost' | sed 's/^/      /' || true

# ---------------------------------------------------------------- 1. Clúster PostgreSQL 18.6 exclusivo
paso "1. Clúster PostgreSQL 18.6 exclusivo en 127.0.0.1:$PG_PUERTO ($PG_DATOS)"
run "install -d -o postgres -g postgres -m 700 '$PG_DATOS'"
run "sudo -u postgres '$PG_BIN/initdb' -D '$PG_DATOS' -E UTF8 --locale=C.UTF-8 -A scram-sha-256 -U postgres --pwprompt"
run "printf \"port = $PG_PUERTO\\nlisten_addresses = '127.0.0.1'\\nunix_socket_directories = '/run/postgresql-anfeca8088'\\n\" >> '$PG_DATOS/postgresql.conf'"
run "install -d -o postgres -g postgres -m 775 /run/postgresql-anfeca8088"
run "printf 'd /run/postgresql-anfeca8088 0775 postgres postgres -\\n' > /etc/tmpfiles.d/postgresql-anfeca8088.conf"
run "sed -e 's#^Environment=PGDATA=.*#Environment=PGDATA=$PG_DATOS#' -e 's#^Description=.*#Description=PostgreSQL 18.6 EXCLUSIVO de ANFECA 8088#' /usr/lib/systemd/system/postgresql-18.service > /etc/systemd/system/$PG_SERVICIO.service"
run "semanage port -l | grep -qw $PG_PUERTO || semanage port -a -t postgresql_port_t -p tcp $PG_PUERTO"
run "semanage fcontext -a -t postgresql_db_t '$PG_DATOS(/.*)?' && restorecon -R '$PG_DATOS'"
run "systemctl daemon-reload && systemctl enable --now $PG_SERVICIO"

paso "1b. Base $BD (nueva y vacía) y rol de la aplicación"
run "read -rsp 'Contraseña para el rol $ROL_BD: ' CLAVE_ROL; echo"
run "sudo -u postgres '$PG_BIN/psql' -h 127.0.0.1 -p $PG_PUERTO -v ON_ERROR_STOP=1 -c \"create role $ROL_BD login password '\$CLAVE_ROL'\" -c \"create database $BD owner $ROL_BD encoding 'UTF8'\""
run "sudo -u postgres '$PG_BIN/psql' -h 127.0.0.1 -p $PG_PUERTO -d $BD -v ON_ERROR_STOP=1 -c 'revoke create on schema public from public' -c 'alter schema public owner to $ROL_BD'"

# ---------------------------------------------------------------- 2. Aplicación
paso "2. Aplicación en $APP (rama $RAMA)"
run "git clone --branch '$RAMA' --single-branch '$REPO' '$APP'"
run "cd '$APP' && echo '$SQL_SHA256  database/sql/oficial/anfeca_modelo_powerdesigner_certificacion_anfeca_sin_esquema.sql' | sha256sum -c -"

paso "2b. 36 tablas oficiales (SQL aprobado, sin modificaciones) y verificación física"
run "PGPASSWORD=\"\$CLAVE_ROL\" '$PG_BIN/psql' -h 127.0.0.1 -p $PG_PUERTO -U $ROL_BD -d $BD -v ON_ERROR_STOP=1 -f '$APP/database/sql/oficial/anfeca_modelo_powerdesigner_certificacion_anfeca_sin_esquema.sql'"

paso "2c. Dependencias, compilación del frontend existente y configuración"
run "cd '$APP' && '$PHP_BIN' \$(command -v composer) install --no-dev --optimize-autoloader --no-interaction"
run "cd '$APP' && npm ci && npm run build && rm -rf node_modules"
run "install -m 640 -o root -g $USUARIO_WEB '$APP/scripts/anfeca_8088/env.8088.example' '$APP/.env'"
run "sed -i \"s#^DB_PORT=.*#DB_PORT=$PG_PUERTO#; s#^DB_USERNAME=.*#DB_USERNAME=$ROL_BD#; s#^DB_PASSWORD=.*#DB_PASSWORD=\$CLAVE_ROL#\" '$APP/.env'"
run "cd '$APP' && '$PHP_BIN' artisan key:generate --force"
run "chown -R root:$USUARIO_WEB '$APP' && chmod -R g+rwX '$APP/storage' '$APP/bootstrap/cache'"
run "semanage fcontext -a -t httpd_sys_content_t '$APP(/.*)?' && semanage fcontext -a -t httpd_sys_rw_content_t '$APP/(storage|bootstrap/cache)(/.*)?' && restorecon -R '$APP'"
run "setsebool -P httpd_can_network_connect_db on"
run "cd '$APP' && sudo -u $USUARIO_WEB '$PHP_BIN' artisan anfeca:catalogos && sudo -u $USUARIO_WEB '$PHP_BIN' artisan config:cache && sudo -u $USUARIO_WEB '$PHP_BIN' artisan view:cache"
run "cd '$APP' && sudo -u $USUARIO_WEB '$PHP_BIN' artisan anfeca:verificar-esquema"

paso "2d. Catálogo SEPOMEX → entidad_federativa, alcaldia_municipio y colonia (diagnóstico y carga aditiva)"
[ -n "$SEPOMEX_SHA256" ] || echo "    Defina SEPOMEX_SHA256 con la huella verificada de $SEPOMEX_ARCHIVO (sha256sum)."
run "cd '$APP' && sudo -u $USUARIO_WEB '$PHP_BIN' -d memory_limit=1024M artisan anfeca:sepomex '$SEPOMEX_ARCHIVO'"
run "cd '$APP' && sudo -u $USUARIO_WEB '$PHP_BIN' -d memory_limit=1024M artisan anfeca:sepomex '$SEPOMEX_ARCHIVO' --aplicar --base=$BD --sha256=$SEPOMEX_SHA256"
run "cd '$APP' && sudo -u $USUARIO_WEB '$PHP_BIN' -d memory_limit=1024M artisan anfeca:sepomex '$SEPOMEX_ARCHIVO' | grep -E 'nuev'"

# ---------------------------------------------------------------- 3. PHP-FPM 8.5.12 exclusivo
paso "3. PHP-FPM 8.5.12 exclusivo ($FPM_SERVICIO, socket /run/anfeca8088/php-fpm.sock)"
run "install -d -o $USUARIO_WEB -m 750 /var/log/anfeca8088 /etc/anfeca8088"
run "sed 's/USUARIO_WEB/$USUARIO_WEB/g; s/GRUPO_WEB/$USUARIO_WEB/g' '$APP/scripts/anfeca_8088/servidor/php-fpm-anfeca8088.conf' > /etc/anfeca8088/php-fpm.conf"
run "install -m 644 '$APP/scripts/anfeca_8088/servidor/anfeca-8088.ini' /etc/anfeca8088/anfeca-8088.ini"
run "sed 's#PHP_FPM_BIN#$PHP_FPM_BIN#' '$APP/scripts/anfeca_8088/servidor/php-fpm-anfeca8088.service' > /etc/systemd/system/$FPM_SERVICIO.service"
run "systemctl daemon-reload && systemctl enable --now $FPM_SERVICIO"

# ---------------------------------------------------------------- 4. Apache :8088
paso "4. VirtualHost exclusivo :$PUERTO_WEB (los demás sitios no se editan)"
run "sed 's#RUTA_LOGS#/var/log/httpd#g' '$APP/scripts/anfeca_8088/servidor/anfeca-8088.conf' > /etc/httpd/conf.d/anfeca-8088.conf"
run "semanage port -l | grep -qw $PUERTO_WEB || semanage port -a -t http_port_t -p tcp $PUERTO_WEB"
run "firewall-cmd --permanent --add-port=$PUERTO_WEB/tcp && firewall-cmd --reload"
run "apachectl configtest && systemctl reload httpd"

# ---------------------------------------------------------------- 5. Validación
paso "5. Validación"
run "curl -s -o /dev/null -w '%{http_code}\\n' http://127.0.0.1:$PUERTO_WEB/login"
run "curl -s -o /dev/null -w '.env → %{http_code} (debe ser 403/404)\\n' http://127.0.0.1:$PUERTO_WEB/.env"
echo
[ "$EJECUTAR" = 1 ] && echo "Instalación terminada. Siga docs/anfeca/INTEGRACION_36_TABLAS_8088.md, sección «Validación en el servidor»." \
                    || echo "SIMULACIÓN: no se ejecutó nada. Revise los comandos y vuelva a ejecutar con --ejecutar."
