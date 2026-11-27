#!/usr/bin/env bash
#
# ANFECA · Prevalidación de despliegue (servidor con PHP-FPM + Apache + SELinux)
#
# Detecta, ANTES de abrir ANFECA a los usuarios, las condiciones que provocan
# HTTP 500 en el inicio de sesión y en la generación de acuses:
#   - directorios de Laravel inexistentes o sin escritura efectiva para PHP-FPM;
#   - subdirectorios de caché sin bit de ejecución (fallo del 2026-11-21:
#     RateLimiter → fopen()/fileperms() «Permission denied» / «stat failed»);
#   - vistas compiladas de otro propietario («touch(): Utime failed»);
#   - SELinux, ACL, configuración de PHP-FPM y memoria (512M para el acuse PDF).
#
# Seguridad:
#   - No modifica permisos, propietarios, configuración, bases de datos ni servicios.
#   - No lista ni lee documentos privados: de storage/app/private solo comprueba
#     que el directorio exista y sea escribible.
#   - Única escritura: en las rutas de caché, sesiones, vistas, logs y bootstrap/cache
#     crea un archivo temporal «.prevalidacion.*» como el usuario de PHP-FPM y lo
#     borra al instante. Es idempotente.
#   - No muestra el contenido de .env (solo si cada variable cumple o no).
#
# Uso (como root para comprobar permisos EFECTIVOS del usuario de PHP-FPM):
#   sudo bash scripts/anfeca/prevalidar_despliegue.sh [RUTA_ANFECA] [POOL_PHP_FPM]
#   RUTA_ANFECA   por omisión /var/www/anfeca
#   POOL_PHP_FPM  archivo del pool; por omisión se busca el que contenga «anfeca»
#                 en /etc/php-fpm.d/, si no existe, www.conf
#   Variables opcionales: URL_ANFECA=https://dominio  (verificación HTTP)
#
# Código de salida: 0 sin errores; 1 con errores (no abrir el sitio).

set -u
LC_ALL=C

RUTA="${1:-/var/www/anfeca}"
POOL="${2:-}"
ERRORES=0
AVISOS=0

ok()    { printf '  [OK]     %s\n' "$*"; }
aviso() { printf '  [AVISO]  %s\n' "$*"; AVISOS=$((AVISOS + 1)); }
error() { printf '  [ERROR]  %s\n' "$*"; ERRORES=$((ERRORES + 1)); }
titulo(){ printf '\n== %s\n' "$*"; }

if [[ ! -f "$RUTA/artisan" ]]; then
    echo "No se encontró una instalación de ANFECA en '$RUTA' (falta artisan)." >&2
    exit 1
fi
cd "$RUTA" || exit 1

# ---------------------------------------------------------------------------
titulo "PHP-FPM: pool, usuario y límites"
if [[ -z "$POOL" ]]; then
    POOL=$(grep -lis 'anfeca' /etc/php-fpm.d/*.conf 2>/dev/null | head -1)
    [[ -z "$POOL" ]] && POOL=/etc/php-fpm.d/www.conf
fi
if [[ -r "$POOL" ]]; then
    ok "pool: $POOL"
else
    error "no se puede leer el pool de PHP-FPM ($POOL)"
fi

valor_pool() { # $1 = clave exacta (p. ej. user)
    grep -E "^\s*$1\s*=" "$POOL" 2>/dev/null | tail -1 | sed -E 's/^[^=]*=\s*//; s/\s+$//'
}
ini_pool() { # $1 = directiva PHP en php_admin_value/php_value
    grep -E "^\s*php(_admin)?_value\[$1\]\s*=" "$POOL" 2>/dev/null | tail -1 | sed -E 's/^[^=]*=\s*//; s/\s+$//'
}
a_bytes() { # 512M → bytes; -1 → ilimitado
    local v="${1^^}"
    case "$v" in
        -1) echo -1 ;;
        *G) echo $(( ${v%G} * 1024 * 1024 * 1024 )) ;;
        *M) echo $(( ${v%M} * 1024 * 1024 )) ;;
        *K) echo $(( ${v%K} * 1024 )) ;;
        ''|*[!0-9]*) echo 0 ;;
        *) echo "$v" ;;
    esac
}

