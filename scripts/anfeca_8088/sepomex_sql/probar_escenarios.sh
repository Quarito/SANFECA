#!/bin/bash
# =====================================================================================================================
# ANFECA · Validación REAL de los scripts SQL del catálogo SEPOMEX en PostgreSQL, sobre bases NUEVAS y desechables.
# Cada escenario crea su propia base (prefijo $PREFIJO), instala el modelo oficial de 36 tablas y ejecuta los scripts
# con psql. Nunca reutiliza, modifica ni borra una base existente: si un nombre ya existe, se detiene.
# Solo datos ficticios (los domicilios y solicitantes de E8 los crea anfeca:laboratorio en la copia de prueba).
#
# Variables:
#   PG_BIN PGHOST PGPORT              cliente psql y servidor de PRUEBAS (nunca el institucional)
#   ADMIN ADMIN_CLAVE                 rol con CREATEDB (solo para crear bases nuevas y para la prueba de interrupción)
#   ROL ROL_CLAVE                     rol de la aplicación, propietario de las bases de prueba; ejecuta las cargas
#   PAQUETE                           directorio del paquete generado (contiene sql/)
#   MODELO                            SQL oficial del modelo de 36 tablas
#   COPIA PHP SEPOMEX SEPOMEX_SHA256  copia de prueba de ANFECA (Artisan) y archivo oficial (comparación con Artisan)
#   PREFIJO (sx) SALIDA               prefijo de las bases nuevas y directorio de evidencias
# =====================================================================================================================
set -uo pipefail
: "${PG_BIN:?}" "${PGHOST:?}" "${PGPORT:?}" "${ADMIN:?}" "${ROL:?}" "${PAQUETE:?}" "${MODELO:?}" "${COPIA:?}" "${SALIDA:?}" "${SEPOMEX:?}" "${SEPOMEX_SHA256:?}"
PHP="${PHP:-php}"; PREFIJO="${PREFIJO:-sx}"; SQL="$PAQUETE/sql"; DIR="$(cd "$(dirname "$0")" && pwd)"
export PGHOST PGPORT
mkdir -p "$SALIDA"; RES="$SALIDA/resultados.txt"; : > "$RES"
ok=0; total=0
prueba() { total=$((total+1)); if [ "$2" = 0 ]; then ok=$((ok+1)); echo "APROBADA $1${3:+ — $3}" | tee -a "$RES"; else echo "FALLIDA  $1${3:+ — $3}" | tee -a "$RES"; fi; }
adm() { PGPASSWORD="${ADMIN_CLAVE:-}" "$PG_BIN/psql" -X -q -U "$ADMIN" -d postgres -v ON_ERROR_STOP=1 "$@"; }
app() { local b=$1; shift; PGPASSWORD="${ROL_CLAVE:-}" "$PG_BIN/psql" -X -U "$ROL" -d "$b" "$@"; }
val() { app "$1" -Atc "$2"; }
conteos() { val "$1" "select (select count(*) from entidad_federativa)||'/'||(select count(*) from alcaldia_municipio)||'/'||(select count(*) from colonia)"; }
nueva() {
    local b="${PREFIJO}_$1"
    [ "$(adm -Atc "select count(*) from pg_database where datname = '$b'")" = 0 ] || { echo "DETENIDO: $b ya existe (no se reutiliza ni se borra)." >&2; exit 1; }
    adm -c "create database $b owner $ROL encoding 'UTF8' template template0" >&2
    app "$b" -q -v ON_ERROR_STOP=1 -f "$MODELO" >/dev/null
    [ -n "${2:-}" ] && app "$b" -q -v ON_ERROR_STOP=1 -v escenario="$2" -f "$DIR/pruebas.sql" >/dev/null
    echo "$b"
}
cargar() { local d="${3:-$SQL}"; (cd "$d" && app "$1" -v ON_ERROR_STOP=1 -f SEPOMEX_CATALOGO_COMPLETO.sql > "$SALIDA/$2.log" 2>&1); }
insertados() { grep -a "^Insertados ·" "$SALIDA/$1.log" | tail -1; }
marcas() { grep -a "^Marcas de reversión" "$SALIDA/$1.log" | tail -1 | sed 's/^.*): //'; }
artisan() { (cd "$COPIA" && env DB_DATABASE="$1" DB_HOST="$PGHOST" DB_PORT="$PGPORT" DB_USERNAME="$ROL" DB_PASSWORD="${ROL_CLAVE:-}" \
    "$PHP" -d memory_limit=1024M artisan "${@:2}"); }
