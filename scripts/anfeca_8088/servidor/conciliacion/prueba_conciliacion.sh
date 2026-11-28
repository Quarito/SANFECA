#!/bin/bash
# =====================================================================================================================
# LABORATORIO · Prueba de propuesta_conciliacion_8088.sql sobre COPIAS NUEVAS de una base de simulación (nunca 8088).
# Variables: PG_BIN PGHOST PGPORT ADMIN ADMIN_CLAVE ROL ROL_CLAVE COPIA (ANFECA con vendor/) PHP SALIDA
#            BASE_SIM (base con el modelo oficial + simular_diferencias_8088.sql y datos ficticios) PREFIJO (cx)
# =====================================================================================================================
set -uo pipefail
: "${PG_BIN:?}" "${PGHOST:?}" "${PGPORT:?}" "${ADMIN:?}" "${ROL:?}" "${COPIA:?}" "${SALIDA:?}" "${BASE_SIM:?}"
PHP="${PHP:-php}"; PREFIJO="${PREFIJO:-cx}"; DIR="$(cd "$(dirname "$0")" && pwd)"; export PGHOST PGPORT
mkdir -p "$SALIDA"; : > "$SALIDA/resultados.txt"
adm() { PGPASSWORD="${ADMIN_CLAVE:-}" "$PG_BIN/psql" -X -q -U "$ADMIN" -d postgres -v ON_ERROR_STOP=1 -c "$1"; }
app() { local b=$1; shift; PGPASSWORD="${ROL_CLAVE:-}" "$PG_BIN/psql" -X -U "$ROL" -d "$b" "$@"; }
laravel() { (cd "$COPIA" && DB_DATABASE=$1 DB_HOST=$PGHOST DB_PORT=$PGPORT DB_USERNAME=$ROL DB_PASSWORD="${ROL_CLAVE:-}" "$PHP" "${@:2}"); }
esquema() { laravel "$1" artisan anfeca:verificar-esquema > /dev/null 2>&1; echo $?; }
instituciones() { laravel "$1" -r 'require "vendor/autoload.php"; $a = require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    try { echo "instituciones: ", count(app(App\Services\Anfeca\Instituciones::class)->lista()); } catch (Throwable $e) { echo "ERROR ", substr($e->getMessage(), 0, 90); }'; }
conc() { mkdir -p "$SALIDA/$2"; (cd "$DIR" && app "$1" -v ON_ERROR_STOP=1 -v exportar="$SALIDA/$2" "${@:3}" -f propuesta_conciliacion_8088.sql) > "$SALIDA/$2.log" 2>&1; echo $?; }
huella() { app "$1" -Atc "select md5(string_agg(t::text, ',' order by t::text)) from institucion t"; }
ok=0; n=0
r() { n=$((n+1)); local l; if [ "$2" = 0 ]; then ok=$((ok+1)); l="APROBADA $1${3:+ — $3}"; else l="FALLIDA  $1${3:+ — $3}"; fi; echo "$l" | tee -a "$SALIDA/resultados.txt"; }
nueva() { local b="${PREFIJO}_$1"; [ "$(adm "select 1" >/dev/null; PGPASSWORD="${ADMIN_CLAVE:-}" "$PG_BIN/psql" -X -At -U "$ADMIN" -d postgres -c "select count(*) from pg_database where datname='$b'")" = 0 ] \
    || { echo "DETENIDO: $b ya existe." >&2; exit 1; }; adm "create database $b owner $ROL template $BASE_SIM"; echo "$b"; }

D=$(nueva datos); NU=$(nueva nulos); LA=$(nueva largo); AP=$(nueva aplicar); SI=$(nueva simulacion)
app "$D" -q -c "update convocatoria set conv_anio = 2026, conv_id_certificacion = 1 where conv_id_convocatoria = (select min(conv_id_convocatoria) from convocatoria);
                update institucion set inst_campus = 'Campus Ficticio', inst_sede = 'Sede Ficticia' where inst_id_institucion = (select min(inst_id_institucion) from institucion)"
app "$NU" -q -c "update institucion set inst_zona = null where inst_id_institucion = (select max(inst_id_institucion) from institucion)"
app "$LA" -q -c "update tipo_convocatoria set tico_tipo_convocatoria = repeat('Descripción extensa ', 4) where tico_id_tipo_convocatoria = (select min(tico_id_tipo_convocatoria) from tipo_convocatoria)"

