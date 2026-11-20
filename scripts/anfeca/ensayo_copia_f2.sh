#!/usr/bin/env bash
# =============================================================================
# ANFECA · Ensayo de F2 sobre una COPIA restaurada (bd_anfeca_copia_pruebas_pg)
#
# Uso (desde el worktree, NUNCA desde /var/www/ANFECA_PRUEBAS):
#   bash scripts/anfeca/ensayo_copia_f2.sh /ruta/respaldo.dump [plan|simular|completo] [--recrear]
#
#   plan      (por omisión) restaura, prepara el PDM en la copia y muestra el plan.
#   simular   además escribe, concilia y revierte (requiere decisiones sin bloqueos).
#   completo  además migra, concilia, verifica IDs, vacía (reversión) y comprueba public.
#
# Garantías:
#   - Solo escribe en bd_anfeca_copia_pruebas_pg (nombre fijo). En la base de trabajo
#     únicamente ejecuta SELECT de conteos para comparar.
#   - La contraseña se pide en la sesión y no se guarda.
#   - Todo queda en $HOME/ANFECA_RESPALDOS/ensayo_f2/<fecha>/ (permisos 700).
# =============================================================================
set -euo pipefail

DUMP="${1:-}"; MODO="${2:-plan}"; RECREAR="${3:-}"
COPIA="bd_anfeca_copia_pruebas_pg"; TRABAJO="bd_laravelanfeca_pruebas"
export PGHOST="${DB_HOST:-127.0.0.1}" PGPORT="${DB_PORT:-5432}" PGUSER="${DB_USERNAME:-rol_anfecalaravel}"
SALIDA="$HOME/ANFECA_RESPALDOS/ensayo_f2/$(date +%Y%m%d_%H%M%S)"

falla() { echo "ERROR: $*" >&2; exit 1; }
paso()  { echo; echo "=== $* ==="; }

[[ -f artisan ]] || falla "Ejecute el script desde la raíz del worktree."
[[ "$(pwd -P)" != /var/www/ANFECA_PRUEBAS* ]] || falla "No se permite ejecutar en /var/www/ANFECA_PRUEBAS."
[[ "$MODO" =~ ^(plan|simular|completo)$ ]] || falla "Modo no válido: $MODO"
[[ -n "$DUMP" && -s "$DUMP" ]] || falla "Indique un respaldo existente: $0 /ruta/respaldo.dump"

mkdir -p "$SALIDA" && chmod 700 "$HOME/ANFECA_RESPALDOS" "$HOME/ANFECA_RESPALDOS/ensayo_f2" "$SALIDA"
exec > >(tee -a "$SALIDA/ensayo.log") 2>&1

read -r -s -p "Contraseña de $PGUSER: " PGPASSWORD; echo; export PGPASSWORD
trap 'unset PGPASSWORD' EXIT

paso "1. Respaldo identificado y verificado"
echo "Archivo:   $DUMP"
echo "Fecha:     $(stat -c '%y' "$DUMP")"
echo "Tamaño:    $(stat -c '%s' "$DUMP") bytes"
echo "SHA-256:   $(sha256sum "$DUMP" | cut -d' ' -f1)"
TABLAS_DATOS=$(pg_restore -l "$DUMP" | grep -c "TABLE DATA" || true)
[[ "$TABLAS_DATOS" -gt 0 ]] || falla "pg_restore -l no lista datos: respaldo no válido."
echo "Tablas con datos en el respaldo: $TABLAS_DATOS"

paso "2. Base aislada $COPIA"
if psql -d postgres -Atc "select 1 from pg_database where datname = '$COPIA'" | grep -q 1; then
    [[ "$RECREAR" == "--recrear" ]] || falla "$COPIA ya existe. Use --recrear para reemplazarla (solo esa base)."
    read -r -p "Se eliminará y recreará $COPIA (solo la copia). Escriba RECREAR para continuar: " R
    [[ "$R" == "RECREAR" ]] || falla "Cancelado."
    dropdb "$COPIA"
fi
createdb "$COPIA" || falla "No se pudo crear $COPIA. Créela con: sudo -u postgres createdb -O $PGUSER $COPIA"
pg_restore --no-owner --no-privileges -d "$COPIA" "$DUMP" || echo "AVISO: pg_restore reportó advertencias (revise arriba)."

paso "3. ¿La copia corresponde a la base de trabajo actual? (conteos; solo SELECT en $TRABAJO)"
CONTEOS="select 'users', count(*) from public.users union all select 'datos_solicitantes', count(*) from public.datos_solicitantes
 union all select 'solicitudes_certificacion', count(*) from public.solicitudes_certificacion union all select 'datos_refrendo', count(*) from public.datos_refrendo
 union all select 'formaciones_academicas_solicitud', count(*) from public.formaciones_academicas_solicitud union all select 'documentos_probatorios_solicitud', count(*) from public.documentos_probatorios_solicitud
 union all select 'historial_documentos_academicos', count(*) from public.historial_documentos_academicos union all select 'certificados_historicos', count(*) from public.certificados_historicos
 union all select 'instituciones_afiliadas', count(*) from public.instituciones_afiliadas order by 1"
