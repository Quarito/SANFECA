# ANFECA · Funciones comunes de aplicación y reversión de la migración del catálogo de posgrados.
CAT_MIG=2026_11_27_000000_add_titulo_especifico_to_formaciones_academicas_solicitud
CAT_RUTA=database/migrations_propuestas/$CAT_MIG.php
CAT_COLUMNAS="'titulo_ultimo_grado_especifico','origen_titulo_ultimo_grado','clave_posgrado_catalogo','version_catalogo_posgrados'"
# Archivos cuya versión en disco debe ser IDÉNTICA a HEAD (correspondencia código ↔ commit autorizado).
CAT_ARCHIVOS="$CAT_RUTA app/Services/Catalogos app/Http/Controllers/FormacionAcademicaController.php app/Models/FormacionAcademicaSolicitud.php config/anfeca_catalogos.php"
detener() { echo "DETENIDO: $*" >&2; exit 1; }
valor() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
espacio() { df -Pm / | awk 'NR==2 {print $4}'; }

# Condiciones previas comunes: espacio, base, rama, commit, código idéntico a HEAD, sin caché, banderas y respaldo.
cat_precondiciones() {
    local base=$1 commit=$2 respaldo=$3
    [ "$(espacio)" -ge "${ANFECA_ESPACIO_MINIMO_MB:-5120}" ] || detener "menos de ${ANFECA_ESPACIO_MINIMO_MB:-5120} MB libres en / ($(espacio) MB)"
    [ "$(valor DB_DATABASE)" = "$base" ] || detener ".env no apunta a $base"
    [ "$(git rev-parse --abbrev-ref HEAD)" = desarrollo/modulo-coordinacion ] || detener "rama no autorizada"
    [ "$(git rev-parse --short=7 HEAD)" = "${commit:0:7}" ] || detener "commit distinto de $commit"
    [ -f "$CAT_RUTA" ] || detener "no existe $CAT_RUTA"
    # shellcheck disable=SC2086
    git diff --quiet HEAD -- $CAT_ARCHIVOS || detener "los archivos del catálogo difieren de HEAD"
    [ ! -f bootstrap/cache/config.php ] || detener "configuración en caché"
    BASE="$base" php -r 'require "vendor/autoload.php"; $a = require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
      exit((config("anfeca_pdm.modo") === "apagado" && config("anfeca_coordinacion.revision_documental") === false
            && config("anfeca_catalogos.posgrados.activo") === false
            && config("database.connections.".config("database.default").".database") === getenv("BASE")) ? 0 : 1);' \
      || detener "PDM no apagado, revisión documental o catálogo activos, o conexión efectiva distinta"
    grep -q "RESULTADO: respaldo restaurable y equivalente" "$respaldo/RESULTADO.txt" || detener "el respaldo indicado no está verificado"
    grep -q "base=$base " "$respaldo/RESULTADO.txt" || detener "el respaldo no corresponde a la base $base"
    grep -q "commit=${commit:0:7}" "$respaldo/RESULTADO.txt" || detener "el respaldo no corresponde al commit $commit"
    [ "$(find "$respaldo/RESULTADO.txt" -mmin -120 | wc -l)" -eq 1 ] || detener "el respaldo tiene más de 2 horas: genere uno nuevo"
    (cd "$respaldo" && sha256sum -c --quiet base.dump.sha256) || detener "SHA-256 del respaldo no coincide"
    export PGHOST="$(valor DB_HOST)" PGPORT="$(valor DB_PORT)" PGUSER="$(valor DB_USERNAME)" PGPASSWORD="$(valor DB_PASSWORD)" PGDATABASE="$base"
    anfeca_pg_preparar
}

cat_columnas_presentes() { psql -Atc "select count(*) from information_schema.columns where table_schema='public' and table_name='formaciones_academicas_solicitud' and column_name in ($CAT_COLUMNAS)"; }
# Huella de formaciones SOLO con las columnas ANTERIORES (no cambia por agregar o quitar las aditivas).
cat_huella_formaciones() { psql -Atc "select count(*)||'|'||md5(coalesce(string_agg((to_jsonb(x) - array[$CAT_COLUMNAS])::text, E'\n' order by x.id), '')) from public.formaciones_academicas_solicitud x"; }
cat_migraciones() { psql -Atc "select migration from migrations order by migration"; }

# Integridad documental por HTTP (antes/después). En una base desechable sin documentos puede omitirse explícitamente.
cat_documentos() {
    local salida=$1
    if [ -n "${ANFECA_URL_DOCUMENTOS:-}" ]; then
        bash "$(dirname "${BASH_SOURCE[0]}")/huellas_documentos.sh" "$ANFECA_URL_DOCUMENTOS" "$salida"
    elif [[ "$PGDATABASE" == *desechable* ]]; then
        echo "  · Huellas documentales omitidas: base desechable sin documentos" | tee "$salida"
    else
        detener "indique ANFECA_URL_DOCUMENTOS (p. ej., http://192.168.7.130:8086) para comparar los documentos"
    fi
}

# Compara huellas de TODAS las tablas: solo pueden cambiar migrations y la huella completa de formaciones
# (por las columnas agregadas o retiradas); el conteo y la huella de las columnas anteriores deben ser idénticos.
cat_comparar() {
    local d=$1 inesperadas
    diff "$d/huellas_antes.txt" "$d/huellas_despues.txt" > "$d/diferencias.txt" || true
    inesperadas=$( { grep -E '^[<>]' "$d/diferencias.txt" || true; } | { grep -vE '^[<>] public\.(migrations|formaciones_academicas_solicitud)\|' || true; } | wc -l)
    [ "$inesperadas" -eq 0 ] || detener "ALERTA: cambiaron otras tablas (ver $d/diferencias.txt)"
    cmp -s "$d/formaciones_antes.txt" "$d/formaciones_despues.txt" || detener "ALERTA: cambiaron datos existentes de formaciones"
    if [[ "$(head -1 "$d/documentos_antes.json")" == *omitidas* ]]; then echo "  · Documentos: comparación omitida (base desechable)"
    else cmp -s "$d/documentos_antes.json" "$d/documentos_despues.json" || detener "ALERTA: cambiaron las huellas de los documentos"
         echo "  ✔ Documentos idénticos ($(grep -c sha256 "$d/documentos_despues.json") huellas)"; fi
    echo "  ✔ Solo cambiaron migrations y la estructura de formaciones; datos existentes idénticos ($(cut -d'|' -f1 "$d/formaciones_despues.txt") formaciones)"
}
