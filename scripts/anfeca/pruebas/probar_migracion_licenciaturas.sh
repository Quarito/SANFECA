#!/usr/bin/env bash
# =============================================================================
# ANFECA · Prueba INTEGRAL, en PostgreSQL 18.6 DESECHABLE, de aplicación, verificación y reversión de la migración
# del catálogo de licenciaturas y la declaración de posgrado. Reproduce el estado de 8086: migración de posgrados ya
# aplicada, catálogo de posgrados ACTIVO y formaciones históricas (incluida una con título de posgrado del catálogo).
#
#   bash scripts/anfeca/pruebas/probar_migracion_licenciaturas.sh <directorio_nuevo_fuera_de_/var/www>
#
# Clon --shared del commit actual (el repositorio original no se modifica), pod con puerto solo en 127.0.0.1, datos
# FICTICIOS y respaldo verificado real dentro del directorio de la prueba. Al terminar elimina pod y directorio.
# =============================================================================
set -euo pipefail
DEST="${1:?directorio nuevo}"
ORIGEN=$(git -C "$(dirname "$0")" rev-parse --show-toplevel)
case "$(realpath -m "$DEST")" in /var/www/*) echo "SEGURIDAD: el destino no puede estar en /var/www" >&2; exit 1 ;; esac
[ -e "$DEST" ] && { echo "El destino ya existe: $DEST" >&2; exit 1; }
libre() { df -Pm / | awk 'NR==2 {print $4}'; }
echo "Espacio libre al inicio: $(libre) MB"
[ "$(libre)" -ge 5120 ] || { echo "DETENIDO: menos de 5 GB libres" >&2; exit 1; }

BASE=bd_anfeca_desechable_catalogo_pg; POD="anfeca-prueba-lic-$$"; PUERTO=$((55000 + RANDOM % 5000)); CLAVE=$(openssl rand -hex 16)
FALLAS=0; RESULTADOS=()
limpiar() { podman pod rm -f "$POD" >/dev/null 2>&1 || true; [ "${ANFECA_CONSERVAR:-0}" = 1 ] || podman unshare rm -rf -- "$DEST"; }
trap limpiar EXIT
caso() { local nombre=$1 esperado=$2; shift 2; local salida; set +e; salida=$("$@" 2>&1); local rc=$?; set -e
    local obtenido=$([ $rc -eq 0 ] && echo correcto || echo detenido)
    if [ "$obtenido" = "$esperado" ]; then RESULTADOS+=("✔ $nombre → $obtenido"); else RESULTADOS+=("✘ $nombre → $obtenido (esperado $esperado)"); FALLAS=$((FALLAS+1)); fi
    echo "== $nombre: $obtenido"; grep -E 'DETENIDO|ALERTA|✘|✔|Fallas' <<< "$salida" | head -14 | sed 's/^/   /' || true; }
verifica() { if [ "$2" = "$3" ]; then RESULTADOS+=("✔ $1"); else RESULTADOS+=("✘ $1 (esperado $3; obtenido $2)"); FALLAS=$((FALLAS+1)); fi; }

umask 077; mkdir -p "$DEST/respaldos"
podman pod create --name "$POD" -p "127.0.0.1:$PUERTO:5432" >/dev/null
podman run -d --pod "$POD" --name "$POD-pg" --tmpfs /var/lib/postgresql:rw,size=600m -e POSTGRES_USER=rol_pruebas \
    -e POSTGRES_PASSWORD="$CLAVE" -e POSTGRES_DB=$BASE docker.io/library/postgres:18.6 >/dev/null
for _ in $(seq 1 30); do podman exec "$POD-pg" pg_isready -q -U rol_pruebas -d $BASE && break; sleep 1; done; sleep 2

git clone -q --shared --branch "$(git -C "$ORIGEN" rev-parse --abbrev-ref HEAD)" "$ORIGEN" "$DEST/app"
cp -a --reflink=auto "$ORIGEN/vendor" "$DEST/app/vendor"
cd "$DEST/app"
COMMIT=$(git rev-parse --short HEAD)
cp .env.example .env
sed -i -e 's/^APP_ENV=.*/APP_ENV=testing/' -e 's/^DB_CONNECTION=.*/DB_CONNECTION=pgsql/' -e '/^#\? *DB_\(HOST\|PORT\|DATABASE\|USERNAME\|PASSWORD\)=/d' .env
printf 'DB_HOST=127.0.0.1\nDB_PORT=%s\nDB_DATABASE=%s\nDB_USERNAME=rol_pruebas\nDB_PASSWORD=%s\nANFECA_PDM_MODO=apagado\nCACHE_STORE=array\nSESSION_DRIVER=array\nANFECA_CATALOGO_POSGRADOS=true\n' "$PUERTO" "$BASE" "$CLAVE" >> .env
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
php artisan key:generate --force -q
php artisan migrate --force -q
php artisan migrate --force -q --path=database/migrations_propuestas/2026_11_27_000000_add_titulo_especifico_to_formaciones_academicas_solicitud.php