diff <(psql -d "$TRABAJO" -At -F ' ' -c "$CONTEOS") <(psql -d "$COPIA" -At -F ' ' -c "$CONTEOS") \
    && echo "Conteos idénticos: la copia es actual." \
    || echo "AVISO: la copia difiere de la base de trabajo (izquierda: trabajo; derecha: copia). Use un respaldo más reciente si importa."

paso "4. Conexión efectiva de Laravel hacia la copia"
php artisan config:clear >/dev/null
export DB_CONNECTION=pgsql DB_DATABASE="$COPIA" DB_HOST="$PGHOST" DB_PORT="$PGPORT" DB_USERNAME="$PGUSER" DB_PASSWORD="$PGPASSWORD" DB_URL=""
php artisan anfeca:pdm-conexion | tee "$SALIDA/conexion.txt"
[[ "$(psql -d "$COPIA" -Atc 'select current_database()')" == "$COPIA" ]] || falla "La conexión no apunta a la copia."

paso "5. PDM en la copia: esquema original, extensiones y catálogos"
N=$(psql -d "$COPIA" -Atc "select count(*) from pg_tables where schemaname = 'anfeca_modelo'")
if [[ "$N" == "0" ]]; then
    ANFECA_MODELO_BD_PERMITIDA="$COPIA" php artisan anfeca:modelo-instalar --ejecutar --confirmar
    N=36
fi
[[ "$N" == "36" ]] && php artisan migrate --path=database/migrations_pdm --force
php artisan db:seed --class="Database\\Seeders\\Pdm\\CatalogosPdmSeeder" --force
psql -d "$COPIA" -Atc "select 'tablas PDM: '||count(*) from pg_tables where schemaname='anfeca_modelo'"
psql -d "$COPIA" -Atc "select 'FK PDM: '||count(*) from pg_constraint where contype='f' and connamespace='anfeca_modelo'::regnamespace"

paso "6. Plan de migración (solo lectura)"
php artisan anfeca:pdm-migrar --informe="$SALIDA/plan.json" || true

if [[ "$MODO" == "simular" || "$MODO" == "completo" ]]; then
    paso "7. Simulación (escribe, concilia y revierte en la copia)"
    php artisan anfeca:pdm-migrar --simular --informe="$SALIDA/simulacion.json"
fi

if [[ "$MODO" == "completo" ]]; then
    paso "8. Migración en la copia + conciliación"
    HUELLA_ANTES=$(psql -d "$COPIA" -Atc "select md5(string_agg(x::text,'|' order by x.id)) from public.solicitudes_certificacion x")
    php artisan anfeca:pdm-migrar --ejecutar --confirmar --informe="$SALIDA/ejecucion.json"
    php artisan anfeca:pdm-conciliar

    paso "9. Identificadores conservados (public frente a PDM)"
    psql -d "$COPIA" -c "
      select 'personas = datos_solicitantes' as verificacion, (select count(*) from anfeca_modelo.persona p join public.datos_solicitantes d on d.id = p.pers_id_persona) as coinciden, (select count(*) from anfeca_modelo.persona) as en_pdm
      union all select 'usuarios = users', (select count(*) from anfeca_modelo.usuario u join public.users x on x.id = u.usua_id_usuario and x.password = u.usua_contrasenia), (select count(*) from anfeca_modelo.usuario)
      union all select 'solicitudes (id y folio)', (select count(*) from anfeca_modelo.solicitud s join public.solicitudes_certificacion x on x.id = s.soli_id_solicitud and s.soli_folio_solicitud = x.id), (select count(*) from anfeca_modelo.solicitud)
      union all select 'históricos (copia fiel)', (select count(*) from anfeca_modelo.historico_certificado_importacion h join public.certificados_historicos x on x.id = h.hcim_id_importacion and x.numero_certificado is not distinct from h.hcim_numero_certificado), (select count(*) from public.certificados_historicos)
      union all select 'autorizaciones', (select count(*) from anfeca_modelo.persona p join public.datos_solicitantes d on d.id = p.pers_id_persona and d.datos_autorizados = p.pers_datos_autorizados), (select count(*) from anfeca_modelo.persona)
      union all select 'compromisos (evento CA)', (select count(*) from anfeca_modelo.estado_solicitud where esso_siglas = 'CA'), (select count(*) from public.solicitudes_certificacion where compromiso_aceptado)
      union all select 'historial (sustituidos)', (select count(*) from anfeca_modelo.estado_solicitud_documento e join anfeca_modelo.estado_documento d on d.esdo_id_estado_documento = e.esdo_id_estado_documento where d.esdo_estado_documento = 'Sustituido'), (select count(*) from public.historial_documentos_academicos)"

    paso "10. Reversión probada: vaciar el PDM de la copia y comprobar public"
    php artisan anfeca:pdm-vaciar --confirmar
    HUELLA_DESPUES=$(psql -d "$COPIA" -Atc "select md5(string_agg(x::text,'|' order by x.id)) from public.solicitudes_certificacion x")
    [[ "$HUELLA_ANTES" == "$HUELLA_DESPUES" ]] && echo "public sin cambios (huella de solicitudes idéntica)." || falla "public cambió."
    psql -d "$COPIA" -Atc "select 'personas en PDM tras vaciar: '||count(*) from anfeca_modelo.persona"
fi

paso "Fin"
echo "Resultados en: $SALIDA"
echo "Al terminar el ensayo, elimine la copia (contiene datos personales): sudo -u postgres dropdb $COPIA"
