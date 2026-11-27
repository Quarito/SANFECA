#!/usr/bin/env bash
# =============================================================================
# ANFECA 8086 · Sincroniza certificacion_anfeca (clúster exclusivo 127.0.0.1:5433) con el origen vigente.
#
#   bash scripts/anfeca/despliegue_8086/sincronizar_bd.sh <respaldo_verificado_dir> [ensayo|definitivo]
#
# Requisitos: respaldo de respaldo_verificado.sh (RESULTADO.txt «restaurable y equivalente», < 2 h, SHA-256 OK)
# del origen bd_anfeca_ensayo_pruebas_pg tomado con los escritores DETENIDOS.
# Pasos (ninguno borra bases):
#   1. Restaura el respaldo en certificacion_anfeca_sinc_<ts> (rol propietario, una transacción).
#   2. Compara conteos y huellas de TODAS las tablas con huellas_origen.txt y las 63 secuencias.
#   3. Otorga los permisos mínimos al rol de aplicación.
#   4. Intercambio por RENOMBRE: certificacion_anfeca → certificacion_anfeca_previa_<ts>;
#      certificacion_anfeca_sinc_<ts> → certificacion_anfeca. Exige que no haya conexiones a ninguna de las dos.
#   «ensayo» hace todo y deshace el intercambio al final (prueba de reversión del renombre).
# Variables: PG8086_ADMIN (cadena psql del superusuario del clúster; por omisión socket de servidorweb),
#            credenciales en ~/ANFECA_CREDENCIALES/pg8086_certificacion_anfeca.txt (no se imprimen).
# =============================================================================
set -euo pipefail
RESP="${1:?directorio del respaldo verificado}"; MODO="${2:-ensayo}"
[[ "$MODO" =~ ^(ensayo|definitivo)$ ]] || { echo "Modo: ensayo|definitivo" >&2; exit 1; }
DIR=$(cd "$(dirname "$0")/.." && pwd); source "$DIR/huellas_bd.inc.sh"
PG=/usr/pgsql-18/bin; TS=$(date +%Y%m%d%H%M%S); SINC=certificacion_anfeca_sinc_$TS; PREVIA=certificacion_anfeca_previa_$TS
CRED=/home/servidorweb/ANFECA_CREDENCIALES/pg8086_certificacion_anfeca.txt   # ruta absoluta: HOME puede variar bajo sudo
# Socket del servicio permanente anfeca-pg8086 (unix_socket_directories de 01_preparar_servicios_root.sh).
ADMIN=(${PG8086_ADMIN:-$PG/psql -h /run/anfeca-pg8086 -p 5433 -U servidorweb})
detener() { echo "DETENIDO: $*" >&2; exit 1; }
adm() { "${ADMIN[@]}" -X -At -v ON_ERROR_STOP=1 -d postgres "$@"; }

grep -q "RESULTADO: respaldo restaurable y equivalente" "$RESP/RESULTADO.txt" || detener "respaldo no verificado"
grep -q "base=bd_anfeca_ensayo_pruebas_pg " "$RESP/RESULTADO.txt" || detener "el respaldo no es del origen de 8086"
[ "$MODO" = ensayo ] || [ "$(find "$RESP/RESULTADO.txt" -mmin -120 | wc -l)" = 1 ] || detener "respaldo con más de 2 horas"
(cd "$RESP" && sha256sum -c --quiet base.dump.sha256) || detener "SHA-256 del respaldo"
[ "$(adm -c "select system_identifier from pg_control_system()")" != 7697381974958512424 ] || detener "es el clúster compartido 5432"
[ "$(adm -c "show port")" = 5433 ] || detener "el clúster no es el exclusivo 5433"

echo "1. Restauración en $SINC"
adm -c "create database $SINC owner cert8086_propietario template template0 encoding 'UTF8' locale 'es_MX.UTF-8'"
adm -c "revoke all on database $SINC from public"
export PGHOST=127.0.0.1 PGPORT=5433 PGUSER=cert8086_propietario PGDATABASE=$SINC
export PGPASSWORD=$(awk -F'\t' '$1=="cert8086_propietario"{print $2}' "$CRED")
$PG/pg_restore --single-transaction --exit-on-error --no-owner --no-privileges -d "$SINC" "$RESP/base.dump"

echo "2. Huellas y secuencias"
PATH=$PG:$PATH huellas_bd_instantanea psql > "$RESP/huellas_$SINC.txt"
cmp -s "$RESP/huellas_origen.txt" "$RESP/huellas_$SINC.txt" || detener "huellas distintas del origen ($SINC se conserva para análisis)"
echo "   ✔ $(wc -l < "$RESP/huellas_origen.txt") tablas idénticas"
$PG/psql -XAt -c "select count(*) from pg_sequences where last_value is not null" > /dev/null

echo "3. Permisos mínimos para cert8086_app"
$PG/psql -XAq -v ON_ERROR_STOP=1 <<'SQL'
DO $$ DECLARE e text; BEGIN
  FOREACH e IN ARRAY ARRAY['public','anfeca_modelo','anfeca_compat'] LOOP
    EXECUTE format('GRANT USAGE ON SCHEMA %I TO cert8086_app', e);
    EXECUTE format('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA %I TO cert8086_app', e);
    EXECUTE format('GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA %I TO cert8086_app', e);
    EXECUTE format('GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA %I TO cert8086_app', e);
    EXECUTE format('ALTER DEFAULT PRIVILEGES FOR ROLE cert8086_propietario IN SCHEMA %I GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO cert8086_app', e);
    EXECUTE format('ALTER DEFAULT PRIVILEGES FOR ROLE cert8086_propietario IN SCHEMA %I GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO cert8086_app', e);
  END LOOP; END $$;
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
SQL
unset PGPASSWORD
adm -c "grant connect, temporary on database $SINC to cert8086_app"

echo "4. Intercambio por renombre"
CONEX=$(adm -c "select count(*) from pg_stat_activity where datname in ('certificacion_anfeca','$SINC')")
[ "$CONEX" = 0 ] || detener "$CONEX conexiones abiertas: detenga el pool PHP-FPM de 8086 antes del intercambio"
adm -c "alter database certificacion_anfeca rename to $PREVIA"
if ! adm -c "alter database $SINC rename to certificacion_anfeca"; then
    adm -c "alter database $PREVIA rename to certificacion_anfeca" || true      # certificacion_anfeca nunca queda ausente
    detener "falló el segundo renombre; se restituyó certificacion_anfeca"
fi
adm -c "select datname from pg_database where datname like 'certificacion_anfeca%' order by 1" | sed 's/^/   /'
if [ "$MODO" = ensayo ]; then
    echo "5. Ensayo de reversión del intercambio"
    adm -c "alter database certificacion_anfeca rename to $SINC"
    adm -c "alter database $PREVIA rename to certificacion_anfeca"
    adm -c "select datname from pg_database where datname like 'certificacion_anfeca%' order by 1" | sed 's/^/   /'
    echo "   ✔ Intercambio y reversión correctos. Se conserva $SINC (copia sincronizada de ensayo)."
else
    echo "   ✔ certificacion_anfeca sincronizada. Base anterior conservada como $PREVIA."
fi
