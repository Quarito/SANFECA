#!/usr/bin/env bash
# =============================================================================
# ANFECA · Prueba INTEGRAL, en PostgreSQL 18.6 DESECHABLE, de los scripts de aplicación, verificación y
# reversión de la migración del catálogo de posgrados. Nunca usa la base de 8086 ni el sitio publicado.
#
#   bash scripts/anfeca/pruebas/probar_migracion_catalogo.sh <directorio_nuevo_fuera_de_/var/www>
#
# - Clon «git clone --shared» del commit actual (el repositorio original no se modifica) y vendor por reflink.
# - Pod Podman con PostgreSQL 18.6 en memoria, publicado SOLO en 127.0.0.1 (puerto aleatorio).
# - Datos FICTICIOS; respaldo verificado real (restauración) dentro del directorio de la prueba.
# - Escenarios: condiciones previas, aplicación, verificación, reaplicación rechazada, reversión rechazada con
#   datos, reversión con columnas vacías, reaplicación y protección de títulos con la bandera desactivada
#   tras revertir solo los commits de interfaz y bandera.
# Al terminar elimina el pod y el directorio (salvo ANFECA_CONSERVAR=1).
# =============================================================================
set -euo pipefail
DEST="${1:?directorio nuevo}"
ORIGEN=$(git -C "$(dirname "$0")" rev-parse --show-toplevel)
case "$(realpath -m "$DEST")" in /var/www/*) echo "SEGURIDAD: el destino no puede estar en /var/www" >&2; exit 1 ;; esac
[ -e "$DEST" ] && { echo "El destino ya existe: $DEST" >&2; exit 1; }
libre() { df -Pm / | awk 'NR==2 {print $4}'; }
echo "Espacio libre al inicio: $(libre) MB"
[ "$(libre)" -ge 5120 ] || { echo "DETENIDO: menos de 5 GB libres" >&2; exit 1; }

BASE=bd_anfeca_desechable_catalogo_pg; POD="anfeca-prueba-catalogo-$$"; PUERTO=$((55000 + RANDOM % 5000)); CLAVE=$(openssl rand -hex 16)
FALLAS=0; RESULTADOS=()
limpiar() { podman pod rm -f "$POD" >/dev/null 2>&1 || true; [ "${ANFECA_CONSERVAR:-0}" = 1 ] || podman unshare rm -rf -- "$DEST"; }
trap limpiar EXIT
caso() { local nombre=$1 esperado=$2; shift 2; local salida; set +e; salida=$("$@" 2>&1); local rc=$?; set -e
    local obtenido=$([ $rc -eq 0 ] && echo correcto || echo detenido)
    if [ "$obtenido" = "$esperado" ]; then RESULTADOS+=("✔ $nombre → $obtenido"); else RESULTADOS+=("✘ $nombre → $obtenido (esperado $esperado)"); FALLAS=$((FALLAS+1)); fi
    printf '%s\n' "$salida" > "$DEST/log_$(tr ' /' '__' <<< "$nombre").txt"; echo "== $nombre: $obtenido"; grep -E 'DETENIDO|ALERTA|✘|✔|Fallas' <<< "$salida" | head -20 | sed 's/^/   /' || true; }

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
sed -i -e 's/^APP_ENV=.*/APP_ENV=testing/' -e 's/^DB_CONNECTION=.*/DB_CONNECTION=pgsql/' "$DEST/app/.env"
sed -i -e '/^#\? *DB_\(HOST\|PORT\|DATABASE\|USERNAME\|PASSWORD\)=/d' .env
printf 'DB_HOST=127.0.0.1\nDB_PORT=%s\nDB_DATABASE=%s\nDB_USERNAME=rol_pruebas\nDB_PASSWORD=%s\nANFECA_PDM_MODO=apagado\nCACHE_STORE=array\nSESSION_DRIVER=array\n' "$PUERTO" "$BASE" "$CLAVE" >> .env
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
php artisan key:generate --force -q
php artisan migrate --force -q                 # ruta ordinaria (no incluye la propuesta)