php -r 'require "vendor/autoload.php"; $a = require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  use Illuminate\Support\Facades\DB;
  if (!str_contains(DB::connection()->getDatabaseName(), "desechable")) { exit(1); }
  $i = DB::table("instituciones_afiliadas")->insertGetId(["numero_afiliacion" => "F01", "nombre" => "Institución ficticia"]);
  foreach ([["Maestría","Informática",null,[]],["Maestría","Otro","Economía",[]],["Licenciatura","Administración",null,[]],
            ["Maestría","Administración",null,["titulo_ultimo_grado_especifico"=>"Maestría ficticia","origen_titulo_ultimo_grado"=>"catalogo","clave_posgrado_catalogo"=>"MAE-00000000000A","version_catalogo_posgrados"=>"FICTICIO-1"]]] as $n => [$niv,$area,$otro,$extra]) {
    $u = DB::table("users")->insertGetId(["name" => "Ficticio $n", "email" => "lic$n@example.test", "password" => "x", "created_at" => now(), "updated_at" => now()]);
    $s = DB::table("solicitudes_certificacion")->insertGetId(["user_id" => $u, "tipo_tramite" => "inicial", "certificacion" => "ANFECA", "estado" => "enviado",
      "tipo_personal" => "Profesoras y profesores de Tiempo Completo", "area_certificacion" => "Administración", "created_at" => now(), "updated_at" => now()]);
    DB::table("formaciones_academicas_solicitud")->insert(array_merge(["solicitud_id" => $s, "nivel_origen" => "Licenciatura", "titulo_origen" => "Administración",
      "nivel_ultimo_grado" => $niv, "titulo_ultimo_grado" => $area, "titulo_ultimo_grado_otro" => $otro, "institucion_afiliada_id" => $i], $extra));
  }'
export PGHOST=127.0.0.1 PGPORT=$PUERTO PGUSER=rol_pruebas PGPASSWORD="$CLAVE" PGDATABASE=$BASE
source scripts/anfeca/pg_herramientas.inc.sh >/dev/null; anfeca_pg_preparar >/dev/null
CAT_COLUMNAS="'titulo_origen_especifico','origen_titulo_origen','clave_licenciatura_catalogo','version_catalogo_licenciaturas','cuenta_con_posgrado'"
source scripts/anfeca/catalogo_posgrados_comun.inc.sh
HUELLA_INICIAL=$(cat_huella_formaciones)
POSGRADO_INICIAL=$(psql -Atc "select string_agg(coalesce(clave_posgrado_catalogo,'-')||'|'||coalesce(version_catalogo_posgrados,'-'), ',' order by id) from formaciones_academicas_solicitud")

echo "== Respaldo verificado (restauración real) de la base desechable"
ANFECA_RESPALDOS_DIR="$DEST/respaldos" bash scripts/anfeca/respaldo_verificado.sh $BASE "$(git rev-parse --abbrev-ref HEAD)" "$COMMIT" | tail -1
RESPALDO=$(ls -d "$DEST"/respaldos/respaldo_verificado_* | tail -1)
echo "Espacio libre durante la prueba: $(libre) MB"