USUARIO_WEB=$(valor_pool user); USUARIO_WEB=${USUARIO_WEB:-apache}
GRUPO_WEB=$(valor_pool group); GRUPO_WEB=${GRUPO_WEB:-$USUARIO_WEB}
if id "$USUARIO_WEB" >/dev/null 2>&1; then
    ok "PHP-FPM se ejecuta como $USUARIO_WEB:$GRUPO_WEB"
else
    error "el usuario del pool ($USUARIO_WEB) no existe"
fi

PID_FPM=$(pgrep -u "$USUARIO_WEB" php-fpm 2>/dev/null | head -1)
if [[ -n "$PID_FPM" ]]; then
    UMASK_FPM=$(awk '/^Umask/ {print $2}' "/proc/$PID_FPM/status" 2>/dev/null)
    ok "proceso PHP-FPM activo (umask ${UMASK_FPM:-desconocida})"
else
    aviso "no hay procesos PHP-FPM de $USUARIO_WEB en ejecución"
fi

comprobar_limite() { # $1 directiva, $2 mínimo legible, $3 mínimo en bytes
    local v
    v=$(ini_pool "$1")
    [[ -z "$v" ]] && v=$(php-fpm -i 2>/dev/null | awk -F'=> ' -v d="$1" '$1 ~ "^"d" " {print $2; exit}' | awk '{print $1}')
    [[ -z "$v" ]] && v=$(php -r "echo ini_get('$1');" 2>/dev/null)
    local b
    b=$(a_bytes "$v")
    if [[ "$b" == "-1" || "$b" -ge "$3" ]]; then
        ok "$1 = $v (mínimo $2)"
    else
        error "$1 = ${v:-desconocido}; se requiere al menos $2"
    fi
}
comprobar_limite memory_limit 512M $((512 * 1024 * 1024))
comprobar_limite upload_max_filesize 6M $((6 * 1024 * 1024))
comprobar_limite post_max_size 25M $((25 * 1024 * 1024))

# ---------------------------------------------------------------------------
titulo "PHP, extensiones y Composer"
VERSION_PHP=$(php -r 'echo PHP_VERSION;' 2>/dev/null)
[[ "$VERSION_PHP" == "8.5.12" ]] && ok "PHP CLI $VERSION_PHP" || error "PHP CLI ${VERSION_PHP:-no encontrado}: se requiere exactamente 8.5.12"
MODULOS=$(php -m 2>/dev/null | tr 'A-Z' 'a-z')
for e in bcmath ctype curl dom fileinfo filter gd iconv intl mbstring openssl pcntl pdo_pgsql pgsql session sockets tokenizer xml xmlreader xmlwriter zip zlib; do
    grep -qx "$e" <<<"$MODULOS" || error "falta la extensión PHP: $e"
done
ok "extensiones PHP revisadas"
if command -v composer >/dev/null 2>&1; then
    if composer check-platform-reqs --lock --no-interaction >/dev/null 2>&1; then
        ok "composer check-platform-reqs --lock"
    else
        error "composer check-platform-reqs --lock informa requisitos faltantes"
    fi
else
    aviso "composer no está en el PATH de este usuario"
fi
[[ -f vendor/autoload.php ]] && ok "vendor/ instalado" || error "falta vendor/ (composer install --no-dev)"
[[ -f public/build/manifest.json ]] && ok "public/build/manifest.json presente" || error "faltan los activos compilados (public/build)"