# Datos FICTICIOS que imitan los casos reales (Maestría Informática / Contaduría / Otro «Economía», Licenciatura).
php -r 'require "vendor/autoload.php"; $a = require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  use Illuminate\Support\Facades\DB;
  if (!str_contains(DB::connection()->getDatabaseName(), "desechable")) { exit(1); }
  $i = DB::table("instituciones_afiliadas")->insertGetId(["numero_afiliacion" => "F01", "nombre" => "Institución ficticia"]);
  foreach ([["Maestría","Informática",null],["Maestría","Contaduría",null],["Maestría","Otro","Economía"],["Licenciatura","Administración",null],["Doctorado","Otro","Gestión ficticia"]] as $n => [$niv,$area,$otro]) {
    $u = DB::table("users")->insertGetId(["name" => "Ficticio $n", "email" => "ficticio$n@example.test", "password" => "x", "created_at" => now(), "updated_at" => now()]);
    $s = DB::table("solicitudes_certificacion")->insertGetId(["user_id" => $u, "tipo_tramite" => "inicial", "certificacion" => "ANFECA", "estado" => "enviado",
      "tipo_personal" => "Profesoras y profesores de Tiempo Completo", "area_certificacion" => "Administración", "created_at" => now(), "updated_at" => now()]);
    DB::table("formaciones_academicas_solicitud")->insert(["solicitud_id" => $s, "nivel_origen" => "Licenciatura", "titulo_origen" => "Administración",
      "nivel_ultimo_grado" => $niv, "titulo_ultimo_grado" => $area, "titulo_ultimo_grado_otro" => $otro, "institucion_afiliada_id" => $i]);
  }'
export PGHOST=127.0.0.1 PGPORT=$PUERTO PGUSER=rol_pruebas PGPASSWORD="$CLAVE" PGDATABASE=$BASE
source scripts/anfeca/pg_herramientas.inc.sh >/dev/null; anfeca_pg_preparar >/dev/null
source scripts/anfeca/catalogo_posgrados_comun.inc.sh
HUELLA_INICIAL=$(cat_huella_formaciones)

echo "== Respaldo verificado (restauración real) de la base desechable"
ANFECA_RESPALDOS_DIR="$DEST/respaldos" bash scripts/anfeca/respaldo_verificado.sh $BASE "$(git rev-parse --abbrev-ref HEAD)" "$COMMIT" | tail -2
RESPALDO=$(ls -d "$DEST"/respaldos/respaldo_verificado_* | tail -1)
echo "Espacio libre durante la prueba: $(libre) MB"

A=scripts/anfeca/aplicar_catalogo_posgrados.sh; R=scripts/anfeca/revertir_catalogo_posgrados.sh; V=scripts/anfeca/verificar_catalogo_posgrados.sh
caso "aplicar sin autorización" detenido bash $A $BASE "$COMMIT" "$RESPALDO"
caso "aplicar con otra base" detenido env ANFECA_AUTORIZO_MIGRACION=catalogo_posgrados bash $A bd_anfeca_ensayo_pruebas_pg "$COMMIT" "$RESPALDO"
caso "aplicar con otro commit" detenido env ANFECA_AUTORIZO_MIGRACION=catalogo_posgrados bash $A $BASE 0000000 "$RESPALDO"
echo "// cambio local" >> app/Services/Catalogos/TituloUltimoGrado.php
caso "aplicar con código distinto de HEAD" detenido env ANFECA_AUTORIZO_MIGRACION=catalogo_posgrados bash $A $BASE "$COMMIT" "$RESPALDO"
git checkout -q -- app/Services/Catalogos/TituloUltimoGrado.php
caso "aplicar con la bandera activa" detenido env ANFECA_AUTORIZO_MIGRACION=catalogo_posgrados ANFECA_CATALOGO_POSGRADOS=true bash $A $BASE "$COMMIT" "$RESPALDO"
caso "aplicar autorizada" correcto env ANFECA_AUTORIZO_MIGRACION=catalogo_posgrados bash $A $BASE "$COMMIT" "$RESPALDO"
caso "verificar" correcto bash $V
caso "reaplicar" detenido env ANFECA_AUTORIZO_MIGRACION=catalogo_posgrados bash $A $BASE "$COMMIT" "$RESPALDO"