# Contenido representado en el modelo: id y nombre de la entidad, municipio, código postal y colonia.
exportar() { val "$1" "select fila from (select e.enfe_id_entidad_federativa||'|'||e.enfe_nombre||'|'||a.almu_nombre||'|'||c.colo_codigo_postal||'|'||c.colo_nombre as fila
    from colonia c join alcaldia_municipio a on a.almu_id_alcaldia = c.colo_id_alcaldia join entidad_federativa e on e.enfe_id_entidad_federativa = a.almu_id_entidad_federativa) x
    order by fila collate \"C\"" > "$SALIDA/$2"; [ -s "$SALIDA/$2" ] || echo "AVISO: exportación vacía de $1" >&2; }
# Huella de las tablas del solicitante (todo lo que NO es catálogo geográfico).
huella_ajena() { val "$1" "select count(*)||' filas · '||md5(string_agg(t, E'\n' order by t)) from (
    select 'persona'||(x.*)::text t from persona x union all select 'usuario'||(x.*)::text from usuario x
    union all select 'domicilio'||(x.*)::text from domicilio x union all select 'comunicacion'||(x.*)::text from comunicacion x
    union all select 'solicitud'||(x.*)::text from solicitud x union all select 'estado'||(x.*)::text from estado_solicitud x
    union all select 'estudio'||(x.*)::text from estudio_profesional x union all select 'documento'||(x.*)::text from solicitud_documento x
    union all select 'privilegio'||(x.*)::text from privilegio x) y"; }
fallo_controlado() {   # $1 base, $2 nombre, $3 conteos esperados sin cambio, $4 texto esperado
    local antes; antes=$(conteos "$1"); cargar "$1" "$2" "${6:-}"; local r=$?
    [ $r != 0 ] && grep -aq "$4" "$SALIDA/$2.log" && [ "$(conteos "$1")" = "$antes" ] && [ "$antes" = "$3" ]
    prueba "$5" $? "rc=$r · $(grep -a "ERROR" "$SALIDA/$2.log" | head -1 | sed 's/^psql:[^E]*//') · conteos $antes → $(conteos "$1")"
}

echo "== E1 · Base nueva con el modelo oficial vacío"
B1=$(nueva e1_vacia); t0=$(date +%s.%N); cargar "$B1" e1_carga; r=$?; t1=$(date +%s.%N)
prueba "E1 carga completa en base vacía ($B1)" $r "$(insertados e1_carga) · $(awk "BEGIN {printf \"%.1f\", $t1 - $t0}") s totales"
[ "$(conteos "$B1")" = "32/2478/158507" ]; prueba "E1 conteos 32/2478/158507" $? "$(conteos "$B1")"
[ "$(grep -ac '| coincide$' "$SALIDA/e1_carga.log")" = 32 ]; prueba "E1 huella exacta del catálogo en las 32 entidades (04)" $?
[ "$(val "$B1" "select count(distinct colo_codigo_postal) from colonia where colo_codigo_postal ~ '^0'")" = 685 ]
prueba "E11 códigos postales con cero inicial conservados (685 distintos)" $?
val "$B1" "select c.colo_codigo_postal||' '||a.almu_nombre||', '||e.enfe_nombre||': '||string_agg(c.colo_nombre, ', ' order by c.colo_nombre) from colonia c join alcaldia_municipio a on a.almu_id_alcaldia=c.colo_id_alcaldia
  join entidad_federativa e on e.enfe_id_entidad_federativa=a.almu_id_entidad_federativa where c.colo_codigo_postal in ('01000','04510','06600','09000') group by c.colo_codigo_postal, a.almu_nombre, e.enfe_nombre order by c.colo_codigo_postal" > "$SALIDA/e11_cp.txt"
grep -q "^01000 Álvaro Obregón, Ciudad de México" "$SALIDA/e11_cp.txt" && grep -q "^04510 Coyoacán, Ciudad de México" "$SALIDA/e11_cp.txt"
prueba "E11 consulta de 01000, 04510, 06600 y 09000" $? "$(tr '\n' ';' < "$SALIDA/e11_cp.txt" | cut -c1-160)"
artisan "$B1" anfeca:verificar-esquema > "$SALIDA/e12_esquema.txt" 2>&1; grep -q "Sin diferencias" "$SALIDA/e12_esquema.txt"
prueba "E12 esquema de 36 tablas idéntico al SQL oficial tras la carga" $? "$(grep -a 'Tablas' "$SALIDA/e12_esquema.txt")"
grep -aA13 "== 3. Integridad" "$SALIDA/e1_carga.log" | grep -a '|' | grep -av revision | awk -F'|' '{s+=$2} END {exit s!=0}'
prueba "E9 integridad referencial: huérfanos, duplicados, CP inválidos, FK validadas y secuencias = 0" $?

