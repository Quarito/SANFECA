#!/usr/bin/env bash
# =============================================================================
# ANFECA · ACTIVIDAD 3 — Integración CONTROLADA de la base principal, por fases.
#
#   cd /var/www/ANFECA_PRUEBAS
#   bash /home/servidorweb/anfeca_f45/scripts/anfeca/activar_principal.sh <fase>
#
# Fases (en este orden). Las que escriben exigen ANFECA_AUTORIZO=<fase> en la misma línea:
#   verificar        Solo lectura: código, modo apagado, ensayo aprobado, huella, login.
#   respaldo         pg_dump -Fc nuevo + verificación (punto de recuperación obligatorio).
#   esquema    [E]   Migraciones PDM 000001–000004 + catálogos. public: datos idénticos.
#   datos      [E]   Migración F2 + conciliación + equivalencia (0 diferencias o se revierte).
#   espejo     [E]   Activa F3 (requiere ANFECA_PDM_MODO=espejo ya en .env).
#   comprobar        Solo lectura: conciliación, equivalencia, recorrido funcional, login.
#   apagar           REVERSIÓN inmediata a apagado (siempre permitida).
#
# [E] = escribe en bd_laravelanfeca_pruebas. Nunca borra registros ni bases, nunca ejecuta
# migrate:fresh, db:wipe ni pdm-vaciar. principal/exclusivo NO se activan aquí (decisión posterior).
# =============================================================================
set -euo pipefail

FASE="${1:-}"; PRINCIPAL="${ANFECA_PRINCIPAL:-bd_laravelanfeca_pruebas}"   # variables ANFECA_* de ruta/base: solo ensayos fuera del servidor
APP="${ANFECA_APP:-/var/www/ANFECA_PRUEBAS}"; WT="${ANFECA_WT:-/home/servidorweb/anfeca_f45}"
URL="${ANFECA_URL:-http://192.168.7.130:8084/login}"
PGBIN="${ANFECA_PGBIN:-/usr/pgsql-18/bin}"; export PATH="$PGBIN:$PATH"
RAIZ="$HOME/ANFECA_RESPALDOS/activacion"; FECHA="$(date +%Y%m%d_%H%M%S)"; SALIDA="$RAIZ/${FECHA}_${FASE:-sin_fase}"

falla() { echo; echo "DETENIDO: $*" >&2; exit 1; }
env_de() { { grep -E "^$1=" "$APP/.env" || true; } | tail -1 | cut -d= -f2- | tr -d '"'"'"; }
autorizada() { [[ "${ANFECA_AUTORIZO:-}" == "$FASE" ]] || falla "La fase '$FASE' escribe en $PRINCIPAL: repita con ANFECA_AUTORIZO=$FASE."; }
huella() { bash "$WT/scripts/anfeca/huella_bd.sh" "$PRINCIPAL" > "$1"; }
public_datos() { grep '^DATOS public\.' "$1" | grep -v '^DATOS public\.migrations '; }
login() { local c; c="$(curl -s -o /dev/null -w '%{http_code}' "$URL" || true)"; echo "Login $URL: HTTP $c"; [[ "$c" == "200" ]] || falla "El login no responde 200."; }
art() { (cd "$APP" && php artisan "$@"); }
modo_bd() {   # dos consultas: PostgreSQL analiza toda la sentencia aunque la tabla aún no exista
    if [[ "$(psql -d "$PRINCIPAL" -X -q -At -c "select to_regclass('anfeca_modelo.integracion_control') is not null")" != "t" ]]; then echo sin_control; return; fi
    psql -d "$PRINCIPAL" -X -q -At -c "select coalesce((select ic_modo from anfeca_modelo.integracion_control where ic_id = 1), 'apagado')"
}
recargar_config() { if [[ -f "$APP/bootstrap/cache/config.php" ]]; then art config:cache; else art config:clear; fi; }

[[ "$FASE" =~ ^(verificar|respaldo|esquema|datos|espejo|comprobar|apagar)$ ]] || { sed -n '3,20p' "$0"; exit 1; }
[[ -x "$PGBIN/pg_dump" ]] || falla "No existe $PGBIN (PostgreSQL 18)."
[[ "$(env_de DB_DATABASE)" == "$PRINCIPAL" ]] || falla "La aplicación no apunta a $PRINCIPAL."
mkdir -p "$SALIDA" && chmod 700 "$HOME/ANFECA_RESPALDOS" "$RAIZ" "$SALIDA"
exec > >(tee -a "$SALIDA/fase.log") 2>&1
export PGHOST="$(env_de DB_HOST)" PGPORT="$(env_de DB_PORT)" PGUSER="$(env_de DB_USERNAME)"
read -r -s -p "Contraseña de PostgreSQL para $PGUSER: " PGPASSWORD; echo; export PGPASSWORD
trap 'unset PGPASSWORD' EXIT
echo "Fase: $FASE · Código: $(git -C "$APP" rev-parse --short HEAD) · Modo .env: $(env_de ANFECA_PDM_MODO) · Modo base: $(modo_bd)"

