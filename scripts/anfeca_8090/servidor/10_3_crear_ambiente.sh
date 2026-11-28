#!/bin/bash
# =====================================================================================================================
# ANFECA 8090 · FASE 10.3 · AMBIENTE INDEPENDIENTE /var/www/ANFECA_8090 en Apache :8090 (AlmaLinux 9, nativo).
# Requiere la base certificacion_anfeca_8090 y el rol anfeca_8090 creados en 10.2. NO crea tablas, no ejecuta
# migraciones, seeders ni anfeca:catalogos; no toca 8086, 8088, 8008 ni producción (se detiene si algo ya existe).
# Por omisión SIMULA (imprime cada comando). Para ejecutar:  sudo -E bash 10_3_crear_ambiente.sh --ejecutar
# =====================================================================================================================
set -uo pipefail
EJECUTAR=0; [ "${1:-}" = "--ejecutar" ] && EJECUTAR=1
RAMA="${RAMA:-integracion/anfeca-definitivo-8090}"
REPO="${REPO:-/home/servidorweb/anfeca_8088_codigo}"          # checkout existente: solo se lee su URL de origen
APP="${APP:-/var/www/ANFECA_8090}"
APP8088="${APP8088:-/var/www/ANFECA_8088}"                      # origen de los expedientes (solo lectura)
PUERTO_WEB="${PUERTO_WEB:-8090}"
IP_SERVIDOR="${IP_SERVIDOR:-$(hostname -I 2>/dev/null | awk '{print $1}')}"
RED_PERMITIDA="${RED_PERMITIDA:-}"                               # p. ej. 192.168.7.0/24 (vacío: puerto abierto a la zona)
PG_BIN="${PG_BIN:-/usr/pgsql-18/bin}"; PG_PUERTO="${PG_PUERTO:-5488}"
BD="${BD:-certificacion_anfeca_8090}"; ROL_BD="${ROL_BD:-anfeca_8090}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"; PHP_FPM_BIN="${PHP_FPM_BIN:-/usr/sbin/php-fpm}"
FPM_SERVICIO="php-fpm-anfeca8090"; USUARIO_WEB="${USUARIO_WEB:-apache}"
MANIFIESTO="${MANIFIESTO:-}"                                     # origen_archivos_privados.sha256 del respaldo 10.2
SQL_VIGENTE_SHA256="0d45c625d4cb714a50e4a5b702d8a2317e5b3d8f21d6955ee726e4a647667d6a"
DIR="$(cd "$(dirname "$0")" && pwd)"
paso() { printf '\n==> %s\n' "$*"; }
run()  { printf '    $ %s\n' "$*"; [ "$EJECUTAR" = 1 ] && { eval "$@" || alto "Falló: $*"; }; return 0; }
alto() { printf '\nDETENIDO: %s\n' "$*" >&2; exit 1; }

paso "0. Comprobaciones previas (solo lectura)"
[ "$(id -u)" = 0 ] || [ "$EJECUTAR" = 0 ] || alto "Ejecute con sudo -E."
[ -e "$APP" ] && alto "$APP ya existe: no se sobrescribe."
ss -ltn "( sport = :$PUERTO_WEB )" | grep -q LISTEN && alto "El puerto $PUERTO_WEB ya está en uso."
systemctl list-unit-files | grep -q "^$FPM_SERVICIO" && alto "Ya existe la unidad $FPM_SERVICIO."
[ -e /etc/httpd/conf.d/anfeca-8090.conf ] && alto "Ya existe /etc/httpd/conf.d/anfeca-8090.conf."
"$PHP_BIN" -v | head -1 | grep -q '^PHP 8\.5\.' || alto "Se requiere PHP 8.5 ($PHP_BIN)."
[ -n "$MANIFIESTO" ] && [ -f "$MANIFIESTO" ] || alto "Indique MANIFIESTO=<respaldo 10.2>/origen_archivos_privados.sha256 para copiar y verificar los expedientes."
ORIGEN=$(git -C "$REPO" remote get-url origin) || alto "No se pudo leer el origen de $REPO."
echo "Rama $RAMA desde $ORIGEN · IP $IP_SERVIDOR · base $BD (rol $ROL_BD) en el puerto $PG_PUERTO"
read -rsp "Contraseña del rol $ROL_BD (definida en 10.2): " CLAVE_ROL; echo
PGPASSWORD="$CLAVE_ROL" "$PG_BIN/psql" -X -h 127.0.0.1 -p "$PG_PUERTO" -U "$ROL_BD" -d "$BD" -Atc "select current_database()" | grep -qx "$BD" \
    || alto "No hay conexión con $BD como $ROL_BD (¿se ejecutó 10.2?)."

paso "1. Código de la rama en $APP (directorio nuevo)"
run "git clone --branch '$RAMA' --single-branch '$ORIGEN' '$APP'"
run "git -C '$APP' log -1 --oneline | tee /dev/stderr > /dev/null"
run "cd '$APP' && echo '$SQL_VIGENTE_SHA256  database/sql/oficial/anfeca_modelo_36_tablas_vigente.sql' | sha256sum -c -"

paso "2. Dependencias y compilación (Vite). Requiere salida a packagist, npm y fonts.bunny.net SOLO durante la compilación"
run "cd '$APP' && '$PHP_BIN' \$(command -v composer) install --no-dev --optimize-autoloader --no-interaction"
run "cd '$APP' && npm ci && npm run build && rm -rf node_modules"

