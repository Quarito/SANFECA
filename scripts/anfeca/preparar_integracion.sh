#!/usr/bin/env bash
# =============================================================================
# ANFECA · ACTIVIDAD 2 — Preparar la integración de las bases (sin modificar la principal).
#
#   cd /home/servidorweb/anfeca_f45 && bash scripts/anfeca/preparar_integracion.sh [--recrear-ensayo]
#
# 1. Revisa las migraciones PDM 000001–000004 y su estado en la base principal (lectura).
# 2. Revisa las decisiones D-11, D-13 y D-14 registradas en este worktree.
# 3. Respaldo NUEVO de bd_laravelanfeca_pruebas (pg_dump -Fc, permisos 600) y restauración
#    de prueba en una base independiente: bd_anfeca_ensayo_pruebas_pg. Verifica que la
#    restauración es idéntica (estructura y datos, tabla por tabla).
# 4. ENSAYO COMPLETO F1–F5 en la base restaurada: migraciones PDM, catálogos, plan F2,
#    migración, conciliación, espejo, principal (equivalencia + recorrido funcional de lectura
#    con todos los usuarios reales), exclusivo, exportación y vuelta a apagado.
# 5. Comprueba que la base principal NO cambió (huella antes/después).
#
# Garantías:
#   - En bd_laravelanfeca_pruebas solo hay SELECT y pg_dump. En bd_anfeca_copia_pruebas_pg, nada.
#   - Solo crea/reemplaza bd_anfeca_ensayo_pruebas_pg, y únicamente si la creó este script
#     (lleva un comentario de marca) y se indica --recrear-ensayo.
#   - Contraseña solicitada en pantalla, nunca guardada. Respaldos fuera del repositorio.
# =============================================================================
set -euo pipefail

PRINCIPAL="bd_laravelanfeca_pruebas"
COPIA="bd_anfeca_copia_pruebas_pg"
ENSAYO="bd_anfeca_ensayo_pruebas_pg"
MARCA="ANFECA ensayo de integracion (preparar_integracion.sh)"
APP="${ANFECA_APP:-/var/www/ANFECA_PRUEBAS}"   # variable solo para ensayos fuera del servidor
RECREAR="${1:-}"
FECHA="$(date +%Y%m%d_%H%M%S)"
SALIDA="$HOME/ANFECA_RESPALDOS/integracion/$FECHA"

falla() { echo; echo "ERROR: $*" >&2; exit 1; }
paso()  { echo; echo "=================== $* ==================="; }
env_de() { { grep -E "^$1=" "$APP/.env" || true; } | tail -1 | cut -d= -f2- | tr -d '"'"'"; }
art()   { php artisan "$@"; }                       # artisan contra la base de $DB_DATABASE

