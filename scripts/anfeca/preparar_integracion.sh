#!/usr/bin/env bash
# =============================================================================
# ANFECA · ACTIVIDAD 2 — Preparar la integración de las bases (sin modificar la principal).
#
#   cd /home/servidorweb/anfeca_f45 && bash scripts/anfeca/preparar_integracion.sh
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
#   - La base de ensayo la crea PREVIAMENTE un administrador (el rol de Laravel no tiene CREATEDB
#     ni se le concede). Antes de restaurar se verifica que sea exactamente bd_anfeca_ensayo_pruebas_pg,
#     local, VACÍA, propiedad de rol_anfecalaravel, con la marca ANFECA_ENSAYO_AISLADO y con la misma
#     codificación y collation que la principal. Si algo no cumple, se rechaza sin tocarla.
#   - Nunca elimina ni recrea bases. La restauración es de una sola transacción: si falla, la base
#     de ensayo queda vacía.
#   - Herramientas de PostgreSQL 18 (/usr/pgsql-18/bin), con versión >= a la del servidor.
#   - Contraseña solicitada en pantalla, nunca guardada. Respaldos fuera del repositorio.
# =============================================================================
set -euo pipefail

PRINCIPAL="bd_laravelanfeca_pruebas"
COPIA="bd_anfeca_copia_pruebas_pg"
ENSAYO="bd_anfeca_ensayo_pruebas_pg"
ROL_ESPERADO="${ANFECA_ROL_ESPERADO:-rol_anfecalaravel}"   # variable solo para ensayos fuera del servidor
MARCA="ANFECA_ENSAYO_AISLADO:$ENSAYO"                       # COMMENT ON DATABASE que pone el administrador
PGBIN="${ANFECA_PGBIN:-/usr/pgsql-18/bin}"
APP="${ANFECA_APP:-/var/www/ANFECA_PRUEBAS}"   # variable solo para ensayos fuera del servidor
[[ $# -eq 0 ]] || { echo "Uso: bash scripts/anfeca/preparar_integracion.sh (sin opciones; la base de ensayo no se recrea desde aquí)" >&2; exit 1; }
FECHA="$(date +%Y%m%d_%H%M%S)"
SALIDA="$HOME/ANFECA_RESPALDOS/integracion/$FECHA"

falla() { echo; echo "ERROR: $*" >&2; exit 1; }
paso()  { echo; echo "=================== $* ==================="; }
env_de() { { grep -E "^$1=" "$APP/.env" || true; } | tail -1 | cut -d= -f2- | tr -d '"'"'"; }
art()   { php artisan "$@"; }                       # artisan contra la base de $DB_DATABASE (solo lecturas)
# Toda operación que ESCRIBE pasa por aquí: se niega si la base no es exactamente la de ensayo.
ensayo() {
    [[ "${DB_DATABASE:-}" == "$ENSAYO" && "$ENSAYO" != "$PRINCIPAL" && "$ENSAYO" != "$COPIA" ]] \
        || falla "Protección: '$*' solo se ejecuta en $ENSAYO (DB_DATABASE='${DB_DATABASE:-}')."
    if [[ "$1" == "php" ]]; then "$@"; else php artisan "$@"; fi
}

[[ -f artisan ]] || falla "Ejecute desde la raíz del worktree (/home/servidorweb/anfeca_f45)."
for h in psql pg_dump pg_restore; do [[ -x "$PGBIN/$h" ]] || falla "No existe $PGBIN/$h (herramientas de PostgreSQL 18)."; done
export PATH="$PGBIN:$PATH"   # huella_bd.sh y este script usan las herramientas de $PGBIN
[[ "$(pwd -P)" != /var/www/* ]] || falla "No se ejecuta dentro de /var/www."
for c in 450f69d e831add; do git merge-base --is-ancestor "$c" HEAD 2>/dev/null || falla "El worktree no contiene $c (use la rama integracion/pdm-despliegue)."; done
URL="${ANFECA_URL:-http://192.168.7.130:8084/login}"
[[ ! -f bootstrap/cache/config.php ]] || falla "Hay configuración en caché en el worktree: ejecute php artisan config:clear."
mkdir -p "$SALIDA" && chmod 700 "$HOME/ANFECA_RESPALDOS" "$HOME/ANFECA_RESPALDOS/integracion" "$SALIDA"
exec > >(tee -a "$SALIDA/preparacion.log") 2>&1

export PGHOST="$(env_de DB_HOST)" PGPORT="$(env_de DB_PORT)" PGUSER="$(env_de DB_USERNAME)"
[[ "$PGUSER" == "$ROL_ESPERADO" ]] || falla "DB_USERNAME es '$PGUSER'; se esperaba $ROL_ESPERADO."
case "$PGHOST" in 127.0.0.1|localhost|::1|/*) ;; *) falla "DB_HOST='$PGHOST' no es local (se admite 127.0.0.1, localhost, ::1 o un socket).";; esac
[[ "$(env_de DB_DATABASE)" == "$PRINCIPAL" ]] || falla "La aplicación no apunta a $PRINCIPAL."
read -r -s -p "Contraseña de PostgreSQL para $PGUSER: " PGPASSWORD; echo; export PGPASSWORD
trap 'unset PGPASSWORD DB_PASSWORD' EXIT
export DB_CONNECTION=pgsql DB_HOST="$PGHOST" DB_PORT="$PGPORT" DB_USERNAME="$PGUSER" DB_PASSWORD="$PGPASSWORD" DB_URL=""
q() { psql -d "$1" -X -q -At -v ON_ERROR_STOP=1 -c "$2"; }
# Lecturas de catálogo con transacción de solo lectura (para la principal).
ql() { psql -d "$1" -X -q -At -v ON_ERROR_STOP=1 -c "set default_transaction_read_only = on" -c "$2"; }

echo "Herramientas: $(psql --version) · $(pg_dump --version) · $(pg_restore --version)"
SERVIDOR_NUM="$(ql "$PRINCIPAL" "show server_version_num")"
HERRAMIENTA_NUM="$(pg_dump --version | grep -oE '[0-9]+(\.[0-9]+)?' | head -1 | cut -d. -f1)"
echo "Servidor: $(ql "$PRINCIPAL" "show server_version") · conexión: $(ql "$PRINCIPAL" "select coalesce(host(inet_server_addr()), 'socket local')")"
(( HERRAMIENTA_NUM >= SERVIDOR_NUM / 10000 )) || falla "pg_dump $HERRAMIENTA_NUM es anterior al servidor ($SERVIDOR_NUM): use las herramientas de PostgreSQL 18."
[[ "$(ql "$PRINCIPAL" "select coalesce(host(inet_server_addr()), 'socket')")" =~ ^(127\.0\.0\.1|::1|socket)$ ]] || falla "El servidor PostgreSQL no es local."

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

paso "4. Restauración de prueba en $ENSAYO (base creada por el administrador)"
[[ "$ENSAYO" == "bd_anfeca_ensayo_pruebas_pg" && "$ENSAYO" != "$PRINCIPAL" && "$ENSAYO" != "$COPIA" ]] || falla "Nombre de ensayo inválido."

instrucciones_admin() {
    cat <<TXT
Pida al administrador de PostgreSQL (no al rol de Laravel; no se concede CREATEDB) que la prepare:
  sudo -u postgres $PGBIN/psql -p ${PGPORT:-5432} -v ON_ERROR_STOP=1 \\
    -c "CREATE DATABASE $ENSAYO OWNER $ROL_ESPERADO TEMPLATE template0 ENCODING '$(ql "$PRINCIPAL" "select pg_encoding_to_char(encoding) from pg_database where datname = current_database()")' LC_COLLATE '$(ql "$PRINCIPAL" "select datcollate from pg_database where datname = current_database()")' LC_CTYPE '$(ql "$PRINCIPAL" "select datctype from pg_database where datname = current_database()")'" \\
    -c "COMMENT ON DATABASE $ENSAYO IS '$MARCA'"
TXT
}

# Todas las comprobaciones se leen del catálogo compartido (pg_database), sin conectarse aún al ensayo.
DATOS_BD="$(ql "$PRINCIPAL" "select pg_get_userbyid(d.datdba) || '|' || coalesce(shobj_description(d.oid, 'pg_database'), '') || '|' ||
    d.datistemplate || '|' || d.datallowconn || '|' || pg_encoding_to_char(d.encoding) || '|' || d.datcollate || '|' || d.datctype || '|' || d.datlocprovider::text
    from pg_database d where d.datname = '$ENSAYO'")"
[[ -n "$DATOS_BD" ]] || { instrucciones_admin; falla "$ENSAYO no existe."; }
REF_BD="$(ql "$PRINCIPAL" "select pg_encoding_to_char(d.encoding) || '|' || d.datcollate || '|' || d.datctype || '|' || d.datlocprovider::text
    from pg_database d where d.datname = current_database()")"
IFS='|' read -r E_DUENO E_MARCA E_PLANTILLA E_CONEXION E_COD E_COLL E_CTYPE E_PROV <<< "$DATOS_BD"
echo "Dueño: $E_DUENO · Marca: '${E_MARCA}' · Codificación/collation: $E_COD $E_COLL $E_CTYPE $E_PROV"
[[ "$E_DUENO" == "$ROL_ESPERADO" ]] || falla "$ENSAYO pertenece a '$E_DUENO', no a $ROL_ESPERADO. Rechazada."
[[ "$E_MARCA" == "$MARCA" ]] || { instrucciones_admin; falla "$ENSAYO no tiene la marca '$MARCA'. Rechazada."; }
[[ "$E_PLANTILLA" == "false" && "$E_CONEXION" == "true" ]] || falla "$ENSAYO es plantilla o no admite conexiones. Rechazada."
[[ "$E_COD|$E_COLL|$E_CTYPE|$E_PROV" == "$REF_BD" ]] || { instrucciones_admin; falla "Codificación/collation de $ENSAYO ($E_COD|$E_COLL|$E_CTYPE|$E_PROV) distinta de la principal ($REF_BD): la comparación de integridad no sería válida. Rechazada."; }

# Conexión al ensayo: confirma la base real y que está VACÍA (ningún objeto de usuario).
[[ "$(q "$ENSAYO" "select current_database()")" == "$ENSAYO" ]] || falla "La conexión no apunta a $ENSAYO."
OBJETOS="$(q "$ENSAYO" "select
    (select count(*) from pg_class c join pg_namespace n on n.oid = c.relnamespace
        where n.nspname not in ('pg_catalog', 'information_schema') and n.nspname not like 'pg_toast%' and n.nspname not like 'pg_temp%')
  + (select count(*) from pg_proc p join pg_namespace n on n.oid = p.pronamespace where n.nspname not in ('pg_catalog', 'information_schema'))
  + (select count(*) from pg_namespace n where n.nspname not in ('public', 'pg_catalog', 'information_schema') and n.nspname not like 'pg_toast%' and n.nspname not like 'pg_temp%')
  + (select count(*) from pg_extension where extname <> 'plpgsql')
  + (select count(*) from pg_type t join pg_namespace n on n.oid = t.typnamespace where n.nspname = 'public')")"
if [[ "$OBJETOS" != "0" ]]; then
    echo "$ENSAYO contiene $OBJETOS objeto(s): no está vacía (¿ensayo anterior?). No se elimina ni se reutiliza."
    echo "Para repetir el ensayo, el administrador debe eliminarla y crearla de nuevo:"
    echo "  sudo -u postgres $PGBIN/dropdb -p ${PGPORT:-5432} $ENSAYO"
    instrucciones_admin
    falla "Base de ensayo no vacía. Rechazada."
fi
echo "$ENSAYO verificada: local, vacía, propiedad de $ROL_ESPERADO, marcada y con la collation de la principal."

# Restauración sin CREATEDB: el rol es dueño de la base. Una sola transacción: si falla, queda vacía.
pg_restore --dbname="$ENSAYO" --no-owner --no-privileges --single-transaction --exit-on-error "$DUMP" \
    || falla "pg_restore falló; por ser de una sola transacción, $ENSAYO quedó vacía. Revise el error arriba."
[[ "$(ql "$PRINCIPAL" "select coalesce(shobj_description(oid, 'pg_database'), '') from pg_database where datname = '$ENSAYO'")" == "$MARCA" ]] \
    || falla "La marca de $ENSAYO cambió durante la restauración."
bash scripts/anfeca/huella_bd.sh "$ENSAYO" > "$SALIDA/huella_ensayo_restaurada.txt"
# La restauración se compara SIN tablas justificadas: debe ser idéntica.
if ANFECA_HUELLA_JUSTIFICADA="" bash scripts/anfeca/huella_bd.sh --comparar "$SALIDA/huella_principal_al_respaldar.txt" "$SALIDA/huella_ensayo_restaurada.txt" > "$SALIDA/dif_restauracion.txt"; then
    echo "RESTAURACIÓN VERIFICADA: estructura y $(grep -c '^DATOS' "$SALIDA/huella_ensayo_restaurada.txt") tablas idénticas a la principal."
else
    cat "$SALIDA/dif_restauracion.txt"
    falla "La restauración NO es equivalente a la principal. Procedimiento detenido (si hubo actividad de usuarios durante el respaldo, repita en un momento sin uso)."
fi
# public.migrations se excluye: es el único cambio esperado (registro de las migraciones PDM aplicadas).
grep '^DATOS public\.' "$SALIDA/huella_ensayo_restaurada.txt" | grep -v '^DATOS public\.migrations ' > "$SALIDA/public_ensayo_inicial.txt"

paso "5. ENSAYO F1–F5 en $ENSAYO"
export DB_DATABASE="$ENSAYO" ANFECA_PDM_MODO=apagado
[[ "$(art tinker --execute='echo DB::connection()->getDatabaseName();' 2>/dev/null | tail -1)" == "$ENSAYO" ]] || falla "Laravel no apunta a $ENSAYO."

echo "--- 5.1 Migraciones PDM (000001–000004) y catálogos"
ensayo migrate --path=database/migrations_pdm --force
ensayo db:seed --class='Database\Seeders\Pdm\CatalogosPdmSeeder' --force
echo "anfeca_modelo: $(q "$ENSAYO" "select count(*) from pg_tables where schemaname='anfeca_modelo'") tablas, $(q "$ENSAYO" "select count(*) from pg_constraint where contype='f' and connamespace='anfeca_modelo'::regnamespace") FK · anfeca_compat: $(q "$ENSAYO" "select count(*) from information_schema.views where table_schema='anfeca_compat'") vistas"

echo "--- 5.2 Plan F2"
ensayo anfeca:pdm-migrar --informe="$SALIDA/plan_f2.json" || true
BLOQ="$(php -r '$j = json_decode(file_get_contents($argv[1]), true); echo count(array_filter($j["incidencias"] ?? [], fn ($i) => $i["bloqueante"] ?? false));' "$SALIDA/plan_f2.json" 2>/dev/null || echo '?')"
echo "Incidencias bloqueantes: $BLOQ (detalle en $SALIDA/plan_f2.json)"
[[ "$BLOQ" =~ ^[0-9]+$ ]] || falla "No se pudo leer el plan F2 ($SALIDA/plan_f2.json)."
if [[ "$BLOQ" != "0" ]]; then
    echo; echo "ENSAYO DETENIDO en el plan: registre las decisiones pendientes (D-11/D-13/D-14) con fuente documentada"
    echo "en config/anfeca_pdm_decisiones.php; el administrador recrea la base de ensayo vacía y se repite. La base principal no se tocó."
    ETAPA="plan con bloqueos"
else
    echo "--- 5.3 Migración F2 + conciliación + equivalencia"
    ensayo anfeca:pdm-migrar --ejecutar --confirmar --informe="$SALIDA/migracion_f2.json"
    ensayo anfeca:pdm-conciliar
    ensayo anfeca:pdm-equivalencia

    echo "--- 5.4 Recorrido funcional en apagado (todos los usuarios con datos)"
    ensayo php scripts/anfeca/recorrido_lectura.php "$SALIDA/recorrido_apagado.json"

    echo "--- 5.5 Espejo (F3)"
    ANFECA_PDM_MODO=espejo ensayo anfeca:pdm-modo espejo --confirmar
    ensayo anfeca:pdm-conciliar

    echo "--- 5.6 Principal (F4)"
    ANFECA_PDM_MODO=principal ensayo anfeca:pdm-modo principal --confirmar
    ensayo anfeca:pdm-equivalencia
    ANFECA_PDM_MODO=principal ensayo php scripts/anfeca/recorrido_lectura.php "$SALIDA/recorrido_principal.json"

    echo "--- 5.7 Exclusivo (F5), exportación y reversión"
    ANFECA_PDM_MODO=exclusivo ensayo anfeca:pdm-modo exclusivo --confirmar
    ANFECA_PDM_MODO=exclusivo ensayo php scripts/anfeca/recorrido_lectura.php "$SALIDA/recorrido_exclusivo.json"
    ensayo anfeca:pdm-exportar-public --confirmar
    ANFECA_PDM_MODO=principal ensayo anfeca:pdm-modo principal --confirmar
    ensayo anfeca:pdm-modo apagado
    ensayo anfeca:pdm-conciliar
    ensayo anfeca:pdm-equivalencia

    echo "--- 5.8 Comparación de recorridos (la aplicación debe mostrar lo mismo en los tres modos)"
    php -r '$a = json_decode(file_get_contents($argv[1]), true); $ok = true;
        foreach (array_slice($argv, 2) as $f) { $b = json_decode(file_get_contents($f), true);
            $dif = array_keys(array_filter($a["paginas"], fn ($p, $k) => ($b["paginas"][$k] ?? null) !== $p, ARRAY_FILTER_USE_BOTH));
            printf("%s: %d páginas, %d distintas de apagado%s\n", $b["modo"], count($b["paginas"]), count($dif), $dif ? " → ".implode(", ", array_slice($dif, 0, 10)) : "");
            $ok = $ok && !$dif && count($b["paginas"]) === count($a["paginas"]); }
        printf("Apagado: %s\n", json_encode($a["resumen"]["codigos"])); exit($ok ? 0 : 1);' \
        "$SALIDA/recorrido_apagado.json" "$SALIDA/recorrido_principal.json" "$SALIDA/recorrido_exclusivo.json" \
        && echo "RECORRIDOS IDÉNTICOS" || falla "Comparación funcional FALLIDA: hay páginas distintas entre modos (revise $SALIDA/recorrido_*.json)."

    echo "--- 5.9 public del ensayo tras todo el ciclo"
    bash scripts/anfeca/huella_bd.sh "$ENSAYO" | grep '^DATOS public\.' | grep -v '^DATOS public\.migrations ' > "$SALIDA/public_ensayo_final.txt"
    diff "$SALIDA/public_ensayo_inicial.txt" "$SALIDA/public_ensayo_final.txt" && echo "public sin cambios en el ensayo (el ciclo F2–F5 no alteró ningún registro)." \
        || falla "public del ensayo cambió durante el ciclo F2–F5 (ver arriba). Procedimiento detenido."
    ETAPA="ciclo F2–F5 completo"
fi

paso "6. Base principal: huella final"
unset DB_DATABASE
bash scripts/anfeca/huella_bd.sh "$PRINCIPAL" > "$SALIDA/huella_principal_despues.txt"
bash scripts/anfeca/huella_bd.sh --comparar "$SALIDA/huella_principal_antes.txt" "$SALIDA/huella_principal_despues.txt" \
    && echo "BASE PRINCIPAL SIN CAMBIOS." \
    || falla "La huella de $PRINCIPAL cambió sin justificación (este script solo la lee: revise actividad externa). No se declara éxito."
CODIGO="$(curl -s -o /dev/null -w '%{http_code}' "$URL" || true)"
echo "Login $URL: HTTP $CODIGO"
[[ "$CODIGO" == "200" ]] || falla "El login no responde HTTP 200."

paso "RESULTADO"
echo "Respaldo: $DUMP"
echo "Ensayo: $ENSAYO · Etapa alcanzada: $ETAPA"
if [[ "$ETAPA" != "ciclo F2–F5 completo" ]]; then echo "RESULTADO: BLOQUEADO (ver arriba)."; exit 2; fi
echo "RESULTADO: PREPARACIÓN APROBADA (todas las verificaciones superadas)."
echo "Registros: $SALIDA"
