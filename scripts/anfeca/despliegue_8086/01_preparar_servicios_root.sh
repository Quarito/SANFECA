#!/usr/bin/env bash
# =============================================================================
# ANFECA 8086 · Preparación ADMINISTRATIVA (root) de los servicios EXCLUSIVOS de 8086. No conmuta 8086.
#
#   sudo ANFECA_COMMIT=0d3ad60 bash scripts/anfeca/despliegue_8086/01_preparar_servicios_root.sh --verificar
#   sudo ANFECA_COMMIT=0d3ad60 bash scripts/anfeca/despliegue_8086/01_preparar_servicios_root.sh --ejecutar
#
# Fases IDEMPOTENTES (una reejecución tras una falla retoma desde la fase pendiente):
#  A. Clúster PostgreSQL 18 exclusivo: /home/servidorweb/anfeca_pg8086 → /var/lib/pgsql/anfeca8086 (postgresql_db_t),
#     unidad anfeca-pg8086 (usuario postgres, 127.0.0.1:5433 → postgresql_port_t). Ante una falla, el clúster
#     original se vuelve a iniciar. La copia de /home se conserva.
#  B. PHP-FPM 8.5.12 exclusivo (anfeca8086-fpm): imagen localhost/anfeca-php-fpm:8.5.12, usuario 48:48 (apache),
#     socket /run/anfeca8086-fpm/php-fpm.sock (httpd_var_run_t). Apache se conecta por socket UNIX porque la
#     política SELinux vigente solo permite a httpd_t conectarse a puertos http_port_t con booleanos globales
#     desactivados (httpd_can_network_relay / httpd_graceful_shutdown / httpd_can_network_connect), mientras que
#     «allow domain spc_t:unix_stream_socket connectto» es incondicional (verificado con sesearch sobre policy.35).
#  C. Release /var/www/ANFECA_8086/releases/<commit> desde el artefacto (SHA-256, commit y compilación aprobada),
#     storage enlazado al EXISTENTE (los documentos no se copian), .env propio 640.
#  D. VirtualHost CANDIDATO solo en 127.0.0.1:18087 (http_port_t). Si no responde 200, se retira y se registran AVC.
# No modifica: pool www, PHP 8.4.27, clúster 5432, booleanos SELinux, 8008, 8084, 8085 ni el VirtualHost de 8086.
# =============================================================================
set -Eeuo pipefail
ACCION="${1:-}"; [[ "$ACCION" =~ ^--(verificar|ejecutar)$ ]] || { echo "Use --verificar o --ejecutar" >&2; exit 1; }
DIR=$(cd "$(dirname "$0")" && pwd); source "$DIR/comun.inc.sh"
COMMIT="${ANFECA_COMMIT:?indique ANFECA_COMMIT=<commit del artefacto aprobado>}"
ART=/home/servidorweb/anfeca_trabajo/artefactos/anfeca8086_$COMMIT.tar.gz
PG_HOME=/home/servidorweb/anfeca_pg8086; PG_DEST=/var/lib/pgsql/anfeca8086; PGBIN=/usr/pgsql-18/bin
ID_CLUSTER=7712865409902226718; RELV=$REL_RAIZ/releases/$COMMIT
CONF=/etc/anfeca8086; SOCKDIR=$(dirname "$SOCK"); CAND=18087; EV=$EVID/anfeca8086_preparacion; mkdir -m 700 -p "$EV"
paso() { echo; echo "== $*" | tee -a "$EV/bitacora.txt"; }
id_home() { SW $PGBIN/psql -h $PG_HOME/run -p 5433 -XAtc 'select system_identifier from pg_control_system()' postgres 2>/dev/null; }
id_serv() { sudo -u postgres $PGBIN/psql -h /run/anfeca-pg8086 -p 5433 -U servidorweb -XAtc 'select system_identifier from pg_control_system()' postgres 2>/dev/null; }

