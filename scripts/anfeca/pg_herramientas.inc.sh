# =============================================================================
# ANFECA · Selección de herramientas cliente de PostgreSQL compatibles con el SERVIDOR.
#   source scripts/anfeca/pg_herramientas.inc.sh && anfeca_pg_preparar
# Requiere PGHOST/PGPORT/PGUSER/PGPASSWORD/PGDATABASE exportadas.
# - Consulta la versión real del servidor (server_version_num).
# - Selecciona /usr/pgsql-<mayor>/bin (paquetes PGDG) y verifica que psql, pg_dump y pg_restore
#   tengan la MISMA versión mayor que el servidor. pg_dump de una versión menor no puede respaldar
#   un servidor más nuevo (p. ej., el psql 16.15 del PATH predeterminado frente al servidor 18.6).
# - Ajusta el PATH SOLO dentro del script que lo invoca; nunca el PATH global del sistema.
# - Si no hay herramientas compatibles, se detiene con un mensaje claro.
# Variables de prueba: ANFECA_PG_RAIZ (por omisión /usr/pgsql-).
# =============================================================================
anfeca_pg_detener() { echo "DETENIDO (PostgreSQL): $*" >&2; exit 1; }

anfeca_pg_mayor_de() { "$1" --version 2>/dev/null | sed -E 's/.* ([0-9]+)(\.[0-9]+)?.*/\1/' | head -1; }

anfeca_pg_preparar() {
    local raiz="${ANFECA_PG_RAIZ:-/usr/pgsql-}" sonda="" candidato num mayor bin herramienta m
    # Cualquier psql sirve para PREGUNTAR la versión del servidor; se prefiere el más reciente instalado.
    for candidato in $(ls -d "${raiz}"*/bin/psql 2>/dev/null | sort -V -r) $(command -v psql 2>/dev/null); do
        [ -x "$candidato" ] && { sonda=$candidato; break; }
    done
    [ -n "$sonda" ] || anfeca_pg_detener "no hay ningún psql instalado"
    num=$("$sonda" -Atc 'show server_version_num' 2>/dev/null) || anfeca_pg_detener "no fue posible conectar con el servidor ${PGHOST:-?}:${PGPORT:-?}/${PGDATABASE:-?}"
    [[ "$num" =~ ^[0-9]+$ ]] || anfeca_pg_detener "respuesta inesperada del servidor: $num"
    mayor=$((num / 10000))
    ANFECA_PG_SERVIDOR=$("$sonda" -Atc 'show server_version' | cut -d' ' -f1)
    bin="${raiz}${mayor}/bin"
    [ -d "$bin" ] || anfeca_pg_detener "el servidor es PostgreSQL ${ANFECA_PG_SERVIDOR}, pero no existe ${bin}. Instale el cliente ${mayor} (no se usa otra versión)."
    for herramienta in psql pg_dump pg_restore; do
        [ -x "$bin/$herramienta" ] || anfeca_pg_detener "falta $bin/$herramienta"
        m=$(anfeca_pg_mayor_de "$bin/$herramienta")
        [ "$m" = "$mayor" ] || anfeca_pg_detener "$bin/$herramienta es versión $m; el servidor es $mayor"
    done
    export PATH="$bin:$PATH" ANFECA_PG_BIN="$bin" ANFECA_PG_MAYOR="$mayor" ANFECA_PG_SERVIDOR
    echo "PostgreSQL: servidor ${ANFECA_PG_SERVIDOR} · cliente ${bin} ($("$bin/pg_dump" --version | awk '{print $NF}'))"
}

# Imagen desechable con la MISMA versión exacta del servidor (para restauraciones de verificación).
anfeca_pg_imagen() {
    local imagen="docker.io/library/postgres:${ANFECA_PG_SERVIDOR:?ejecute anfeca_pg_preparar primero}"
    podman image exists "$imagen" || anfeca_pg_detener "no existe la imagen local $imagen (misma versión que el servidor); no se descarga automáticamente"
    echo "$imagen"
}