echo "INFO espacio: tres tablas $(val "$B1" "select pg_size_pretty(sum(pg_total_relation_size(t::regclass))) from unnest(array['entidad_federativa','alcaldia_municipio','colonia']) t") (datos e índices) · base completa $(val "$B1" "select pg_size_pretty(pg_database_size(current_database()))") · paquete sql/ $(du -sh "$SQL" | cut -f1)" | tee -a "$RES"
# Memoria: pico del cliente psql (getrusage) y del proceso servidor de la sesión (VmHWM, muestreado al final de la carga).
B1m=$(nueva e1m_memoria)
(cd "$SQL" && PGPASSWORD="${ROL_CLAVE:-}" python3 -I - "$PG_BIN/psql" "$ROL" "$B1m" "$SALIDA/e1m_carga.log" "$ADMIN" "${ADMIN_CLAVE:-}" <<'PY' | tee -a "$RES"
import resource, subprocess, sys, time, os
psql, rol, base, log, adm, clave = sys.argv[1:7]
t = time.time(); p = subprocess.Popen([psql, '-X', '-U', rol, '-d', base, '-v', 'ON_ERROR_STOP=1', '-f', 'SEPOMEX_CATALOGO_COMPLETO.sql'],
                                      stdout=open(log, 'w'), stderr=subprocess.STDOUT)
pico = 0
while p.poll() is None:
    try:
        env = dict(os.environ, PGPASSWORD=clave)
        pid = subprocess.run([psql, '-X', '-At', '-U', adm, '-d', 'postgres', '-c', f"select pid from pg_stat_activity where datname = '{base}' and backend_type = 'client backend' limit 1"],
                             capture_output=True, text=True, env=env).stdout.strip()
        if pid:
            for l in open(f'/proc/{pid}/status'):
                if l.startswith('VmHWM'):
                    pico = max(pico, int(l.split()[1]))
    except Exception:
        pass
    time.sleep(0.2)
c = resource.getrusage(resource.RUSAGE_CHILDREN).ru_maxrss
print(f"INFO memoria: cliente psql pico {c/1024:.0f} MB · proceso servidor de la sesión pico {pico/1024:.0f} MB · {time.time()-t:.1f} s totales (rc={p.returncode})")
PY
)

echo "== E6 · Ejecución consecutiva del mismo archivo"
cargar "$B1" e6_repeticion; r=$?
[ $r = 0 ] && insertados e6_repeticion | grep -q "entidades: 0 · municipios: 0 · colonias: 0"; prueba "E6 segunda ejecución del maestro no inserta nada" $? "$(insertados e6_repeticion)"
for f in 01_insert_entidad_federativa 02_insert_alcaldia_municipio 03_insert_colonia; do
    (cd "$SQL" && app "$B1" -v ON_ERROR_STOP=1 -f $f.sql > "$SALIDA/e6_$f.log" 2>&1); r=$?
    [ $r = 0 ] && grep -aq "^Insertados en .*: 0$" "$SALIDA/e6_$f.log"; prueba "E6 $f.sql ejecutado de nuevo por separado: 0 insertados" $? "$(grep -a '^Insertados en' "$SALIDA/e6_$f.log")"
done
[ "$(conteos "$B1")" = "32/2478/158507" ]; prueba "E6 conteos sin cambio tras cuatro ejecuciones adicionales" $? "$(conteos "$B1")"

echo "== E1b · Carga por archivos separados (01, 02 y 03, cada uno en su transacción)"
B1b=$(nueva e1b_separados)
for f in 01_insert_entidad_federativa 02_insert_alcaldia_municipio 03_insert_colonia; do
    (cd "$SQL" && app "$B1b" -v ON_ERROR_STOP=1 -f $f.sql > "$SALIDA/e1b_$f.log" 2>&1) || break
done
exportar "$B1" e1_export.txt; exportar "$B1b" e1b_export.txt
[ -s "$SALIDA/e1_export.txt" ] && cmp -s "$SALIDA/e1_export.txt" "$SALIDA/e1b_export.txt"; prueba "E1b la carga por archivos separados produce el mismo contenido que el maestro" $? "$(conteos "$B1b")"