[[ -f artisan ]] || falla "Ejecute desde la raíz del worktree (/home/servidorweb/anfeca_f45)."
[[ "$(pwd -P)" != /var/www/* ]] || falla "No se ejecuta dentro de /var/www."
git merge-base --is-ancestor 450f69d HEAD 2>/dev/null || falla "El worktree no contiene 450f69d."
[[ ! -f bootstrap/cache/config.php ]] || falla "Hay configuración en caché en el worktree: ejecute php artisan config:clear."
mkdir -p "$SALIDA" && chmod 700 "$HOME/ANFECA_RESPALDOS" "$HOME/ANFECA_RESPALDOS/integracion" "$SALIDA"
exec > >(tee -a "$SALIDA/preparacion.log") 2>&1

export PGHOST="$(env_de DB_HOST)" PGPORT="$(env_de DB_PORT)" PGUSER="$(env_de DB_USERNAME)"
[[ "$(env_de DB_DATABASE)" == "$PRINCIPAL" ]] || falla "La aplicación no apunta a $PRINCIPAL."
read -r -s -p "Contraseña de PostgreSQL para $PGUSER: " PGPASSWORD; echo; export PGPASSWORD
trap 'unset PGPASSWORD DB_PASSWORD' EXIT
export DB_CONNECTION=pgsql DB_HOST="$PGHOST" DB_PORT="$PGPORT" DB_USERNAME="$PGUSER" DB_PASSWORD="$PGPASSWORD" DB_URL=""
q() { psql -d "$1" -X -q -At -v ON_ERROR_STOP=1 -c "$2"; }

paso "0. Base principal: huella inicial (solo lectura)"
bash scripts/anfeca/huella_bd.sh "$PRINCIPAL" > "$SALIDA/huella_principal_antes.txt"
grep -E '^(BASE|ESQUEMAS|ESTRUCTURA)' "$SALIDA/huella_principal_antes.txt" | cut -c1-120
echo "Tablas: public=$(q "$PRINCIPAL" "select count(*) from pg_tables where schemaname='public'") · anfeca_modelo=$(q "$PRINCIPAL" "select count(*) from pg_tables where schemaname='anfeca_modelo'")"

paso "1. Migraciones PDM"
for f in database/migrations_pdm/*.php; do echo "$(sha256sum "$f" | cut -c1-12)  $f"; done
echo; echo "Estado en $PRINCIPAL (solo lectura):"
DB_DATABASE="$PRINCIPAL" art migrate:status --path=database/migrations_pdm 2>&1 || echo "(sin tabla de migraciones PDM registrada)"
DB_DATABASE="$PRINCIPAL" ANFECA_PDM_MODO=apagado art anfeca:pdm-modo

paso "2. Decisiones D-11, D-13, D-14 (config/anfeca_pdm_decisiones.php de este worktree)"
php -r '$d = require "config/anfeca_pdm_decisiones.php";
    foreach (["instituciones" => "D-11", "domicilios" => "D-13", "certificados" => "D-14", "solicitudes" => "D-15"] as $k => $n) {
        $e = $d[$k] ?? []; $sinFuente = 0;
        foreach ($e as $v) { if (is_array($v) && trim((string) ($v["fuente"] ?? "")) === "") { $sinFuente++; } }
        printf("%-5s %-13s %3d decisión(es) registradas, %d sin fuente documentada\n", $n, $k, count($e), $sinFuente);
    }'
git diff --quiet HEAD -- config/anfeca_pdm_decisiones.php && echo "(sin cambios locales respecto al repositorio)" || echo "(con decisiones locales no versionadas: se conservan en el servidor, no se suben a GitHub)"

paso "3. Respaldo nuevo de $PRINCIPAL"
DUMP="$SALIDA/${PRINCIPAL}_$FECHA.dump"
pg_dump -Fc -d "$PRINCIPAL" -f "$DUMP"
chmod 600 "$DUMP"
bash scripts/anfeca/huella_bd.sh "$PRINCIPAL" > "$SALIDA/huella_principal_al_respaldar.txt"
echo "Archivo: $DUMP"
echo "Tamaño: $(stat -c %s "$DUMP") bytes · SHA-256: $(sha256sum "$DUMP" | cut -d' ' -f1)"
echo "Objetos con datos en el respaldo: $(pg_restore -l "$DUMP" | grep -c 'TABLE DATA')"
pg_dump --version

paso "4. Restauración de prueba en $ENSAYO"
[[ "$ENSAYO" != "$PRINCIPAL" && "$ENSAYO" != "$COPIA" ]] || falla "Nombre de ensayo inválido."
if [[ "$(q postgres "select count(*) from pg_database where datname = '$ENSAYO'")" == "1" ]]; then
    [[ "$(q postgres "select shobj_description(oid, 'pg_database') from pg_database where datname = '$ENSAYO'")" == "$MARCA" ]] \
        || falla "$ENSAYO existe y no fue creada por este script: no se toca."
    [[ "$RECREAR" == "--recrear-ensayo" ]] || falla "$ENSAYO ya existe (de un ensayo anterior). Repita con --recrear-ensayo para reemplazarla."
    dropdb "$ENSAYO"
fi
createdb "$ENSAYO" || falla "No se pudo crear $ENSAYO. El rol $PGUSER necesita CREATEDB, o créela un administrador:
  sudo -u postgres createdb -O $PGUSER $ENSAYO"
q "$ENSAYO" "comment on database $ENSAYO is '$MARCA'"
pg_restore -d "$ENSAYO" --no-owner --no-privileges --exit-on-error "$DUMP"
bash scripts/anfeca/huella_bd.sh "$ENSAYO" > "$SALIDA/huella_ensayo_restaurada.txt"
if diff <(grep -v '^BASE' "$SALIDA/huella_principal_al_respaldar.txt") <(grep -v '^BASE' "$SALIDA/huella_ensayo_restaurada.txt") > "$SALIDA/dif_restauracion.txt"; then
    echo "RESTAURACIÓN VERIFICADA: estructura y $(grep -c '^DATOS' "$SALIDA/huella_ensayo_restaurada.txt") tablas idénticas a la principal."
else
    echo "Diferencias entre la principal (al respaldar) y la restauración:"; cat "$SALIDA/dif_restauracion.txt"
    echo "Si solo difieren tablas con actividad de usuarios entre el respaldo y la huella, repita en un momento sin uso."
fi
# public.migrations se excluye: es el único cambio esperado (registro de las migraciones PDM aplicadas).
grep '^DATOS public\.' "$SALIDA/huella_ensayo_restaurada.txt" | grep -v '^DATOS public\.migrations ' > "$SALIDA/public_ensayo_inicial.txt"

paso "5. ENSAYO F1–F5 en $ENSAYO"
export DB_DATABASE="$ENSAYO" ANFECA_PDM_MODO=apagado
[[ "$(art tinker --execute='echo DB::connection()->getDatabaseName();' 2>/dev/null | tail -1)" == "$ENSAYO" ]] || falla "Laravel no apunta a $ENSAYO."

echo "--- 5.1 Migraciones PDM (000001–000004) y catálogos"
art migrate --path=database/migrations_pdm --force
art db:seed --class='Database\Seeders\Pdm\CatalogosPdmSeeder' --force
echo "anfeca_modelo: $(q "$ENSAYO" "select count(*) from pg_tables where schemaname='anfeca_modelo'") tablas, $(q "$ENSAYO" "select count(*) from pg_constraint where contype='f' and connamespace='anfeca_modelo'::regnamespace") FK · anfeca_compat: $(q "$ENSAYO" "select count(*) from information_schema.views where table_schema='anfeca_compat'") vistas"

echo "--- 5.2 Plan F2"
art anfeca:pdm-migrar --informe="$SALIDA/plan_f2.json" || true
BLOQ="$(php -r '$j = json_decode(file_get_contents($argv[1]), true); echo count(array_filter($j["incidencias"] ?? [], fn ($i) => $i["bloqueante"] ?? false));' "$SALIDA/plan_f2.json" 2>/dev/null || echo '?')"
echo "Incidencias bloqueantes: $BLOQ (detalle en $SALIDA/plan_f2.json)"
if [[ "$BLOQ" != "0" ]]; then
    echo; echo "ENSAYO DETENIDO en el plan: registre las decisiones pendientes (D-11/D-13/D-14) con fuente documentada"
    echo "en config/anfeca_pdm_decisiones.php y repita con --recrear-ensayo. La base principal no se tocó."
    ETAPA="plan con bloqueos"
else
    echo "--- 5.3 Migración F2 + conciliación + equivalencia"
    art anfeca:pdm-migrar --ejecutar --confirmar --informe="$SALIDA/migracion_f2.json"
    art anfeca:pdm-conciliar
    art anfeca:pdm-equivalencia

    echo "--- 5.4 Recorrido funcional en apagado (todos los usuarios con datos)"
    php scripts/anfeca/recorrido_lectura.php "$SALIDA/recorrido_apagado.json"

    echo "--- 5.5 Espejo (F3)"
    ANFECA_PDM_MODO=espejo art anfeca:pdm-modo espejo --confirmar
    art anfeca:pdm-conciliar

    echo "--- 5.6 Principal (F4)"
    ANFECA_PDM_MODO=principal art anfeca:pdm-modo principal --confirmar
    art anfeca:pdm-equivalencia
    ANFECA_PDM_MODO=principal php scripts/anfeca/recorrido_lectura.php "$SALIDA/recorrido_principal.json"

    echo "--- 5.7 Exclusivo (F5), exportación y reversión"
    ANFECA_PDM_MODO=exclusivo art anfeca:pdm-modo exclusivo --confirmar
    ANFECA_PDM_MODO=exclusivo php scripts/anfeca/recorrido_lectura.php "$SALIDA/recorrido_exclusivo.json"
    art anfeca:pdm-exportar-public --confirmar
    ANFECA_PDM_MODO=principal art anfeca:pdm-modo principal --confirmar
    art anfeca:pdm-modo apagado
    art anfeca:pdm-conciliar
    art anfeca:pdm-equivalencia

    echo "--- 5.8 Comparación de recorridos (la aplicación debe mostrar lo mismo en los tres modos)"
    php -r '$a = json_decode(file_get_contents($argv[1]), true); $ok = true;
        foreach (array_slice($argv, 2) as $f) { $b = json_decode(file_get_contents($f), true);
            $dif = array_keys(array_filter($a["paginas"], fn ($p, $k) => ($b["paginas"][$k] ?? null) !== $p, ARRAY_FILTER_USE_BOTH));
            printf("%s: %d páginas, %d distintas de apagado%s\n", $b["modo"], count($b["paginas"]), count($dif), $dif ? " → ".implode(", ", array_slice($dif, 0, 10)) : "");
            $ok = $ok && !$dif && count($b["paginas"]) === count($a["paginas"]); }
        printf("Apagado: %s\n", json_encode($a["resumen"]["codigos"])); exit($ok ? 0 : 1);' \
        "$SALIDA/recorrido_apagado.json" "$SALIDA/recorrido_principal.json" "$SALIDA/recorrido_exclusivo.json" \
        && echo "RECORRIDOS IDÉNTICOS" || echo "ATENCIÓN: hay páginas distintas (revise los JSON)."

    echo "--- 5.9 public del ensayo tras todo el ciclo"
    bash scripts/anfeca/huella_bd.sh "$ENSAYO" | grep '^DATOS public\.' | grep -v '^DATOS public\.migrations ' > "$SALIDA/public_ensayo_final.txt"
    diff "$SALIDA/public_ensayo_inicial.txt" "$SALIDA/public_ensayo_final.txt" && echo "public sin cambios en el ensayo (el ciclo F2–F5 no alteró ningún registro)." || echo "ATENCIÓN: public cambió en el ensayo (ver arriba)."
    ETAPA="ciclo F2–F5 completo"
fi

paso "6. Base principal: huella final"
unset DB_DATABASE
bash scripts/anfeca/huella_bd.sh "$PRINCIPAL" > "$SALIDA/huella_principal_despues.txt"
if diff <(grep -v '^BASE' "$SALIDA/huella_principal_antes.txt") <(grep -v '^BASE' "$SALIDA/huella_principal_despues.txt"); then
    echo "BASE PRINCIPAL SIN CAMBIOS."
else
    echo "(Diferencias arriba: solo pueden provenir de usuarios del sitio; este script no escribe en la principal.)"
fi
curl -s -o /dev/null -w "Login 8084: HTTP %{http_code}\n" "${ANFECA_URL:-http://127.0.0.1:8084/login}" || true

paso "RESULTADO"
echo "Respaldo: $DUMP"
echo "Ensayo: $ENSAYO · Etapa alcanzada: $ETAPA"
echo "Registros: $SALIDA"
