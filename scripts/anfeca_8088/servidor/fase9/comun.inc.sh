#!/bin/bash
# ANFECA 8088 · Fase 9 · Parámetros comunes (ajustar SOLO aquí o por variables de entorno). Sin contraseñas.
set -uo pipefail

REPO="${REPO:-/home/servidorweb/anfeca_8088_codigo}"             # checkout Git existente (no se modifica su rama)
APP="${APP:-/var/www/ANFECA_8088}"                                # aplicación desplegada en :8088
PREPARACION="${PREPARACION:-/home/servidorweb/anfeca_8088_fase9}" # worktree de la rama de integración (compilación)
PRUEBAS="${PRUEBAS:-/home/servidorweb/anfeca_8088_pruebas}"       # copia de PRUEBA (base de prueba; php -S en localhost)
RAMA="${RAMA:-integracion/fase9-8088}"
COMMIT_ESPERADO="${COMMIT_ESPERADO:-34b04d2}"
PG_BIN="${PG_BIN:-/usr/pgsql-18/bin}"
PGHOST="${PGHOST:-127.0.0.1}"; PGPORT="${PGPORT:-5488}"
BD="${BD:-certificacion_anfeca}"
BD_PRUEBAS="${BD_PRUEBAS:-certificacion_anfeca_pruebas_fase9}"
ROL_APP="${ROL_APP:-anfeca_8088}"
PHP="${PHP:-php}"
USUARIO_WEB="${USUARIO_WEB:-apache}"
SEPOMEX="${SEPOMEX:-/var/lib/anfeca8088/sepomex/CPdescarga.xls}"  # archivo oficial (fuera de Git y de public/)
SEPOMEX_SHA256="${SEPOMEX_SHA256:-0a8e2611b92635f47c646816b3641354ab4a554f64b058447890d77e8a052821}"
PAQUETE_SQL="${PAQUETE_SQL:-/var/lib/anfeca8088/sepomex/SEPOMEX_ANFECA}"  # paquete SQL privado (alternativa B)
RESPALDOS="${RESPALDOS:-/var/backups/anfeca8088}"
SALIDA="${SALIDA:-$HOME/anfeca_fase9/$(date +%Y%m%d_%H%M%S)_$(basename "$0" .sh)}"
export PGHOST PGPORT

mkdir -p "$SALIDA"
exec > >(tee -a "$SALIDA/registro.log") 2>&1
paso() { printf '\n==> %s\n' "$*"; }
alto() { printf '\nDETENIDO: %s\n' "$*" >&2; exit 1; }
# Conexión administrativa (solo para CREAR bases nuevas de prueba/restauración): socket local con «peer» por omisión,
# o PG_SOCKET=<directorio> / PGADMINPASSWORD=<…> según la autenticación del clúster del 8088.
PG_SOCKET="${PG_SOCKET:-}"
admin_psql() { sudo -u postgres env ${PGADMINPASSWORD:+PGPASSWORD="$PGADMINPASSWORD"} "$PG_BIN/psql" -X -p "$PGPORT" ${PG_SOCKET:+-h "$PG_SOCKET"} "$@"; }
# Consulta de SOLO LECTURA (transacción read-only) a la base indicada.
leer() { PGOPTIONS='-c default_transaction_read_only=on' "$PG_BIN/psql" -X -U "${2:-$ROL_APP}" -d "${3:-$BD}" -At -F '|' -c "$1"; }
# ENTORNO permite variables adicionales (p. ej. ENTORNO="DB_DATABASE=otra"); no aplica si la configuración está en caché.
artisan() { (cd "${DIR_APP:-$APP}" && sudo -u "$USUARIO_WEB" env ${ENTORNO:-} "$PHP" -d memory_limit=1024M artisan "$@"); }
# artisan en el directorio de preparación, como el usuario que ejecuta (no requiere acceso de Apache a $HOME).
artisan_prep() { (cd "$PREPARACION" && env ${ENTORNO:-} "$PHP" -d memory_limit=1024M artisan "$@"); }
# Exige un respaldo del bloque 2 con huellas válidas (RESPALDO=/var/backups/anfeca8088/fase9_…).
exigir_respaldo() {
    [ -n "${RESPALDO:-}" ] && [ -f "$RESPALDO/SHA256SUMS" ] || alto "Indique RESPALDO=<directorio del bloque 2>."
    (cd "$RESPALDO" && sha256sum -c --quiet SHA256SUMS) || alto "El respaldo $RESPALDO no supera la verificación de huellas."
    echo "Respaldo verificado: $RESPALDO"
}
# Paquete SQL íntegro (SHA256SUMS) y generado a partir del archivo oficial validado.
exigir_paquete() {
    [ -f "$PAQUETE_SQL/SHA256SUMS" ] && [ -f "$PAQUETE_SQL/sql/SEPOMEX_CATALOGO_COMPLETO.sql" ] || alto "No se encontró el paquete SQL en $PAQUETE_SQL."
    (cd "$PAQUETE_SQL" && sha256sum -c --quiet SHA256SUMS) || alto "El paquete $PAQUETE_SQL no supera la verificación de huellas."
    grep -q "\"sha256\": \"$SEPOMEX_SHA256\"" "$PAQUETE_SQL/manifiesto.json" || alto "El paquete no corresponde al archivo oficial validado ($SEPOMEX_SHA256)."
    echo "Paquete verificado: $PAQUETE_SQL ($(grep -o '"version_sepomex": "[^"]*"' "$PAQUETE_SQL/manifiesto.json"))"
}
# Estructura oficial por SQL (sin PHP): 36 tablas, 173 atributos y 35 llaves foráneas en public.
verificar_estructura_sql() {
    local r; r=$(leer "select (select count(*) from information_schema.tables where table_schema='public' and table_type='BASE TABLE')||'/'||
        (select count(*) from information_schema.columns c join information_schema.tables t using (table_schema, table_name) where t.table_schema='public' and t.table_type='BASE TABLE')||'/'||
        (select count(*) from pg_constraint where contype='f' and connamespace='public'::regnamespace)")
    echo "Tablas/atributos/FK: $r"; [ "$r" = "36/173/35" ]
}
exigir_confirmacion() { [ "${CONFIRMAR:-no}" = si ] || alto "Operación con escritura: repita con CONFIRMAR=si tras revisar el bloque anterior."; }
# La contraseña se pide una vez, sin mostrarse ni guardarse (o se toma de ~/.pgpass).
pedir_clave() {
    if [ -z "${PGPASSWORD:-}" ] && ! grep -q ":$PGPORT:" "$HOME/.pgpass" 2>/dev/null; then
        read -rsp "Contraseña del rol ${1:-$ROL_APP} en $PGHOST:$PGPORT: " PGPASSWORD; echo; export PGPASSWORD
    fi
}
echo "Registro: $SALIDA/registro.log"