A=scripts/anfeca/aplicar_licenciaturas_posgrado.sh; R=scripts/anfeca/revertir_licenciaturas_posgrado.sh; V=scripts/anfeca/verificar_licenciaturas_posgrado.sh
caso "aplicar sin autorización" detenido bash $A $BASE "$COMMIT" "$RESPALDO"
caso "aplicar con la autorización de otra migración" detenido env ANFECA_AUTORIZO_MIGRACION=catalogo_posgrados bash $A $BASE "$COMMIT" "$RESPALDO"
caso "aplicar con la bandera de licenciaturas activa" detenido env ANFECA_AUTORIZO_MIGRACION=licenciaturas_posgrado ANFECA_CATALOGO_LICENCIATURAS=true bash $A $BASE "$COMMIT" "$RESPALDO"
caso "aplicar con la casilla de posgrado activa" detenido env ANFECA_AUTORIZO_MIGRACION=licenciaturas_posgrado ANFECA_DECLARACION_POSGRADO=true bash $A $BASE "$COMMIT" "$RESPALDO"
caso "aplicar con otro commit" detenido env ANFECA_AUTORIZO_MIGRACION=licenciaturas_posgrado bash $A $BASE 0000000 "$RESPALDO"
caso "aplicar autorizada (catálogo de posgrados ACTIVO, como en 8086)" correcto env ANFECA_AUTORIZO_MIGRACION=licenciaturas_posgrado bash $A $BASE "$COMMIT" "$RESPALDO"
caso "verificar" correcto bash $V
caso "verificar catálogo de posgrados (intacto)" correcto bash scripts/anfeca/verificar_catalogo_posgrados.sh
caso "reaplicar" detenido env ANFECA_AUTORIZO_MIGRACION=licenciaturas_posgrado bash $A $BASE "$COMMIT" "$RESPALDO"
verifica "registros existentes sin declaración (NULL, sin actualización retrospectiva)" "$(psql -Atc "select count(*) from formaciones_academicas_solicitud where cuenta_con_posgrado is null")" 4

ID=$(psql -Atc "select id from formaciones_academicas_solicitud where titulo_ultimo_grado='Informática' limit 1")
psql -qc "update formaciones_academicas_solicitud set titulo_origen_especifico='Licenciatura ficticia', origen_titulo_origen='manual', cuenta_con_posgrado=false where id=$ID"
caso "revertir sin autorización" detenido bash $R $BASE "$COMMIT" "$RESPALDO"
caso "revertir con datos registrados" detenido env ANFECA_AUTORIZO_REVERSION=licenciaturas_posgrado bash $R $BASE "$COMMIT" "$RESPALDO"
verifica "los datos registrados se conservan tras la reversión rechazada" "$(psql -Atc "select titulo_origen_especifico||'|'||cuenta_con_posgrado from formaciones_academicas_solicitud where id=$ID")" "Licenciatura ficticia|false"
psql -qc "update formaciones_academicas_solicitud set titulo_origen_especifico=null, origen_titulo_origen=null, cuenta_con_posgrado=null where id=$ID"
caso "revertir con columnas vacías" correcto env ANFECA_AUTORIZO_REVERSION=licenciaturas_posgrado bash $R $BASE "$COMMIT" "$RESPALDO"
verifica "datos de formaciones idénticos al estado inicial" "$(cat_huella_formaciones)" "$HUELLA_INICIAL"
verifica "columnas y datos del catálogo de posgrados intactos" "$(psql -Atc "select string_agg(coalesce(clave_posgrado_catalogo,'-')||'|'||coalesce(version_catalogo_posgrados,'-'), ',' order by id) from formaciones_academicas_solicitud")" "$POSGRADO_INICIAL"
caso "verificar catálogo de posgrados tras revertir" correcto bash scripts/anfeca/verificar_catalogo_posgrados.sh
caso "reaplicar tras revertir" correcto env ANFECA_AUTORIZO_MIGRACION=licenciaturas_posgrado bash $A $BASE "$COMMIT" "$RESPALDO"

echo; echo "===== RESULTADOS (commit $COMMIT, PostgreSQL $(psql -Atc 'show server_version')) ====="
printf '%s\n' "${RESULTADOS[@]}"
echo "Espacio libre al final (antes de limpiar): $(libre) MB"
echo "Fallas: $FALLAS"
exit $FALLAS