paso "Comprobaciones previas ($ACCION)"
[ -d "$XDG_RUNTIME_DIR" ] || fallo "falta $XDG_RUNTIME_DIR: ejecute sudo desde una sesión iniciada de servidorweb"
[ -s "$ART" ] && (cd "$(dirname "$ART")" && sha256sum -c --quiet "$(basename "$ART").sha256") || fallo "artefacto ausente o SHA-256 distinto"
tar -xzOf "$ART" "$COMMIT/VERSION_ARTEFACTO" | grep -q "^$COMMIT " || fallo "el artefacto no declara el commit $COMMIT"
tar -xzOf "$ART" "$COMMIT/public/build/manifest.json" | grep -q 'assets/app-ZPlUBWH0.js' || fallo "compilación distinta de la aprobada"
for d in / /var; do [ "$(df -Pm $d | awk 'NR==2{print $4}')" -ge 3072 ] || fallo "menos de 3 GB libres en $d"; done
ss -ltn | grep -q "127.0.0.1:$CAND " && ! [ -f /etc/httpd/conf.d/anfeca-8086-candidato.conf ] && fallo "puerto $CAND ocupado por otro servicio"
ESTADO_PG=nuevo
if systemctl is-active --quiet anfeca-pg8086 && [ "$(id_serv)" = $ID_CLUSTER ]; then ESTADO_PG=servicio
elif [ "$(id_home)" = $ID_CLUSTER ]; then [ ! -e "$PG_DEST" ] || fallo "$PG_DEST existe sin servicio activo: traslado previo incompleto (revisar antes de continuar)"; ESTADO_PG=home
else fallo "el clúster 5433 no responde con el identificador $ID_CLUSTER"; fi
echo "  clúster 5433: $ESTADO_PG"
SW podman image exists localhost/anfeca-php-fpm:8.5.12 || fallo "falta la imagen PHP 8.5.12 de servidorweb"
[ "$(SW podman run --rm localhost/anfeca-php-fpm:8.5.12 php -r 'echo PHP_VERSION, ini_get("display_errors") ? "" : " prod";')" = "8.5.12 prod" ] || fallo "la imagen no es PHP 8.5.12 con php.ini de producción"
systemctl is-active --quiet httpd php-fpm postgresql-18 || fallo "servicios base no activos"
apachectl configtest 2>&1 | grep -q "Syntax OK" || fallo "configuración de Apache con errores ANTES de empezar"
protegidos > "$EV/protegidos_antes.txt"; cat "$EV/protegidos_antes.txt" | sed 's/^/  /'
echo "  Comprobaciones previas correctas."; [ "$ACCION" = --ejecutar ] || exit 0

paso "A. Clúster exclusivo como servicio del sistema"
if [ $ESTADO_PG = home ]; then
    H_ANTES=$(SW $PGBIN/psql -h $PG_HOME/run -p 5433 -XAtc "select md5(string_agg(datname||':'||pg_database_size(datname),',' order by datname)) from pg_database" postgres)
    reiniciar_home() { echo "FALLA en el traslado: se reinicia el clúster original" >&2; systemctl disable --now anfeca-pg8086 2>/dev/null; SW $PGBIN/pg_ctl -D $PG_HOME/data -l $PG_HOME/logs/arranque.log -w start; }
    trap 'reiniciar_home; exit 1' ERR INT TERM
    SW $PGBIN/pg_ctl -D $PG_HOME/data -m fast -w stop
    mkdir -p "$PG_DEST"; cp -a "$PG_HOME/data" "$PG_DEST/data"; chown -R postgres:postgres "$PG_DEST"; chmod 700 "$PG_DEST" "$PG_DEST/data"
    sed -i -e "s|^unix_socket_directories = .*|unix_socket_directories = '/run/anfeca-pg8086'|" \
           -e "s|^log_directory = .*|log_directory = 'log'|" -e "s|^unix_socket_permissions = .*|unix_socket_permissions = 0770|" "$PG_DEST/data/postgresql.conf"
    printf '%s\n' '# ANFECA 8086 · socket propio (peer: postgres y servidorweb → superusuario servidorweb) y loopback con SCRAM.' \
        'local   all   servidorweb                     peer map=anfeca' 'host    all   all          127.0.0.1/32       scram-sha-256' > "$PG_DEST/data/pg_hba.conf"
    printf 'anfeca  postgres     servidorweb\nanfeca  servidorweb  servidorweb\n' > "$PG_DEST/data/pg_ident.conf"; chown postgres:postgres "$PG_DEST/data/"pg_*.conf
    semanage port -l | grep -qE "^postgresql_port_t .*\b5433\b" || semanage port -a -t postgresql_port_t -p tcp 5433
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
    id -nG servidorweb | grep -qw postgres || usermod -aG postgres servidorweb     # socket 0770 del clúster exclusivo
    systemctl daemon-reload; systemctl enable --now anfeca-pg8086.service
    for i in $(seq 30); do $PGBIN/pg_isready -q -h 127.0.0.1 -p 5433 && break; sleep 1; done
    [ "$(id_serv)" = $ID_CLUSTER ]
    H_DESPUES=$(sudo -u postgres $PGBIN/psql -h /run/anfeca-pg8086 -p 5433 -U servidorweb -XAtc "select md5(string_agg(datname||':'||pg_database_size(datname),',' order by datname)) from pg_database" postgres)
    [ "$H_ANTES" = "$H_DESPUES" ]
    trap - ERR INT TERM
