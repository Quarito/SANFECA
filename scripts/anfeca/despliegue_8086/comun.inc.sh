# ANFECA 8086 · Funciones y rutas comunes de los scripts de despliegue (se incluye con «source»).
# Todas las rutas admiten sustitución por variables SOLO en simulación (ANFECA_SIMULACION=1), para ensayar la
# recuperación sin privilegios; en ese modo se rechazan las rutas reales del servidor.
APP=${ANFECA_APP:-/var/www/ANFECA_ENSAYO}
REL_RAIZ=${ANFECA_REL_RAIZ:-/var/www/ANFECA_8086}
REL=$REL_RAIZ/current
VH=${ANFECA_VHOST:-/etc/httpd/conf.d/anfeca-ensayo.conf}
EVID=${ANFECA_EVIDENCIA:-/root}
ORIGINAL=$EVID/anfeca8086_original                     # copia ÚNICA del VirtualHost y .env previos a la primera conmutación
SOCK=${ANFECA_FPM_SOCKET:-/run/anfeca8086-fpm/php-fpm.sock}
UID_SW=$(id -u servidorweb 2>/dev/null || echo 1000)
export XDG_RUNTIME_DIR=/run/user/$UID_SW               # podman sin privilegios de servidorweb invocado desde root

if [ "${ANFECA_SIMULACION:-0}" = 1 ]; then
    case "$APP$VH$EVID" in *"/var/www/ANFECA_ENSAYO"*|*"/etc/httpd"*|"/root"*) echo "DETENIDO: la simulación no admite rutas reales" >&2; exit 1;; esac
else
    [ "$(id -u)" = 0 ] || { echo "Ejecutar con sudo" >&2; exit 1; }
fi
# sudo -u servidorweb REINICIA el entorno (env_reset): lo que el comando necesite debe pasarse explícitamente
# (p. ej., SW env PG8086_ADMIN=… bash …). La simulación reproduce ese reinicio con env -i.
SW() {
    if [ "${ANFECA_SIMULACION:-0}" = 1 ]; then env -i PATH="$PATH" HOME="$HOME" SIM_FALLA="${SIM_FALLA:-}" "$@"
    else sudo -u servidorweb XDG_RUNTIME_DIR="$XDG_RUNTIME_DIR" "$@"; fi
}
fallo() { echo "DETENIDO: $*" >&2; exit 1; }
vhost_conmutado() { grep -q 'ANFECA_8086' "$VH"; }
protegidos() {   # huella de los ambientes protegidos (no debe cambiar)
    for u in 8084 8085; do echo "$u $(curl -s -o /dev/null -w '%{http_code}' --max-time 10 http://127.0.0.1:$u/)"; done
    echo "8008 $({ ss -ltn 2>/dev/null || true; } | { grep -c ':8008 ' || true; })"
    echo "anfeca $(git -C /var/www/anfeca rev-parse HEAD 2>/dev/null || true) $({ git -C /var/www/anfeca status --porcelain 2>/dev/null || true; } | sha256sum | cut -c1-16)"
}