paso "3. Configuración independiente (.env propio, APP_KEY propia, cookie, caché, sesiones y logs propios)"
run "install -m 640 -o root -g $USUARIO_WEB '$DIR/env.8090.example' '$APP/.env'"
run "sed -i -e \"s#^APP_URL=.*#APP_URL=http://$IP_SERVIDOR:$PUERTO_WEB#\" -e \"s#^DB_PORT=.*#DB_PORT=$PG_PUERTO#\" -e \"s#^DB_DATABASE=.*#DB_DATABASE=$BD#\" -e \"s#^DB_USERNAME=.*#DB_USERNAME=$ROL_BD#\" '$APP/.env'"
printf '    $ (DB_PASSWORD se escribe en %s sin mostrarse)\n' "$APP/.env"
[ "$EJECUTAR" = 1 ] && { CLAVE_ROL="$CLAVE_ROL" python3 -I -c 'import os, re, sys
p = sys.argv[1]; c = os.environ["CLAVE_ROL"].replace("\\", "\\\\").replace("\"", "\\\"")
s = open(p).read(); open(p, "w").write(re.sub(r"(?m)^DB_PASSWORD=.*$", lambda m: "DB_PASSWORD=\"" + c + "\"", s))' "$APP/.env" || alto "No se escribió DB_PASSWORD."; }
run "cd '$APP' && '$PHP_BIN' artisan key:generate --force"

paso "4. Expedientes: copia independiente desde 8088 y verificación contra el manifiesto de 10.2"
run "install -d -m 2770 -o root -g $USUARIO_WEB '$APP/storage/app/private'"
run "rsync -a '$APP8088/storage/app/private/' '$APP/storage/app/private/'"
run "(cd '$APP/storage/app/private' && sha256sum -c --quiet '$MANIFIESTO') && echo 'Expedientes copiados íntegros'"

paso "5. Permisos y SELinux (storage y bootstrap/cache escribibles solo en 8090)"
run "chown -R root:$USUARIO_WEB '$APP' && chmod -R g+rwX '$APP/storage' '$APP/bootstrap/cache' && chmod 640 '$APP/.env'"
run "semanage fcontext -a -t httpd_sys_content_t '$APP(/.*)?' && semanage fcontext -a -t httpd_sys_rw_content_t '$APP/(storage|bootstrap/cache)(/.*)?' && restorecon -R '$APP'"
run "getsebool httpd_can_network_connect_db | grep -q ' on$' || setsebool -P httpd_can_network_connect_db on"
run "cd '$APP' && sudo -u $USUARIO_WEB '$PHP_BIN' artisan config:cache && sudo -u $USUARIO_WEB '$PHP_BIN' artisan view:cache"

paso "6. PHP-FPM exclusivo ($FPM_SERVICIO, socket /run/anfeca8090/php-fpm.sock)"
run "install -d -o $USUARIO_WEB -m 750 /var/log/anfeca8090 /etc/anfeca8090"
run "sed 's/USUARIO_WEB/$USUARIO_WEB/g; s/GRUPO_WEB/$USUARIO_WEB/g' '$DIR/php-fpm-anfeca8090.conf' > /etc/anfeca8090/php-fpm.conf"
run "install -m 644 '$DIR/anfeca-8090.ini' /etc/anfeca8090/anfeca-8090.ini"
run "sed 's#PHP_FPM_BIN#$PHP_FPM_BIN#' '$DIR/php-fpm-anfeca8090.service' > /etc/systemd/system/$FPM_SERVICIO.service"
run "systemctl daemon-reload && systemctl enable --now $FPM_SERVICIO"

paso "7. Apache :$PUERTO_WEB (VirtualHost propio; los demás sitios no se editan) y firewall"
run "sed 's#RUTA_LOGS#/var/log/httpd#g' '$DIR/anfeca-8090.conf' > /etc/httpd/conf.d/anfeca-8090.conf"
run "semanage port -l | grep -qw $PUERTO_WEB || semanage port -a -t http_port_t -p tcp $PUERTO_WEB"
if [ -n "$RED_PERMITIDA" ]; then
    run "firewall-cmd --permanent --add-rich-rule='rule family=ipv4 source address=$RED_PERMITIDA port port=$PUERTO_WEB protocol=tcp accept' && firewall-cmd --reload"
else
    run "firewall-cmd --permanent --add-port=$PUERTO_WEB/tcp && firewall-cmd --reload"
fi
run "apachectl configtest && systemctl reload httpd"

paso "8. Validación y aislamiento"
run "curl -s -o /dev/null -w 'login → %{http_code} (200)\\n' http://127.0.0.1:$PUERTO_WEB/login"
run "curl -s -o /dev/null -w '.env → %{http_code} (403/404)\\n' http://127.0.0.1:$PUERTO_WEB/.env"
run "curl -s -H 'Accept: application/json' -o /dev/null -w 'sesión → %{http_code}\\n' http://127.0.0.1:$PUERTO_WEB/sesion/estado"
run "cd '$APP' && sudo -u $USUARIO_WEB '$PHP_BIN' artisan anfeca:verificar-esquema; echo \"verificación: código \$? (0 coincide; 2 diferencias pendientes de decisión)\""
run "grep -E '^(APP_URL|DB_DATABASE|DB_USERNAME|SESSION_COOKIE|CACHE_PREFIX|MAIL_MAILER)=' '$APP/.env'"
echo
[ "$EJECUTAR" = 1 ] && echo "Ambiente 8090 creado: http://$IP_SERVIDOR:$PUERTO_WEB" || echo "SIMULACIÓN: no se ejecutó nada. Revise y repita con --ejecutar."