echo "== E2 · Base con las 32 entidades previamente registradas (mayúsculas sin acentos, ids 1–32)"
B2=$(nueva e2_entidades e2); antes=$(val "$B2" "select string_agg(enfe_id_entidad_federativa||enfe_nombre, ',' order by 1) from entidad_federativa")
cargar "$B2" e2_carga; r=$?
[ $r = 0 ] && insertados e2_carga | grep -q "entidades: 0 · municipios: 2478 · colonias: 158507" \
  && [ "$antes" = "$(val "$B2" "select string_agg(enfe_id_entidad_federativa||enfe_nombre, ',' order by 1) from entidad_federativa")" ]
prueba "E2 reutiliza las 32 entidades existentes sin modificar nombres ni identificadores" $? "$(insertados e2_carga)"

echo "== E3 · Base con municipios y colonias previamente registrados"
B3=$(nueva e3_previos e3); previos=$(val "$B3" "select string_agg(almu_id_alcaldia||almu_nombre, ',' order by 1) from alcaldia_municipio")
cargar "$B3" e3_carga; r=$?
[ $r = 0 ] && insertados e3_carga | grep -q "entidades: 0 · municipios: 2475 · colonias: 158505" && [ "$(conteos "$B3")" = "32/2478/158508" ] \
  && [ "$previos" = "$(val "$B3" "select string_agg(almu_id_alcaldia||almu_nombre, ',' order by 1) from alcaldia_municipio where almu_id_alcaldia <= 3")" ] \
  && [ "$(val "$B3" "select count(*) from colonia where colo_nombre = 'Colonia Ficticia de Prueba' and colo_id_colonia = 3")" = 1 ]
prueba "E3 inserta solo lo faltante y conserva los municipios y colonias previos (incluida una ajena al catálogo)" $? "$(insertados e3_carga) · $(conteos "$B3")"
grep -aq "Jalisco .*completa, con registros adicionales" "$SALIDA/e3_carga.log"; prueba "E3 la verificación distingue la entidad con registros adicionales" $?

echo "== E4 · Identificadores distintos a los del laboratorio y secuencias atrasadas"
B4=$(nueva e4_ids e4); cargar "$B4" e4_carga; r=$?
[ $r = 0 ] && insertados e4_carga | grep -q "entidades: 16 · municipios: 2475 · colonias: 158507" && [ "$(conteos "$B4")" = "32/2478/158508" ] \
  && [ "$(val "$B4" "select enfe_id_entidad_federativa from entidad_federativa where enfe_nombre = 'Jalisco'")" = 30 ] \
  && [ "$(val "$B4" "select count(*) from colonia c join alcaldia_municipio a on a.almu_id_alcaldia = c.colo_id_alcaldia where a.almu_id_alcaldia = 90000")" -gt 0 ]
prueba "E4 resuelve referencias con los ids reales (Jalisco = 30, Guadalajara = 90000) y ajusta secuencias sin colisiones" $? "$(insertados e4_carga) · entidades nuevas $(val "$B4" "select min(enfe_id_entidad_federativa)||'–'||max(enfe_id_entidad_federativa) from entidad_federativa where enfe_id_entidad_federativa > 32")"
val "$B4" "select (select last_value from entidad_federativa_enfe_id_entidad_federativa_seq) >= (select max(enfe_id_entidad_federativa) from entidad_federativa)
  and (select last_value from alcaldia_municipio_almu_id_alcaldia_seq) >= (select max(almu_id_alcaldia) from alcaldia_municipio)
  and (select last_value from colonia_colo_id_colonia_seq) >= (select max(colo_id_colonia) from colonia)" | grep -q t
prueba "E4 secuencias SERIAL iguales o mayores al máximo (nunca disminuidas)" $?
artisan "$B4" anfeca:verificar-esquema 2>&1 | grep -q "Sin diferencias"; prueba "E12 esquema de 36 tablas intacto en E4" $?