[ "$(esquema "$SI")" = 2 ]; r "La base de simulación presenta las diferencias (código 2)" $? "$(esquema "$SI")"
instituciones "$SI" | grep -q 42703; r "Antes de conciliar, la búsqueda de institución falla con 42703" $? "$(instituciones "$SI")"
h=$(huella "$SI"); rc=$(conc "$SI" simulacion); [ "$rc" = 0 ] && grep -q "SIMULACIÓN" "$SALIDA/simulacion.log" && grep -q "Tablas 36 · atributos 173 · llaves foráneas 35" "$SALIDA/simulacion.log" \
    && [ "$(esquema "$SI")" = 2 ] && [ "$h" = "$(huella "$SI")" ]
r "Por omisión simula: alcanza 36/173/35 dentro de la transacción y revierte todo" $? "rc=$rc"
h=$(huella "$D"); rc=$(conc "$D" datos_sin_autorizacion)
grep -q "DETENIDO: los atributos adicionales contienen 4 valores" "$SALIDA/datos_sin_autorizacion.log" && [ "$(esquema "$D")" = 2 ] && [ "$h" = "$(huella "$D")" ]
r "Con datos en atributos adicionales y sin autorización: se detiene sin cambios" $? "rc=$rc"
grep -q "2026,1" "$SALIDA/datos_sin_autorizacion/convocatoria_adicionales.csv" && grep -q "Campus Ficticio,Sede Ficticia" "$SALIDA/datos_sin_autorizacion/institucion_adicionales.csv"
r "Los datos quedan exportados a CSV antes de cualquier cambio" $?
rc=$(conc "$D" datos_autorizados -v retirar_con_datos=si -v confirmar=si)
[ "$rc" = 0 ] && [ "$(esquema "$D")" = 0 ] && grep -q "Campus Ficticio" "$SALIDA/datos_autorizados/institucion_adicionales.csv" \
    && (cd "$SALIDA/datos_autorizados" && sha256sum -c --quiet SHA256SUMS_conciliacion)
r "Con autorización: esquema oficial (código 0) y datos preservados en CSV con huella SHA-256" $? "rc=$rc"
[ "$(app "$D" -Atc "select count(*) from institucion where ins_coord_regional_cert_academi is not null")" = "$(app "$D" -Atc "select count(*) from institucion")" ]
r "La coordinación regional se conserva al renombrar la columna" $?
rc=$(conc "$NU" nulos -v confirmar=si); grep -q "DETENIDO: 1 instituciones" "$SALIDA/nulos.log" && [ "$(esquema "$NU")" = 2 ]
r "Institución con zona nula: se detiene sin cambios" $? "rc=$rc"
rc=$(conc "$LA" largo -v confirmar=si); grep -q "exceden 50 caracteres" "$SALIDA/largo.log" && [ "$(esquema "$LA")" = 2 ]
r "Tipo de convocatoria de más de 50 caracteres: se detiene sin cambios" $? "rc=$rc"
cuentas=$(app "$AP" -Atc "select (select count(*) from persona)||'/'||(select count(*) from solicitud)||'/'||(select count(*) from convocatoria)||'/'||(select count(*) from institucion)")
rc=$(conc "$AP" aplicar -v confirmar=si); [ "$rc" = 0 ] && [ "$(esquema "$AP")" = 0 ] \
    && [ "$cuentas" = "$(app "$AP" -Atc "select (select count(*) from persona)||'/'||(select count(*) from solicitud)||'/'||(select count(*) from convocatoria)||'/'||(select count(*) from institucion)")" ]
r "Aplicada: esquema oficial (código 0) y filas conservadas" $? "rc=$rc · persona/solicitud/convocatoria/institucion $cuentas"
rc=$(conc "$AP" repetida -v confirmar=si); [ "$rc" = 0 ] && [ "$(esquema "$AP")" = 0 ]; r "Repetida sobre la base conciliada: sin errores ni cambios" $? "rc=$rc"
instituciones "$AP" | grep -q "^instituciones: [1-9]"; r "Después de conciliar, la búsqueda de institución funciona" $? "$(instituciones "$AP")"
echo "Resultado: $ok/$n verificaciones aprobadas." | tee -a "$SALIDA/resultados.txt"