fi
echo "  ✔ anfeca-pg8086 activo, identificador $(id_serv). Copia anterior conservada en $PG_HOME."

paso "B. PHP-FPM 8.5.12 exclusivo (socket UNIX)"
sudo -u servidorweb XDG_RUNTIME_DIR="$XDG_RUNTIME_DIR" podman save localhost/anfeca-php-fpm:8.5.12 | podman load -q >/dev/null
mkdir -p "$CONF" /var/log/anfeca8086-fpm; chown 48:48 /var/log/anfeca8086-fpm; chmod 750 /var/log/anfeca8086-fpm
semanage fcontext -l | grep -qF "$SOCKDIR(/.*)?" || semanage fcontext -a -t httpd_var_run_t "$SOCKDIR(/.*)?"
echo "d $SOCKDIR 0750 48 48 -" > /etc/tmpfiles.d/anfeca8086-fpm.conf; systemd-tmpfiles --create /etc/tmpfiles.d/anfeca8086-fpm.conf; restorecon -R "$SOCKDIR"
cat > "$CONF/php-fpm.conf" <<EOF
[global]
error_log = /var/log/anfeca8086-fpm/error.log
daemonize = no
[anfeca8086]
listen = $SOCK
listen.mode = 0660
pm = dynamic
pm.max_children = 20
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 6
catch_workers_output = yes
clear_env = no
php_admin_value[error_log] = /var/log/anfeca8086-fpm/php-error.log
php_admin_flag[log_errors] = on
php_admin_value[expose_php] = Off
EOF
cat > /etc/systemd/system/anfeca8086-fpm.service <<EOF
[Unit]
Description=PHP-FPM 8.5.12 exclusivo de ANFECA 8086 ($SOCK)
After=network-online.target anfeca-pg8086.service
[Service]
ExecStartPre=-/usr/bin/podman rm -f anfeca8086-fpm
ExecStartPre=/usr/bin/systemd-tmpfiles --create /etc/tmpfiles.d/anfeca8086-fpm.conf
ExecStart=/usr/bin/podman run --rm --name anfeca8086-fpm --network host --user 48:48 --security-opt label=disable \\
  -v $REL_RAIZ:$REL_RAIZ:ro -v $APP/storage:$APP/storage -v $SOCKDIR:$SOCKDIR -v $CONF/php-fpm.conf:/usr/local/etc/php-fpm.conf:ro \\
  -v /var/log/anfeca8086-fpm:/var/log/anfeca8086-fpm -v $REL_RAIZ/cache:$REL_RAIZ/cache localhost/anfeca-php-fpm:8.5.12 php-fpm -F -y /usr/local/etc/php-fpm.conf
ExecStop=/usr/bin/podman stop -t 20 anfeca8086-fpm
Restart=on-failure
[Install]
WantedBy=multi-user.target
EOF

paso "C. Release versionado"
if [ -d "$RELV" ]; then grep -q "^$COMMIT " "$RELV/VERSION_ARTEFACTO" || fallo "$RELV existe con otro contenido"; echo "  release existente verificado"
else mkdir -p "$REL_RAIZ/releases"; tar -xzf "$ART" -C "$REL_RAIZ/releases"; fi
ln -sfn "$APP/storage" "$RELV/storage"
if [ ! -L "$RELV/bootstrap/cache" ]; then   # caché escribible fuera del release de solo lectura (conserva packages/services)
    mkdir -p "$REL_RAIZ/cache/$COMMIT"; cp -a "$RELV/bootstrap/cache/." "$REL_RAIZ/cache/$COMMIT/"
    mv "$RELV/bootstrap/cache" "$RELV/bootstrap/cache.artefacto"; ln -s "$REL_RAIZ/cache/$COMMIT" "$RELV/bootstrap/cache"
