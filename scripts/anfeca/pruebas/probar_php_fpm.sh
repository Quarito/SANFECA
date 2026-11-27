#!/usr/bin/env bash
# Desde la raíz de Laravel con una conexión de pruebas PostgreSQL local.
set -euo pipefail
umask 077
[[ -f vendor/autoload.php && -f public/index.php ]] || { echo 'Faltan las dependencias de Laravel.' >&2; exit 1; }
[[ "${DB_CONNECTION:-}" == pgsql && "${DB_DATABASE:-}" == certificacion_anfeca ]] || { echo 'Seleccione la base oficial desechable.' >&2; exit 1; }
[[ "${DB_HOST:-}" == 127.0.0.1 || "${DB_HOST:-}" == ::1 ]] || { echo 'PostgreSQL debe ser local.' >&2; exit 1; }
[[ -z "${DB_URL:-}" ]] || { echo 'Revise DB_URL.' >&2; exit 1; }
[[ "$(php -r 'echo PHP_VERSION;')" == 8.5.12 ]] || { echo 'CLI requiere PHP 8.5.12 exacto.' >&2; exit 1; }

fpm="${ANFECA_FPM_BIN:-php-fpm}"
fcgi="${ANFECA_FCGI_BIN:-cgi-fcgi}"
app="$(pwd -P)"
temporal=$(mktemp -d)
proceso=''
limpiar() {
    if [[ -n "$proceso" ]]; then kill "$proceso" 2>/dev/null || true; wait "$proceso" 2>/dev/null || true; fi
    rm -f "$temporal"/{fpm.conf,fpm.log,fpm.pid,probe.php,probe.out,login.out,fpm.sock}
    rmdir "$temporal"
}
trap limpiar EXIT
export APP_ENV=testing APP_DEBUG=false ANFECA_PDM_MODO=apagado CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync
export APP_CONFIG_CACHE="$temporal/config-sin-cache.php" ANFECA_PRUEBA_RUTA="$app"
escucha="${ANFECA_FPM_LISTEN:-$temporal/fpm.sock}"
[[ "$escucha" == "$temporal/fpm.sock" || "$escucha" =~ ^127\.0\.0\.1:[0-9]{1,5}$ ]] || { echo 'Escucha FastCGI inválida.' >&2; exit 1; }
cat > "$temporal/fpm.conf" <<EOF
[global]
pid = $temporal/fpm.pid
error_log = $temporal/fpm.log
daemonize = no
[smoke]
user = nobody
group = nogroup
listen = $escucha
listen.allowed_clients = 127.0.0.1
pm = static
pm.max_children = 1
clear_env = no
EOF
cat > "$temporal/probe.php" <<'PHP'
<?php
require getenv('ANFECA_PRUEBA_RUTA').'/vendor/autoload.php';
$app = require getenv('ANFECA_PRUEBA_RUTA').'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$checks = [
    PHP_VERSION === '8.5.12',
    PHP_SAPI === 'fpm-fcgi',
    $app->environment('testing'),
    config('anfeca_pdm.modo') === 'apagado',
    in_array('pgsql', PDO::getAvailableDrivers(), true),
    in_array('sqlite', PDO::getAvailableDrivers(), true),
];
foreach (['curl', 'dom', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pgsql', 'simplexml', 'xml', 'xmlreader', 'xmlwriter', 'zip'] as $extension) {
    $checks[] = extension_loaded($extension);
}
$db = $app['db']->connection();
$identidad = $db->selectOne("select current_database() as bd, current_setting('server_version_num')::int / 10000 as mayor");
$checks[] = $identidad->bd === 'certificacion_anfeca' && (int) $identidad->mayor === 18;
$checks[] = (int) $db->table('pg_tables')->where('schemaname', 'anfeca_modelo')->count() >= 36;
if (in_array(false, $checks, true)) {
    http_response_code(500);
    exit('FASTCGI VERIFICACION FALLIDA');
}
echo 'FASTCGI 8.5.12 Laravel PostgreSQL 18 certificacion_anfeca';
PHP
"$fpm" -t -y "$temporal/fpm.conf"
"$fpm" -F -y "$temporal/fpm.conf" &
proceso=$!
solicitar() {
    SCRIPT_FILENAME="$1" SCRIPT_NAME="$2" REQUEST_METHOD=GET REQUEST_URI="$3" \
        DOCUMENT_ROOT="$app/public" SERVER_PROTOCOL=HTTP/1.1 HTTP_HOST=localhost \
        "$fcgi" -bind -connect "$escucha"
}
for _ in {1..30}; do
    kill -0 "$proceso" || { cat "$temporal/fpm.log" >&2; exit 1; }
    if solicitar "$temporal/probe.php" /probe.php /probe.php > "$temporal/probe.out" 2>/dev/null; then break; fi
    sleep 0.2
done
grep -q '^FASTCGI 8.5.12 Laravel PostgreSQL 18 certificacion_anfeca' "$temporal/probe.out"
solicitar "$app/public/index.php" /index.php /login > "$temporal/login.out"
if grep -qE '^Status: [345][0-9]{2}' "$temporal/login.out"; then
    grep '^Status:' "$temporal/login.out" >&2
    exit 1
fi
grep -qi 'content-type: text/html' "$temporal/login.out"
grep -qi '<form' "$temporal/login.out"
echo 'PASÓ: PHP-FPM 8.5.12, extensiones, Laravel sobre certificacion_anfeca y GET /login por FastCGI.'