echo "== E5 · Conflictos y coincidencias ambiguas (la carga se detiene sin cambios)"
fallo_controlado "$(nueva e5a_municipio_duplicado e5a)" e5a_carga "32/2/0" "SEPOMEX DETENIDO: ALCALDIA_MUNICIPIO con nombres duplicados" "E5a municipio duplicado en la base"
fallo_controlado "$(nueva e5b_clave_ocupada e5b)" e5b_carga "1/0/0" "SEPOMEX DETENIDO: ENTIDAD_FEDERATIVA 9 es «Entidad Ficticia de Prueba»" "E5b clave oficial ocupada por una entidad ajena al catálogo"
fallo_controlado "$(nueva e5c_grafia e5c)" e5c_carga "32/1/0" "solo en acentos, mayúsculas o espacios" "E5c municipio existente con grafía distinta (no se duplica ni se renombra)"
fallo_controlado "$(nueva e5d_entidad_ambigua e5d)" e5d_carga "2/0/0" "SEPOMEX DETENIDO: la entidad «Jalisco» está registrada 2 veces" "E5d entidad registrada dos veces"

echo "== E7 · Interrupción de la carga"
mkdir -p "$SALIDA/sql_con_error"; cp "$SQL"/*.sql "$SALIDA/sql_con_error/"
largo=$(printf 'X%.0s' $(seq 1 151))
# Error a mitad de 03 (un nombre de 151 caracteres excede VARCHAR(150) en el municipio de Guadalajara): todo se revierte.
sed -i "0,/('Arcos', '44130')/s//('$largo', '44130')/" "$SALIDA/sql_con_error/03_insert_colonia.sql"
fallo_controlado "$(nueva e7a_error)" e7a_carga "0/0/0" "value too long" "E7a error a mitad de las colonias: no queda ninguna inserción (ni entidades ni municipios)" "$SALIDA/sql_con_error"
B7=$(nueva e7b_cancelada)
(cd "$SQL" && app "$B7" -v ON_ERROR_STOP=1 -f SEPOMEX_CATALOGO_COMPLETO.sql > "$SALIDA/e7b_carga.log" 2>&1; echo $? > "$SALIDA/e7b_rc") &
for i in $(seq 1 200); do
    pid=$(adm -Atc "select pid from pg_stat_activity where datname = '$B7' and query like '%WITH v(colonia, cp)%' limit 1")
    [ -n "$pid" ] && break; sleep 0.05
done
adm -Atc "select pg_terminate_backend($pid)" >/dev/null; wait
[ "$(cat "$SALIDA/e7b_rc")" != 0 ] && [ "$(conteos "$B7")" = "0/0/0" ]
prueba "E7b conexión terminada durante las colonias: la transacción se revierte por completo" $? "rc=$(cat "$SALIDA/e7b_rc") · $(conteos "$B7")"

echo "== E8 · Conservación de domicilios y solicitantes existentes"
B8=$(nueva e8_solicitantes entidades)
artisan "$B8" anfeca:catalogos --convocatoria-anio="$(date +%Y)" > "$SALIDA/e8_preparacion.txt" 2>&1 && artisan "$B8" anfeca:laboratorio >> "$SALIDA/e8_preparacion.txt" 2>&1
app "$B8" -q -v ON_ERROR_STOP=1 -v escenario=e8 -f "$DIR/pruebas.sql" >> "$SALIDA/e8_preparacion.txt" 2>&1
h0=$(huella_ajena "$B8"); d0=$(val "$B8" "select count(*) from domicilio")
cargar "$B8" e8_carga; r=$?
[ $r = 0 ] && [ "$d0" -gt 0 ] && [ "$h0" = "$(huella_ajena "$B8")" ] && grep -aq "| t$" <(grep -aA3 "== 5. Conservación" "$SALIDA/e8_carga.log")
prueba "E8 domicilios, personas, usuarios, solicitudes y documentos idénticos tras la carga" $? "$d0 domicilios · $h0"

echo "== E10 · Comparación con el importador Artisan"
B10=$(nueva e10_artisan); artisan "$B10" anfeca:sepomex "$SEPOMEX" --aplicar --base="$B10" --sha256="$SEPOMEX_SHA256" --json > "$SALIDA/e10_artisan.json" 2>&1
exportar "$B10" e10_export.txt
[ -s "$SALIDA/e10_export.txt" ] && cmp -s "$SALIDA/e1_export.txt" "$SALIDA/e10_export.txt" && [ "$(conteos "$B10")" = "$(conteos "$B1")" ]
prueba "E10 Artisan y SQL producen el mismo contenido en base vacía (incluidos ids de entidad)" $? "$(wc -l < "$SALIDA/e10_export.txt") filas · md5 $(md5sum < "$SALIDA/e10_export.txt" | cut -c1-12) / $(md5sum < "$SALIDA/e1_export.txt" | cut -c1-12)"
B10b=$(nueva e10b_artisan_ids e4); artisan "$B10b" anfeca:sepomex "$SEPOMEX" --aplicar --base="$B10b" --sha256="$SEPOMEX_SHA256" --json > "$SALIDA/e10b_artisan.json" 2>&1
exportar "$B4" e4_export.txt; exportar "$B10b" e10b_export.txt
[ -s "$SALIDA/e4_export.txt" ] && cmp -s "$SALIDA/e4_export.txt" "$SALIDA/e10b_export.txt"; prueba "E10 Artisan y SQL equivalentes con ids distintos y secuencias atrasadas (escenario E4)" $? "$(conteos "$B10b")"
artisan "$B1" anfeca:sepomex "$SEPOMEX" --json > "$SALIDA/e10_diagnostico_sobre_sql.json" 2>&1
grep -q '"municipios_nuevos": 0' "$SALIDA/e10_diagnostico_sobre_sql.json" && grep -q '"colonias_nuevas": 0' "$SALIDA/e10_diagnostico_sobre_sql.json" && grep -q '"entidades_nuevas": 0' "$SALIDA/e10_diagnostico_sobre_sql.json"
prueba "E10 el diagnóstico Artisan sobre la base cargada con SQL no encuentra pendientes" $?
cargar "$B10" e10_sql_sobre_artisan; insertados e10_sql_sobre_artisan | grep -q "entidades: 0 · municipios: 0 · colonias: 0"
prueba "E10 el SQL sobre la base cargada con Artisan no inserta nada" $? "$(insertados e10_sql_sobre_artisan)"

echo "== R · Reversión controlada (05)"
BR=$(nueva r_reversion e3); cargar "$BR" r_carga
app "$BR" -v ON_ERROR_STOP=1 -c "insert into colonia (colo_id_alcaldia, colo_nombre, colo_codigo_postal)
   select a.almu_id_alcaldia, 'Colonia Ficticia Posterior', '01999' from alcaldia_municipio a join entidad_federativa e on e.enfe_id_entidad_federativa = a.almu_id_entidad_federativa
   where a.almu_nombre = 'Álvaro Obregón' and e.enfe_nombre = 'Ciudad de México'" >/dev/null
M=$(marcas r_carga); echo "$M" > "$SALIDA/r_marcas.txt"
(cd "$SQL" && eval app "$BR" -v ON_ERROR_STOP=1 $M -f 05_reversion_catalogos.sql) > "$SALIDA/r_simulacion.log" 2>&1
grep -q "SIMULACIÓN" "$SALIDA/r_simulacion.log" && [ "$(conteos "$BR")" = "32/2478/158509" ]
prueba "R1 la reversión simula por omisión y no modifica nada" $? "$(conteos "$BR")"
(cd "$SQL" && eval app "$BR" -v ON_ERROR_STOP=1 $M -v confirmar=si -f 05_reversion_catalogos.sql) > "$SALIDA/r_aplicada.log" 2>&1
[ "$(conteos "$BR")" = "32/4/4" ] && [ "$(val "$BR" "select string_agg(almu_nombre, ',' order by almu_id_alcaldia) from alcaldia_municipio")" = "Guadalajara,Coyoacán,Oaxaca de Juárez,Álvaro Obregón" ] \
  && [ "$(val "$BR" "select count(*) from colonia where colo_nombre in ('Arcos','Universidad Nacional Autónoma de México','Colonia Ficticia de Prueba','Colonia Ficticia Posterior')")" = 4 ]
prueba "R2 reversión aplicada: elimina solo lo cargado; conserva lo previo y el municipio con una colonia posterior" $? "$(conteos "$BR")"
(cd "$SQL" && app "$BR" -v ON_ERROR_STOP=1 -f 05_reversion_catalogos.sql) > "$SALIDA/r_sin_marcas.log" 2>&1
grep -q "Faltan las marcas" "$SALIDA/r_sin_marcas.log" && [ "$(conteos "$BR")" = "32/4/4" ]; prueba "R3 sin marcas la reversión no hace nada" $?

echo; echo "Resultado: $ok/$total verificaciones aprobadas." | tee -a "$RES"
echo "Bases de prueba creadas (se conservan como evidencia): $(adm -Atc "select string_agg(datname, ' ' order by 1) from pg_database where datname like '${PREFIJO}\_%'")" | tee -a "$RES"
