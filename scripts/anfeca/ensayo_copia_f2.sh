#!/usr/bin/env bash
# =============================================================================
# ANFECA · Ensayo F2 sobre una base nueva restaurada desde un respaldo verificado.
# ANFECA_ENSAYO_SHA256=<hash esperado> bash scripts/anfeca/ensayo_copia_f2.sh respaldo.dump [plan|simular|completo]
# Ejecutar desde un worktree y con un clúster PostgreSQL aislado seleccionado por DB_HOST/DB_PORT.
# PGPASSWORD puede estar en la sesión. Si no está definido se solicita interactivamente.
# Cada ejecución crea su propia base. Se conserva para revisar las evidencias.
# =============================================================================
set -euo pipefail
umask 077

DUMP="${1:-}"
MODO="${2:-plan}"
ESPERADO="${ANFECA_ENSAYO_SHA256:-}"
falla() { echo "ERROR: $*" >&2; exit 1; }
paso() { echo; echo "=== $* ==="; }

[[ -f artisan ]] || falla "Ejecute el script desde la raíz del worktree."
case "$(pwd -P)" in
    /var/www|/var/www/*) falla "Use un worktree fuera de /var/www." ;;
esac
[[ $# -le 2 && "$MODO" =~ ^(plan|simular|completo)$ ]] || falla "Use respaldo.dump [plan|simular|completo]."
[[ -n "$DUMP" && -s "$DUMP" ]] || falla "Indique un respaldo existente."
[[ "$ESPERADO" =~ ^[A-Fa-f0-9]{64}$ ]] || falla "Indique el SHA-256 del manifiesto en ANFECA_ENSAYO_SHA256."

export PGHOST="${DB_HOST:?Indique el host explicitamente}" PGPORT="${DB_PORT:?Indique el puerto del cluster aislado}"
export PGUSER="${DB_USERNAME:?Indique el rol del cluster aislado}"
[[ "$PGHOST" == 127.0.0.1 || "$PGHOST" == ::1 || "$PGHOST" == /* ]] || falla "Use loopback o el socket privado local."
unset PGSERVICE PGHOSTADDR PGOPTIONS
if [[ ! -v PGPASSWORD ]]; then
    read -r -s -p "Contraseña de $PGUSER: " PGPASSWORD
    echo
fi
export PGPASSWORD
trap 'unset PGPASSWORD DB_PASSWORD' EXIT

SALIDA=$(mktemp -d "${TMPDIR:-/tmp}/anfeca-validacion.XXXXXXXX")
exec > >(tee -a "$SALIDA/ensayo.log") 2>&1

paso "1. Comprobar respaldo y catálogo del archivo"
SHA=$(sha256sum < "$DUMP")
SHA=${SHA%% *}
[[ "$SHA" == "${ESPERADO,,}" ]] || falla "El SHA-256 del respaldo difiere del manifiesto."
pg_restore --list "$DUMP" > "$SALIDA/respaldo.toc" || falla "No se pudo leer el catálogo del respaldo."
grep -qE '^;[[:space:]]+dbname: (bd_anfeca_ensayo_pruebas_pg|bd_laravelanfeca_pruebas)$' "$SALIDA/respaldo.toc" ||
    falla "El catálogo del respaldo no identifica una base de pruebas de ANFECA."
grep -q 'TABLE DATA public solicitudes_certificacion ' "$SALIDA/respaldo.toc" || falla "No hay datos de solicitudes en el respaldo."
echo "$SHA" > "$SALIDA/respaldo.sha256"
echo "SHA-256 verificado: $SHA"

paso "2. Restaurar en una base nueva"
COPIA="bd_anfeca_copia_$(date -u +%Y%m%d%H%M%S)_${SALIDA##*.}_pruebas_pg"
createdb --template=template0 "$COPIA" || falla "No se creó la base nueva; se detiene el ensayo."
echo "Base creada: $COPIA"
pg_restore --exit-on-error --single-transaction --no-owner --no-privileges -d "$COPIA" "$DUMP" ||
    falla "La restauración falló. El ensayo termina antes de cualquier operación Laravel."
[[ "$(psql -X -v ON_ERROR_STOP=1 -d "$COPIA" -Atc 'select current_database()')" == "$COPIA" ]] ||
    falla "La conexión PostgreSQL no usa la copia."

paso "3. Fijar la conexión de Laravel y comprobar la copia"
export DB_CONNECTION=pgsql DB_DATABASE="$COPIA" DB_HOST="$PGHOST" DB_PORT="$PGPORT" DB_USERNAME="$PGUSER" DB_PASSWORD="$PGPASSWORD" DB_URL=""
export ANFECA_PDM_MODO=apagado APP_CONFIG_CACHE="$SALIDA/config-no-cache.php"

# Una copia tomada en modo activo contiene reglas capaces de intervenir escrituras durante el diagnóstico.
CONTROL=$(psql -X -v ON_ERROR_STOP=1 -d "$COPIA" -Atc "select to_regclass('anfeca_modelo.integracion_control') is not null")
if [[ "$CONTROL" == t ]]; then
    [[ -z "$(psql -X -v ON_ERROR_STOP=1 -d "$COPIA" -Atc "select ic_modo from anfeca_modelo.integracion_control where ic_modo <> 'apagado'")" ]] ||
        falla "El respaldo trae un modo PDM activo. Se conserva la copia para revisión."
fi
php artisan anfeca:pdm-conexion --no-interaction | tee "$SALIDA/conexion.txt"

paso "4. PDM en la copia: esquema original, extensiones y catálogos"
N=$(psql -X -v ON_ERROR_STOP=1 -d "$COPIA" -Atc "select count(*) from pg_tables where schemaname = 'anfeca_modelo'")
if [[ "$N" == "0" ]]; then
    ANFECA_MODELO_BD_PERMITIDA="$COPIA" php artisan anfeca:modelo-instalar --ejecutar --confirmar
fi
php artisan migrate --path=database/migrations_pdm --force
php artisan db:seed --class="Database\\Seeders\\Pdm\\CatalogosPdmSeeder" --force
psql -X -v ON_ERROR_STOP=1 -d "$COPIA" -Atc "select 'tablas PDM: '||count(*) from pg_tables where schemaname='anfeca_modelo'"
psql -X -v ON_ERROR_STOP=1 -d "$COPIA" -Atc "select 'FK PDM: '||count(*) from pg_constraint where contype='f' and connamespace='anfeca_modelo'::regnamespace"

paso "5. Plan de migración (solo lectura)"
php artisan anfeca:pdm-migrar --informe="$SALIDA/plan.json"

if [[ "$MODO" == "simular" || "$MODO" == "completo" ]]; then
    paso "6. Simulación (escribe, concilia y revierte en la copia)"
    php artisan anfeca:pdm-migrar --simular --informe="$SALIDA/simulacion.json"
fi

if [[ "$MODO" == "completo" ]]; then
    paso "7. Migración en la copia + conciliación"
    HUELLA_ANTES=$(psql -X -v ON_ERROR_STOP=1 -d "$COPIA" -Atc "select md5(string_agg(x::text,'|' order by x.id)) from public.solicitudes_certificacion x")
    php artisan anfeca:pdm-migrar --ejecutar --confirmar --informe="$SALIDA/ejecucion.json"
    php artisan anfeca:pdm-conciliar

    paso "8. Identificadores conservados (public frente a PDM)"
    psql -X -v ON_ERROR_STOP=1 -d "$COPIA" -c "
      select 'personas = datos_solicitantes' as verificacion, (select count(*) from anfeca_modelo.persona p join public.datos_solicitantes d on d.id = p.pers_id_persona) as coinciden, (select count(*) from anfeca_modelo.persona) as en_pdm
      union all select 'usuarios = users', (select count(*) from anfeca_modelo.usuario u join public.users x on x.id = u.usua_id_usuario and x.password = u.usua_contrasenia), (select count(*) from anfeca_modelo.usuario)
      union all select 'solicitudes (id y folio)', (select count(*) from anfeca_modelo.solicitud s join public.solicitudes_certificacion x on x.id = s.soli_id_solicitud and s.soli_folio_solicitud = x.id), (select count(*) from anfeca_modelo.solicitud)
      union all select 'históricos (copia fiel)', (select count(*) from anfeca_modelo.historico_certificado_importacion h join public.certificados_historicos x on x.id = h.hcim_id_importacion and x.numero_certificado is not distinct from h.hcim_numero_certificado), (select count(*) from public.certificados_historicos)
      union all select 'autorizaciones', (select count(*) from anfeca_modelo.persona p join public.datos_solicitantes d on d.id = p.pers_id_persona and d.datos_autorizados = p.pers_datos_autorizados), (select count(*) from anfeca_modelo.persona)
      union all select 'compromisos (evento CA)', (select count(*) from anfeca_modelo.estado_solicitud where esso_siglas = 'CA'), (select count(*) from public.solicitudes_certificacion where compromiso_aceptado)
      union all select 'historial (sustituidos)', (select count(*) from anfeca_modelo.estado_solicitud_documento e join anfeca_modelo.estado_documento d on d.esdo_id_estado_documento = e.esdo_id_estado_documento where d.esdo_estado_documento = 'Sustituido'), (select count(*) from public.historial_documentos_academicos)"

    paso "9. Reversión probada: vaciar el PDM de la copia y comprobar public"
    php artisan anfeca:pdm-vaciar --confirmar
    HUELLA_DESPUES=$(psql -X -v ON_ERROR_STOP=1 -d "$COPIA" -Atc "select md5(string_agg(x::text,'|' order by x.id)) from public.solicitudes_certificacion x")
    [[ "$HUELLA_ANTES" == "$HUELLA_DESPUES" ]] && echo "public sin cambios (huella de solicitudes idéntica)." || falla "public cambió."
    psql -X -v ON_ERROR_STOP=1 -d "$COPIA" -Atc "select 'personas en PDM tras vaciar: '||count(*) from anfeca_modelo.persona"
fi

paso "Fin"
echo "Resultados en: $SALIDA"
echo "Base conservada para revisión: $COPIA"