ID=$(psql -Atc "select id from formaciones_academicas_solicitud where titulo_ultimo_grado='Informática' limit 1")
psql -qc "update formaciones_academicas_solicitud set titulo_ultimo_grado_especifico='Maestría ficticia en computación', origen_titulo_ultimo_grado='catalogo', clave_posgrado_catalogo='MAE-00000000000D', version_catalogo_posgrados='FICTICIO-1' where id=$ID"
caso "revertir sin autorización" detenido bash $R $BASE "$COMMIT" "$RESPALDO"
caso "revertir con títulos registrados" detenido env ANFECA_AUTORIZO_REVERSION=catalogo_posgrados bash $R $BASE "$COMMIT" "$RESPALDO"
[ "$(psql -Atc "select clave_posgrado_catalogo from formaciones_academicas_solicitud where id=$ID")" = MAE-00000000000D ] \
    && RESULTADOS+=("✔ el título registrado se conserva tras la reversión rechazada") || { RESULTADOS+=("✘ se perdió el título registrado"); FALLAS=$((FALLAS+1)); }

# Nivel 1 y 2 con la migración aplicada: bandera desactivada y reversión SOLO de interfaz y bandera (se conserva df9d4b3).
git -c user.name=prueba -c user.email=prueba@example.test revert --no-edit 4e89d36 85952ea >/dev/null
PROTECCION=$(ID=$ID php -r 'require "vendor/autoload.php"; $a = require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  config(["anfeca_catalogos.posgrados.activo" => false]);
  $f = App\Models\FormacionAcademicaSolicitud::find((int) getenv("ID"));
  $t = app(App\Services\Catalogos\TituloUltimoGrado::class);
  try { $t->protegerSinCatalogo(["nivel_ultimo_grado" => "Maestría", "titulo_ultimo_grado" => "Contaduría"], $f); echo "permitido"; }
  catch (Illuminate\Validation\ValidationException $e) { echo "rechazado"; }
  echo "|", App\Services\Catalogos\TituloUltimoGrado::presentar($f);')
[ "$PROTECCION" = "rechazado|Maestría ficticia en computación" ] \
    && RESULTADOS+=("✔ tras revertir interfaz y bandera: cambio de área rechazado y título exacto visible") \
    || { RESULTADOS+=("✘ protección tras revertir interfaz y bandera: $PROTECCION"); FALLAS=$((FALLAS+1)); }
git reset -q --hard "$COMMIT"   # solo en el clon desechable

psql -qc "update formaciones_academicas_solicitud set titulo_ultimo_grado_especifico=null, origen_titulo_ultimo_grado=null, clave_posgrado_catalogo=null, version_catalogo_posgrados=null where id=$ID"
caso "revertir con columnas vacías" correcto env ANFECA_AUTORIZO_REVERSION=catalogo_posgrados bash $R $BASE "$COMMIT" "$RESPALDO"
[ "$(cat_huella_formaciones)" = "$HUELLA_INICIAL" ] && RESULTADOS+=("✔ datos de formaciones idénticos al estado inicial") || { RESULTADOS+=("✘ los datos de formaciones cambiaron"); FALLAS=$((FALLAS+1)); }
caso "reaplicar tras revertir" correcto env ANFECA_AUTORIZO_MIGRACION=catalogo_posgrados bash $A $BASE "$COMMIT" "$RESPALDO"

echo; echo "===== RESULTADOS (commit $COMMIT, PostgreSQL $(psql -Atc 'show server_version')) ====="
printf '%s\n' "${RESULTADOS[@]}"
echo "Espacio libre al final (antes de limpiar): $(libre) MB"
echo "Fallas: $FALLAS"
exit $FALLAS
