#!/usr/bin/env bash
# =============================================================================
# ANFECA 8086 · Preparación ADMINISTRATIVA (root) de los servicios EXCLUSIVOS de 8086. No conmuta 8086.
#
#   sudo bash scripts/anfeca/despliegue_8086/01_preparar_servicios_root.sh --verificar   # solo comprobaciones
#   sudo bash scripts/anfeca/despliegue_8086/01_preparar_servicios_root.sh --ejecutar
#
# Hace, en este orden, y se detiene ante el primer error:
#  A. Clúster PostgreSQL 18 exclusivo: traslada el clúster preparado por servidorweb (/home/servidorweb/anfeca_pg8086,
#     puerto 5433, identificador 7712865409902226718) a /var/lib/pgsql/anfeca8086 (contexto SELinux postgresql_db_t),
#     lo ejecuta como el usuario postgres con la unidad anfeca-pg8086.service y verifica que el identificador,
#     los roles y las huellas no cambien. La copia de /home se conserva (no se borra).
#  B. PHP-FPM 8.5.12 exclusivo: imagen localhost/anfeca-php-fpm:8.5.12 cargada en root, unidad
#     anfeca8086-fpm.service (podman, usuario 48:48 = apache, red del anfitrión, 127.0.0.1:19086, logs propios).
#  C. SELinux: puerto 5433 → postgresql_port_t; puertos 19086 y 18087 → http_port_t (sin booleanos globales).
#  D. Release: /var/www/ANFECA_8086/releases/<commit> desde el artefacto verificado (SHA-256), storage enlazado al
#     storage EXISTENTE de /var/www/ANFECA_ENSAYO (los documentos no se copian ni se mueven), .env propio (600).
#  E. VirtualHost CANDIDATO solo en 127.0.0.1:18087 para probar Apache → PHP-FPM 8.5.12 → certificacion_anfeca
#     sin tocar 8086. Recarga «graceful» de httpd (8084, 8085 y 8086 siguen igual) y comprobación inmediata.
# No modifica: pool www, PHP 8.4.27 del sistema, clúster 5432, 8008, 8084, 8085 ni el VirtualHost de 8086.
# =============================================================================
set -euo pipefail
ACCION="${1:-}"; [[ "$ACCION" =~ ^--(verificar|ejecutar)$ ]] || { echo "Use --verificar o --ejecutar" >&2; exit 1; }
COMMIT="${ANFECA_COMMIT:-9431a94}"
ART=/home/servidorweb/anfeca_trabajo/artefactos/anfeca8086_$COMMIT.tar.gz
PG_HOME=/home/servidorweb/anfeca_pg8086; PG_DEST=/var/lib/pgsql/anfeca8086; PGBIN=/usr/pgsql-18/bin
ID_CLUSTER=7712865409902226718; REL_RAIZ=/var/www/ANFECA_8086; REL=$REL_RAIZ/releases/$COMMIT
APP_ANTERIOR=/var/www/ANFECA_ENSAYO; CONF=/etc/anfeca8086; FPM_PUERTO=19086; CAND_PUERTO=18087
fallo() { echo "DETENIDO: $*" >&2; exit 1; }
paso() { echo; echo "== $*"; }
[ "$(id -u)" = 0 ] || fallo "ejecutar con sudo"

paso "Comprobaciones previas"
[ -s "$ART" ] && (cd "$(dirname "$ART")" && sha256sum -c --quiet "$(basename "$ART").sha256") || fallo "artefacto $ART ausente o con SHA-256 distinto"
for p in $FPM_PUERTO $CAND_PUERTO; do ! ss -ltn | grep -q "127.0.0.1:$p " || fallo "puerto $p ocupado"; done
[ ! -e "$PG_DEST" ] || fallo "$PG_DEST ya existe"
[ ! -e "$REL" ] || fallo "$REL ya existe"
sudo -u servidorweb $PGBIN/psql -h $PG_HOME/run -p 5433 -XAtc 'select system_identifier from pg_control_system()' postgres | grep -qx $ID_CLUSTER || fallo "el clúster 5433 no es el esperado"
sudo -u servidorweb podman image exists localhost/anfeca-php-fpm:8.5.12 || fallo "falta la imagen PHP 8.5.12 de servidorweb"
systemctl is-active --quiet httpd php-fpm postgresql-18 || fallo "servicios base no activos"
apachectl configtest 2>&1 | grep -q "Syntax OK" || fallo "configuración de Apache con errores ANTES de empezar"
for u in 8084 8085 8086; do echo "  $u → $(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:$u/)"; done | tee /root/anfeca8086_http_antes.txt
echo "  Comprobaciones previas correctas."; [ "$ACCION" = --ejecutar ] || exit 0