# ---------------------------------------------------------------------------
titulo "Configuración de Laravel (.env y caché de archivos)"
if [[ -f .env ]]; then
    MODO=$(stat -c '%a' .env)
    [[ "$MODO" =~ ^6[04]0$ ]] && ok ".env con permisos $MODO" || aviso ".env con permisos $MODO (se recomienda 640)"
    env_es() { grep -qE "^$1=$2\s*$" .env; }
    env_es APP_ENV production && ok "APP_ENV=production" || error "APP_ENV no es production"
    env_es APP_DEBUG false && ok "APP_DEBUG=false" || error "APP_DEBUG no es false"
    grep -qE '^APP_KEY=base64:.{40,}' .env && ok "APP_KEY definida (no se muestra)" || error "APP_KEY vacía"
    env_es ANFECA_PDM_MODO apagado && ok "ANFECA_PDM_MODO=apagado" || aviso "ANFECA_PDM_MODO no es «apagado»: confirme que la activación del PDM fue autorizada"
    for v in CACHE_STORE SESSION_DRIVER; do
        env_es "$v" file && ok "$v=file" || aviso "$v no es «file»: revise la ruta y los permisos de su almacén"
    done
else
    error "no existe .env"
fi
if grep -Eq "^\s*'permission'\s*=>" <(sed -n "/'file' => \[/,/\],/p" config/cache.php); then
    error "config/cache.php define 'permission' en el almacén file: FileStore lo aplica también a los directorios (HTTP 500 en el login)"
else
    ok "config/cache.php: el almacén file no fuerza modo a los directorios"
fi
if [[ -f bootstrap/cache/config.php ]]; then
    PROP=$(stat -c '%U' bootstrap/cache/config.php)
    [[ "$PROP" == "$USUARIO_WEB" ]] && ok "configuración en caché generada por $USUARIO_WEB" \
        || aviso "bootstrap/cache/config.php pertenece a $PROP; regénere con: sudo -u $USUARIO_WEB php artisan config:cache"
fi

# ---------------------------------------------------------------------------
titulo "Directorios con escritura (permisos efectivos de $USUARIO_WEB)"
ES_ROOT=0; [[ $EUID -eq 0 ]] && ES_ROOT=1
como_web() {
    if [[ $ES_ROOT -eq 1 ]]; then
        runuser -u "$USUARIO_WEB" -- "$@"
    elif sudo -n -u "$USUARIO_WEB" true 2>/dev/null; then
        sudo -n -u "$USUARIO_WEB" -- "$@"
    else
        return 99
    fi
}
[[ $ES_ROOT -eq 0 ]] && aviso "no se ejecuta como root: los permisos efectivos de $USUARIO_WEB se verifican solo si sudo -n lo permite"

ESCRITURA=(storage/framework/cache/data storage/framework/cache/locks storage/framework/sessions
           storage/framework/views storage/framework/testing storage/logs bootstrap/cache storage/app/public)
for d in "${ESCRITURA[@]}" storage/app/private; do
    if [[ ! -d "$d" ]]; then
        error "falta el directorio $d (créelo con install -d -o <deploy> -g $GRUPO_WEB -m 2770 $d)"
        continue
    fi
    if [[ "$d" == storage/app/private ]]; then
        # Documentos privados: solo comprobación de acceso, sin crear ni listar archivos.
        como_web test -w "$d" -a -x "$d"; r=$?
    else
        como_web sh -c 'f=$(mktemp -p "$1" .prevalidacion.XXXXXX) && rm -f "$f"' _ "$d"; r=$?
    fi
    case $r in
        0)  ok "$d: escritura efectiva de $USUARIO_WEB ($(stat -c '%A %U:%G' "$d"))" ;;
        99) aviso "$d: sin verificación efectiva ($(stat -c '%A %U:%G' "$d"))" ;;
        *)  error "$d: $USUARIO_WEB NO puede escribir ($(stat -c '%A %U:%G' "$d"))" ;;
    esac
done

# Fallo del 2026-11-21: subdirectorios de caché sin bit de ejecución.
MALOS=$(find storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
        -type d ! -perm -u+x 2>/dev/null | head -20)
if [[ -n "$MALOS" ]]; then
    error "directorios sin permiso de recorrido para su propietario (causan HTTP 500):"
    printf '           %s\n' $MALOS
    echo   "           corrección: sudo find <ruta> -type d ! -perm -u+x -exec chmod u+x,g+x {} +   (solo esas rutas)"
else
    ok "caché, sesiones, vistas, logs y bootstrap/cache: todos los directorios son recorribles"
fi