fi
PAPP=$(awk -F'\t' '$1=="cert8086_app"{print $2}' /home/servidorweb/ANFECA_CREDENCIALES/pg8086_certificacion_anfeca.txt)
{ grep -vE '^(DB_[A-Z_]*|APP_URL|ANFECA_PDM_MODO)=' "$APP/.env"
  echo "APP_URL=http://192.168.7.130:8086"; echo "DB_CONNECTION=pgsql"; echo "DB_HOST=127.0.0.1"; echo "DB_PORT=5433"
  echo "DB_DATABASE=certificacion_anfeca"; echo "DB_USERNAME=cert8086_app"; echo "DB_PASSWORD=$PAPP"; echo "ANFECA_PDM_MODO=apagado"; } > "$RELV/.env.nuevo"
unset PAPP; mv "$RELV/.env.nuevo" "$RELV/.env"
chown -R servidorweb:apache "$REL_RAIZ"; chmod 2750 "$REL_RAIZ" "$REL_RAIZ/releases"; chmod -R g+rX,o-rwx "$RELV"; chmod 640 "$RELV/.env"
chown -R 48:48 "$REL_RAIZ/cache"; chmod -R 2770 "$REL_RAIZ/cache"; restorecon -R "$REL_RAIZ"
ln -sfn "$RELV" "$REL_RAIZ/current"
systemctl daemon-reload; systemctl enable anfeca8086-fpm.service; systemctl restart anfeca8086-fpm.service
for i in $(seq 30); do [ -S "$SOCK" ] && break; sleep 1; done; [ -S "$SOCK" ] || fallo "PHP-FPM 8.5.12 no creó $SOCK"
podman exec anfeca8086-fpm sh -c "printf '<?php echo PHP_VERSION,\" \",PHP_SAPI;' > /tmp/v.php; SCRIPT_FILENAME=/tmp/v.php REQUEST_METHOD=GET cgi-fcgi -bind -connect $SOCK | tail -1" | tee "$EV/fpm_version.txt"; echo
grep -qx '8.5.12 fpm-fcgi' "$EV/fpm_version.txt" || fallo "el pool no responde como PHP-FPM 8.5.12"

paso "D. VirtualHost candidato (127.0.0.1:$CAND), sin tocar 8086"
semanage port -l | grep -qE "^http_port_t .*\b$CAND\b" || semanage port -a -t http_port_t -p tcp $CAND
cat > /etc/httpd/conf.d/anfeca-8086-candidato.conf <<EOF
# ANFECA 8086 · candidato de prueba (solo loopback). Retirar tras la conmutación.
Listen 127.0.0.1:$CAND
<VirtualHost 127.0.0.1:$CAND>
    DocumentRoot $REL_RAIZ/current/public
    <Directory $REL_RAIZ/current/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require ip 127.0.0.1
    </Directory>
    <FilesMatch \.php\$>
        SetHandler "proxy:unix:$SOCK|fcgi://localhost"
    </FilesMatch>
    ErrorLog /var/log/httpd/anfeca-8086-candidato-error.log
    CustomLog /var/log/httpd/anfeca-8086-candidato-access.log combined
</VirtualHost>
EOF
retirar_candidato() { rm -f /etc/httpd/conf.d/anfeca-8086-candidato.conf; apachectl configtest >/dev/null 2>&1 && systemctl reload httpd; }
apachectl configtest 2>&1 | grep -q "Syntax OK" || { retirar_candidato; fallo "configtest falló; candidato retirado"; }
INICIO_AVC=$(date '+%m/%d/%Y %H:%M:%S'); systemctl reload httpd; sleep 2
C=$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:$CAND/login)
ausearch -m avc -ts $INICIO_AVC 2>/dev/null > "$EV/avc_candidato.txt" || true
protegidos > "$EV/protegidos_despues.txt"
cmp -s "$EV/protegidos_antes.txt" "$EV/protegidos_despues.txt" || { retirar_candidato; fallo "cambió un ambiente protegido (ver $EV/protegidos_*.txt); candidato retirado"; }
[ "$C" = 200 ] || { retirar_candidato; fallo "candidato /login respondió $C; AVC en $EV/avc_candidato.txt; candidato retirado"; }
echo "  ✔ Apache → PHP-FPM 8.5.12 (socket) → certificacion_anfeca: /login 200; protegidos sin cambios; AVC: $(grep -c avc "$EV/avc_candidato.txt")"
echo; echo "Preparación terminada. Pruebas del candidato: http://127.0.0.1:$CAND (desde el servidor). Ventana: 02_conmutar_root.sh"