paso "A. Clúster exclusivo como servicio del sistema"
H_ANTES=$(sudo -u servidorweb $PGBIN/psql -h $PG_HOME/run -p 5433 -XAtc "select md5(string_agg(datname||':'||pg_database_size(datname),',' order by datname)) from pg_database" postgres)
sudo -u servidorweb $PGBIN/pg_ctl -D $PG_HOME/data -m fast -w stop
mkdir -p "$PG_DEST"; cp -a "$PG_HOME/data" "$PG_DEST/data"; chown -R postgres:postgres "$PG_DEST"; chmod 700 "$PG_DEST" "$PG_DEST/data"
sed -i -e "s|^unix_socket_directories = .*|unix_socket_directories = '/run/anfeca-pg8086'|" \
       -e "s|^log_directory = .*|log_directory = 'log'|" -e "s|^unix_socket_permissions = .*|unix_socket_permissions = 0770|" "$PG_DEST/data/postgresql.conf"
cat > "$PG_DEST/data/pg_hba.conf" <<'EOF'
# ANFECA 8086 · socket propio (peer: postgres y servidorweb como superusuario del clúster) y loopback con SCRAM.
local   all   postgres                        peer map=anfeca
local   all   servidorweb                     peer map=anfeca
host    all   all          127.0.0.1/32       scram-sha-256
EOF
printf 'anfeca  postgres     servidorweb\nanfeca  servidorweb  servidorweb\n' > "$PG_DEST/data/pg_ident.conf"; chown postgres:postgres "$PG_DEST/data/"pg_*.conf
semanage port -a -t postgresql_port_t -p tcp 5433 2>/dev/null || semanage port -m -t postgresql_port_t -p tcp 5433
restorecon -R "$PG_DEST"
cat > /etc/systemd/system/anfeca-pg8086.service <<EOF
[Unit]
Description=PostgreSQL 18 exclusivo de ANFECA 8086 (127.0.0.1:5433)
After=network-online.target
[Service]
Type=notify
User=postgres
Group=postgres
Environment=PGDATA=$PG_DEST/data
RuntimeDirectory=anfeca-pg8086
RuntimeDirectoryMode=0770
ExecStart=$PGBIN/postgres -D \${PGDATA}
ExecReload=/bin/kill -HUP \$MAINPID
KillMode=mixed
KillSignal=SIGINT
TimeoutSec=0
[Install]
WantedBy=multi-user.target
EOF
usermod -aG postgres servidorweb >/dev/null 2>&1 || true   # acceso al socket 0770 (grupo postgres)
systemctl daemon-reload; systemctl enable --now anfeca-pg8086.service
for i in $(seq 30); do $PGBIN/pg_isready -q -h 127.0.0.1 -p 5433 && break; sleep 1; done
[ "$(sudo -u postgres $PGBIN/psql -h /run/anfeca-pg8086 -p 5433 -U servidorweb -XAtc 'select system_identifier from pg_control_system()' postgres)" = $ID_CLUSTER ] || fallo "identificador distinto tras el traslado"
H_DESPUES=$(sudo -u postgres $PGBIN/psql -h /run/anfeca-pg8086 -p 5433 -U servidorweb -XAtc "select md5(string_agg(datname||':'||pg_database_size(datname),',' order by datname)) from pg_database" postgres)
[ "$H_ANTES" = "$H_DESPUES" ] || fallo "las bases cambiaron durante el traslado (copia original intacta en $PG_HOME)"
echo "  ✔ anfeca-pg8086.service activo; identificador $ID_CLUSTER; bases idénticas. Copia anterior en $PG_HOME (no se borra)."

paso "B. PHP-FPM 8.5.12 exclusivo"
sudo -u servidorweb podman save localhost/anfeca-php-fpm:8.5.12 | podman load -q
mkdir -p "$CONF" /var/log/anfeca8086-fpm; chown 48:48 /var/log/anfeca8086-fpm; chmod 750 /var/log/anfeca8086-fpm
cat > "$CONF/php-fpm.conf" <<EOF
[global]
error_log = /var/log/anfeca8086-fpm/error.log
daemonize = no
[anfeca8086]
listen = 127.0.0.1:$FPM_PUERTO
listen.allowed_clients = 127.0.0.1
pm = dynamic
pm.max_children = 20
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 6
catch_workers_output = yes
clear_env = no
php_admin_value[error_log] = /var/log/anfeca8086-fpm/php-error.log
php_admin_flag[log_errors] = on
php_admin_value[memory_limit] = 512M
php_admin_value[upload_max_filesize] = 20M
php_admin_value[post_max_size] = 25M
php_admin_value[expose_php] = Off
php_admin_value[session.save_path] = $APP_ANTERIOR/storage/framework/sessions
EOF
semanage port -a -t http_port_t -p tcp $FPM_PUERTO 2>/dev/null || semanage port -m -t http_port_t -p tcp $FPM_PUERTO
cat > /etc/systemd/system/anfeca8086-fpm.service <<EOF
[Unit]
Description=PHP-FPM 8.5.12 exclusivo de ANFECA 8086 (127.0.0.1:$FPM_PUERTO)
After=network-online.target anfeca-pg8086.service
[Service]
ExecStartPre=-/usr/bin/podman rm -f anfeca8086-fpm
ExecStart=/usr/bin/podman run --rm --name anfeca8086-fpm --network host --user 48:48 --security-opt label=disable \\
  -v $REL_RAIZ:$REL_RAIZ -v $APP_ANTERIOR/storage:$APP_ANTERIOR/storage -v $CONF/php-fpm.conf:/usr/local/etc/php-fpm.conf:ro \\
  -v /var/log/anfeca8086-fpm:/var/log/anfeca8086-fpm localhost/anfeca-php-fpm:8.5.12 php-fpm -F -y /usr/local/etc/php-fpm.conf