# Archivos que PHP-FPM no podrá modificar (creados desde la terminal por otro usuario).
AJENOS=$(find storage/framework/cache/data storage/framework/views -type f ! -name .gitignore ! -user "$USUARIO_WEB" 2>/dev/null | wc -l)
if [[ "$AJENOS" -gt 0 ]]; then
    aviso "$AJENOS archivo(s) de caché/vistas no pertenecen a $USUARIO_WEB; ejecute Artisan como $USUARIO_WEB (sudo -u $USUARIO_WEB php artisan optimize:clear)"
else
    ok "caché y vistas compiladas pertenecen a $USUARIO_WEB"
fi
if [[ -f storage/logs/laravel.log ]]; then
    como_web test -w storage/logs/laravel.log; r=$?
    [[ $r -eq 0 ]] && ok "storage/logs/laravel.log escribible por $USUARIO_WEB" \
        || { [[ $r -eq 99 ]] && aviso "laravel.log: sin verificación efectiva" || error "$USUARIO_WEB no puede escribir storage/logs/laravel.log"; }
fi

# ---------------------------------------------------------------------------
titulo "SELinux y ACL"
if command -v getenforce >/dev/null 2>&1; then
    ESTADO=$(getenforce)
    ok "SELinux: $ESTADO"
    if [[ "$ESTADO" != "Disabled" ]]; then
        for d in storage bootstrap/cache storage/framework/cache/data storage/logs; do
            T=$(stat -c '%C' "$d" 2>/dev/null | cut -d: -f3)
            [[ "$T" == "httpd_sys_rw_content_t" ]] && ok "$d: contexto $T" \
                || error "$d: contexto ${T:-desconocido}; se requiere httpd_sys_rw_content_t (semanage fcontext + restorecon)"
        done
        for b in httpd_can_network_connect_db; do
            [[ "$(getsebool "$b" 2>/dev/null | awk '{print $3}')" == "on" ]] && ok "booleano $b=on" \
                || aviso "booleano $b no está activo (necesario si PostgreSQL se usa por TCP)"
        done
    fi
else
    aviso "getenforce no disponible"
fi
if command -v getfacl >/dev/null 2>&1; then
    for d in storage/framework/cache/data storage/framework/sessions storage/logs; do
        DEF=$(getfacl -p --omit-header "$d" 2>/dev/null | grep -c '^default:')
        [[ "$DEF" -gt 0 ]] && ok "$d: ACL por omisión presente ($DEF entradas)" || ok "$d: sin ACL por omisión (se usan propietario/grupo setgid)"
    done
fi

# ---------------------------------------------------------------------------
titulo "Rutas Preview y respuesta HTTP"
if [[ $ES_ROOT -eq 1 ]] || sudo -n -u "$USUARIO_WEB" true 2>/dev/null; then
    if como_web php artisan route:list --path=preview 2>&1 | grep -qi "doesn't have any routes"; then
        ok "sin rutas /preview en este entorno"
    else
        error "existen rutas /preview disponibles (APP_ENV debe ser production)"
    fi
else
    aviso "verificación de rutas Preview omitida (requiere ejecutar como $USUARIO_WEB)"
fi
if [[ -n "${URL_ANFECA:-}" ]] && command -v curl >/dev/null 2>&1; then
    for u in /login /registro/datos-personales; do
        c=$(curl -s -o /dev/null -w '%{http_code}' "$URL_ANFECA$u")
        [[ "$c" == "200" ]] && ok "$u → $c" || error "$u → $c (se esperaba 200)"
    done
    c=$(curl -s -o /dev/null -w '%{http_code}' "$URL_ANFECA/preview/datos")
    [[ "$c" == "404" ]] && ok "/preview/datos → 404" || error "/preview/datos → $c (se esperaba 404)"
fi

# ---------------------------------------------------------------------------
printf '\n== Resultado: %d error(es), %d aviso(s)\n' "$ERRORES" "$AVISOS"
if [[ $ERRORES -gt 0 ]]; then
    echo "NO abrir ANFECA a los usuarios hasta corregir los errores."
    exit 1
fi
echo "Prevalidación superada."
exit 0