case "$FASE" in
verificar)
    git -C "$APP" merge-base --is-ancestor e831add HEAD || falla "La aplicación no tiene la corrección F5 (e831add)."
    [[ -z "$(git -C "$APP" status --porcelain --untracked-files=no)" ]] || falla "Cambios locales en la aplicación."
    [[ "$(modo_bd)" =~ ^(sin_control|apagado)$ && "$(env_de ANFECA_PDM_MODO)" =~ ^(|apagado)$ ]] || falla "El modo no es apagado."
    ULT="$(ls -1d "$HOME"/ANFECA_RESPALDOS/integracion/*/ 2>/dev/null | tail -1)"
    grep -q "PREPARACIÓN APROBADA" "${ULT}preparacion.log" 2>/dev/null || falla "No hay un ensayo aprobado (preparar_integracion.sh) en $ULT."
    echo "Ensayo aprobado: $ULT"
    grep -E "Incidencias bloqueantes" "${ULT}preparacion.log" || true
    huella "$SALIDA/huella.txt"; echo "Tablas con huella: $(grep -c '^DATOS' "$SALIDA/huella.txt")"
    art migrate:status --path=database/migrations_pdm || true
    login ;;

respaldo)
    DUMP="$SALIDA/${PRINCIPAL}_$FECHA.dump"
    pg_dump -Fc -d "$PRINCIPAL" -f "$DUMP"; chmod 600 "$DUMP"
    pg_restore -l "$DUMP" > "$SALIDA/indice.txt"
    echo "Respaldo: $DUMP · $(stat -c %s "$DUMP") bytes · SHA-256 $(sha256sum "$DUMP" | cut -d' ' -f1)"
    echo "Objetos con datos: $(grep -c 'TABLE DATA' "$SALIDA/indice.txt") · $(pg_dump --version)"
    echo "$DUMP" > "$RAIZ/ULTIMO_RESPALDO" ;;

esquema|datos|espejo)
    autorizada
    RESP="$(cat "$RAIZ/ULTIMO_RESPALDO" 2>/dev/null || true)"
    [[ -s "$RESP" && $(( $(date +%s) - $(stat -c %Y "$RESP") )) -lt 86400 ]] || falla "Falta un respaldo de menos de 24 h (fase respaldo)."
    echo "Punto de recuperación: $RESP"
    huella "$SALIDA/antes.txt"
    case "$FASE" in
    esquema)
        [[ "$(modo_bd)" =~ ^(sin_control|apagado)$ ]] || falla "El modo no es apagado."
        (cd "$APP" && ANFECA_PDM_AUTORIZAR_EXTENSIONES=si php artisan migrate --path=database/migrations_pdm --force)
        ANFECA_PDM_AUTORIZAR_CATALOGOS=si art db:seed --class='Database\Seeders\Pdm\CatalogosPdmSeeder' --force
        art migrate:status --path=database/migrations_pdm ;;
    datos)
        [[ "$(modo_bd)" == "apagado" ]] || falla "El modo no es apagado (o falta la fase esquema)."
        art anfeca:pdm-migrar --informe="$SALIDA/plan.json"
        (cd "$APP" && ANFECA_PDM_AUTORIZAR_MIGRACION=si php artisan anfeca:pdm-migrar --ejecutar --confirmar --informe="$SALIDA/migracion.json")
        art anfeca:pdm-conciliar
        art anfeca:pdm-equivalencia ;;
    espejo)
        [[ "$(env_de ANFECA_PDM_MODO)" == "espejo" ]] || falla "Defina primero ANFECA_PDM_MODO=espejo en $APP/.env (sin la activación registrada, la app sigue en apagado)."
        recargar_config
        (cd "$APP" && ANFECA_PDM_AUTORIZAR_ESPEJO=si php artisan anfeca:pdm-modo espejo --confirmar)
        art anfeca:pdm-modo ;;
    esac
    huella "$SALIDA/despues.txt"
    # Ninguna fase cambia registros de public (solo public.migrations registra las migraciones PDM).
    diff <(public_datos "$SALIDA/antes.txt") <(public_datos "$SALIDA/despues.txt") \
        || falla "Los datos de public cambiaron durante la fase (ver arriba). Revise y, si procede, ejecute la fase apagar."
    echo "Datos de public sin cambios."
    login ;;

comprobar)
    art anfeca:pdm-conciliar
    art anfeca:pdm-equivalencia
    (cd "$WT" && DB_CONNECTION=pgsql DB_HOST="$PGHOST" DB_PORT="$PGPORT" DB_USERNAME="$PGUSER" DB_PASSWORD="$PGPASSWORD" \
        DB_DATABASE="$PRINCIPAL" ANFECA_PDM_MODO="$(env_de ANFECA_PDM_MODO)" ANFECA_RECORRIDO_PRINCIPAL=si \
        php scripts/anfeca/recorrido_lectura.php "$SALIDA/recorrido.json")
    login ;;

apagar)
    autorizada
    ANFECA_PDM_AUTORIZAR_APAGADO=si art anfeca:pdm-modo apagado
    echo "Cambie ANFECA_PDM_MODO=apagado en $APP/.env; después:"; echo "  cd $APP && php artisan config:clear"
    login ;;
esac
echo; echo "FASE '$FASE' APROBADA. Registro: $SALIDA/fase.log"