ExecStop=/usr/bin/podman stop -t 20 anfeca8086-fpm
Restart=on-failure
[Install]
WantedBy=multi-user.target
EOF

paso "D. Release versionado"
mkdir -p "$REL_RAIZ/releases"; tar -xzf "$ART" -C "$REL_RAIZ/releases"; [ -d "$REL" ] || fallo "el artefacto no contiene $COMMIT"
ln -sfn "$APP_ANTERIOR/storage" "$REL/storage"
PAPP=$(awk -F'\t' '$1=="cert8086_app"{print $2}' /home/servidorweb/ANFECA_CREDENCIALES/pg8086_certificacion_anfeca.txt)
{ grep -vE '^(DB_[A-Z_]*|APP_URL)=' "$APP_ANTERIOR/.env"
  echo "APP_URL=http://192.168.7.130:8086"; echo "DB_CONNECTION=pgsql"; echo "DB_HOST=127.0.0.1"; echo "DB_PORT=5433"
  echo "DB_DATABASE=certificacion_anfeca"; echo "DB_USERNAME=cert8086_app"; echo "DB_PASSWORD=$PAPP"; echo "ANFECA_PDM_MODO=apagado"; } > "$REL/.env"
unset PAPP; grep -c '^ANFECA_PDM_MODO=apagado' "$REL/.env" >/dev/null
chown -R servidorweb:apache "$REL_RAIZ"; chmod 2750 "$REL_RAIZ" "$REL_RAIZ/releases"; chmod -R g+rX,o-rwx "$REL"; chmod 640 "$REL/.env"
chmod -R g+w "$REL/bootstrap/cache"; restorecon -R "$REL_RAIZ"
ln -sfn "$REL" "$REL_RAIZ/current"
systemctl daemon-reload; systemctl enable --now anfeca8086-fpm.service; sleep 5
ss -ltn | grep -q "127.0.0.1:$FPM_PUERTO " || fallo "PHP-FPM 8.5.12 no escucha en $FPM_PUERTO"

paso "E. VirtualHost candidato (127.0.0.1:$CAND_PUERTO), sin tocar 8086"
semanage port -a -t http_port_t -p tcp $CAND_PUERTO 2>/dev/null || semanage port -m -t http_port_t -p tcp $CAND_PUERTO
cat > /etc/httpd/conf.d/anfeca-8086-candidato.conf <<EOF
# ANFECA 8086 · candidato de prueba (solo loopback). Retirar tras la conmutación.
Listen 127.0.0.1:$CAND_PUERTO
<VirtualHost 127.0.0.1:$CAND_PUERTO>
    DocumentRoot $REL_RAIZ/current/public
    <Directory $REL_RAIZ/current/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require ip 127.0.0.1
    </Directory>
    <FilesMatch \.php\$>
        SetHandler "proxy:fcgi://127.0.0.1:$FPM_PUERTO"
    </FilesMatch>
    ErrorLog /var/log/httpd/anfeca-8086-candidato-error.log
    CustomLog /var/log/httpd/anfeca-8086-candidato-access.log combined
</VirtualHost>
EOF
apachectl configtest 2>&1 | grep -q "Syntax OK" || { rm -f /etc/httpd/conf.d/anfeca-8086-candidato.conf; fallo "configtest falló; candidato retirado"; }
systemctl reload httpd; sleep 2
C=$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:$CAND_PUERTO/login)
AVC=$(ausearch -m avc -ts recent 2>/dev/null | grep -cE 'httpd|php-fpm|postgres' || true)
for u in 8084 8085 8086; do echo "  $u → $(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:$u/)"; done > /root/anfeca8086_http_despues.txt
cmp -s /root/anfeca8086_http_antes.txt /root/anfeca8086_http_despues.txt || fallo "8084/8085/8086 cambiaron su respuesta (ver /root/anfeca8086_http_*.txt)"
[ "$C" = 200 ] || fallo "candidato /login respondió $C (AVC recientes: $AVC). Revisar: ausearch -m avc -ts recent"
echo "  ✔ Candidato Apache → PHP-FPM 8.5.12 → certificacion_anfeca responde 200; 8084/8085/8086 sin cambios; AVC: $AVC"
echo; echo "Preparación terminada. Siguiente: pruebas sobre http://127.0.0.1:$CAND_PUERTO y, en la ventana, 02_conmutar_root.sh."
