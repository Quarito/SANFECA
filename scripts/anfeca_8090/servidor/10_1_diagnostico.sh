#!/bin/bash
# =====================================================================================================================
# ANFECA 8090 · FASE 10.1 · DIAGNÓSTICO INTEGRAL DE SOLO LECTURA (8086 ANFECA_ENSAYO, 8088 y prerrequisitos de 8090).
# No modifica ambientes, bases, configuración ni archivos existentes. Solo escribe en $SALIDA (directorio propio) y
# actualiza referencias remotas del checkout de Git (fetch), sin cambiar su rama ni sus archivos.
# Uso (VS Code SSH):  sudo -E bash 10_1_diagnostico.sh      (pide la contraseña del rol de 8088 sin mostrarla)
# =====================================================================================================================
set -uo pipefail
ENSAYO="${ENSAYO:-/var/www/ANFECA_ENSAYO}"
APP8088="${APP8088:-/var/www/ANFECA_8088}"
APP8090="${APP8090:-/var/www/ANFECA_8090}"
FASE9="${FASE9:-/home/servidorweb/anfeca_trabajo/fase9_8088}"
REPO="${REPO:-/home/servidorweb/anfeca_8088_codigo}"
PG_BIN="${PG_BIN:-/usr/pgsql-18/bin}"; PGHOST="${PGHOST:-127.0.0.1}"; PGPORT="${PGPORT:-5488}"
BD="${BD:-certificacion_anfeca}"; ROL_APP="${ROL_APP:-anfeca_8088}"; BD_8090="${BD_8090:-certificacion_anfeca_8090}"
PUERTO="${PUERTO:-8090}"
CANDIDATAS="${CANDIDATAS:-ensayo/principal validacion/8086-candidato-36-tablas integracion/fase9-8088 integracion/anfeca-36-tablas-definitivo desarrollo/modulo-coordinacion}"
SALIDA="${SALIDA:-/home/servidorweb/anfeca_fase10/$(date +%Y%m%d_%H%M)_10_1_diagnostico}"
export PGHOST PGPORT
mkdir -p "$SALIDA"; exec > >(tee -a "$SALIDA/registro.log") 2>&1
paso() { printf '\n==> %s\n' "$*"; }
leer() { PGOPTIONS='-c default_transaction_read_only=on' "$PG_BIN/psql" -X -U "$ROL_APP" -d "${2:-$BD}" -At -F '|' -c "$1"; }
tabla() { PGOPTIONS='-c default_transaction_read_only=on' "$PG_BIN/psql" -X -U "$ROL_APP" -d "${2:-$BD}" -P footer=off -c "$1"; }
echo "Registro: $SALIDA/registro.log"

paso "0. Servidor: sistema, espacio, memoria, PHP, Node"
hostnamectl 2>/dev/null | grep -E 'Operating|Kernel'; date; df -h / /var /home /var/lib/pgsql 2>/dev/null | sort -u; free -m
php -v 2>/dev/null | head -1; node -v 2>/dev/null; npm -v 2>/dev/null; composer --version 2>/dev/null | head -1

paso "1. Puerto $PUERTO, rutas y base destino (deben estar LIBRES)"
ss -ltnp 2>/dev/null | grep -E ":$PUERTO\b" && echo "OCUPADO: el puerto $PUERTO ya escucha" || echo "Puerto $PUERTO libre (sin proceso escuchando)"
semanage port -l 2>/dev/null | grep -E "^http_port_t" ; firewall-cmd --list-ports 2>/dev/null; firewall-cmd --list-services 2>/dev/null
grep -rlE "Listen[[:space:]]+$PUERTO|:$PUERTO>" /etc/httpd/conf /etc/httpd/conf.d 2>/dev/null && echo "AVISO: ya existe configuración de Apache para $PUERTO" || echo "Sin configuración de Apache para $PUERTO"
for d in "$APP8090" "$ENSAYO" "$APP8088" "$FASE9" "$REPO"; do [ -e "$d" ] && ls -ldZ "$d" 2>/dev/null || echo "NO EXISTE: $d"; done
ss -ltnp 2>/dev/null | grep -E ':(80|443|8008|8086|8088|5432|5433|5488)\b'
systemctl list-units --type=service --all 2>/dev/null | grep -Ei 'httpd|php-fpm|postgres'
ls -1 /etc/httpd/conf.d/ 2>/dev/null; grep -rhE '^\s*(Listen|DocumentRoot|<VirtualHost|SetHandler)' /etc/httpd/conf.d/ 2>/dev/null | sort -u

paso "2. ANFECA_ENSAYO (8086): versión, configuración sin secretos y dependencias"
if [ -d "$ENSAYO" ]; then
    [ -d "$ENSAYO/.git" ] && { git -C "$ENSAYO" log -1 --oneline; git -C "$ENSAYO" branch --show-current; git -C "$ENSAYO" status --short | head -40; } || echo "(sin .git en $ENSAYO)"
    grep -E '^(APP_ENV|APP_DEBUG|APP_URL|DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|SESSION_DRIVER|SESSION_COOKIE|SESSION_LIFETIME|CACHE_STORE|CACHE_DRIVER|QUEUE_CONNECTION|MAIL_MAILER|FILESYSTEM_DISK|ANFECA_[A-Z_]*)=' "$ENSAYO/.env" 2>/dev/null \
        | grep -viE 'PASSWORD|SECRET|KEY|TOKEN' | tee "$SALIDA/ensayo_env_sin_secretos.txt"
    php -r '$c=json_decode(file_get_contents($argv[1]),true); foreach(["require","require-dev"] as $k) foreach(($c[$k]??[]) as $p=>$v) echo "$k $p $v\n";' "$ENSAYO/composer.json" 2>/dev/null | tee "$SALIDA/ensayo_composer.txt"
    php -r '$c=json_decode(file_get_contents($argv[1]),true); foreach(["dependencies","devDependencies"] as $k) foreach(($c[$k]??[]) as $p=>$v) echo "$k $p $v\n";' "$ENSAYO/package.json" 2>/dev/null | tee "$SALIDA/ensayo_package.txt"
    cat "$ENSAYO/vite.config.js" 2>/dev/null
fi

paso "3. ANFECA_ENSAYO (8086): inventario del código fuente"
if [ -d "$ENSAYO" ]; then
    for p in app/Models app/Http/Controllers app/Services app/Http/Requests app/Http/Middleware app/Policies app/Console/Commands routes \
             resources/js/components resources/js resources/views resources/css database/migrations database/seeders tests public/images config docs; do
        printf '%-28s %5s archivos\n' "$p" "$( [ -d "$ENSAYO/$p" ] && find "$ENSAYO/$p" -type f | wc -l || echo 0)"
    done | tee "$SALIDA/ensayo_inventario.txt"
    (cd "$ENSAYO" && find app routes config resources database tests docs vite.config.js composer.json package.json -type f 2>/dev/null \
        -not -path '*/node_modules/*' | sort) > "$SALIDA/ensayo_archivos.txt"
    echo "Archivos fuente: $(wc -l < "$SALIDA/ensayo_archivos.txt")"
    (cd "$ENSAYO" && php artisan route:list --json 2>/dev/null) > "$SALIDA/ensayo_rutas.json" && echo "Rutas: $(php -r 'echo count(json_decode(file_get_contents($argv[1]),true)??[]);' "$SALIDA/ensayo_rutas.json")" || echo "(route:list no disponible)"
    grep -rhoE "Schema::create\('([a-z_]+)'" "$ENSAYO/database/migrations" 2>/dev/null | sed -E "s/.*'([a-z_]+)'/\1/" | sort -u > "$SALIDA/ensayo_tablas_migraciones.txt"
    echo "Tablas que crean sus migraciones: $(wc -l < "$SALIDA/ensayo_tablas_migraciones.txt") (ver ensayo_tablas_migraciones.txt)"
    grep -rlE "dompdf|Pdf::|snappy|mpdf" "$ENSAYO/app" "$ENSAYO/composer.json" 2>/dev/null | head; grep -rl "pdfjs" "$ENSAYO/resources/js" "$ENSAYO/package.json" 2>/dev/null | head -3
    find "$ENSAYO/storage/app" -type f 2>/dev/null | wc -l | sed 's/^/Archivos en storage\/app de 8086: /'
    # Copia del CÓDIGO FUENTE real (sin .env, storage, vendor ni node_modules) para recuperarlo y compararlo fuera del servidor.
    tar -C "$ENSAYO" --exclude=.env --exclude='.env.*' --exclude=storage --exclude=vendor --exclude=node_modules --exclude=public/build \
        --exclude=.git -czf "$SALIDA/ensayo_codigo_fuente.tgz" . && sha256sum "$SALIDA/ensayo_codigo_fuente.tgz"
fi

paso "4. ¿Qué commit de Git se parece más al código real de 8086? (comparación de huellas Git de cada archivo)"
if [ -d "$ENSAYO" ] && [ -d "$REPO/.git" ]; then
    git -C "$REPO" fetch -q origin $CANDIDATAS 2>&1 | tail -2
    (cd "$ENSAYO" && xargs -a "$SALIDA/ensayo_archivos.txt" -d '\n' git hash-object --no-filters 2>/dev/null | paste -d' ' - "$SALIDA/ensayo_archivos.txt") > "$SALIDA/ensayo_huellas_git.txt"
    for r in $CANDIDATAS; do
        ref="origin/$r"; git -C "$REPO" rev-parse -q --verify "$ref" >/dev/null || { echo "$r: no disponible"; continue; }
        git -C "$REPO" ls-tree -r "$ref" | awk '{print $3" "$4}' > "$SALIDA/arbol.tmp"
        awk 'NR==FNR {h[$2]=$1; next} {f=$2; if (!(f in h)) a++; else if (h[f]==$1) i++; else d++} END {printf "%5d idénticos · %5d distintos · %5d no están en la rama\n", i, d, a}' \
            "$SALIDA/arbol.tmp" "$SALIDA/ensayo_huellas_git.txt" | sed "s#^#$(printf '%-42s' "$r $(git -C "$REPO" rev-parse --short "$ref")")#"
    done | tee "$SALIDA/ensayo_vs_ramas.txt"
    rm -f "$SALIDA/arbol.tmp"
    # Commit más cercano del historial de ensayo/principal (máximo de archivos idénticos), si existe la rama.
    if git -C "$REPO" rev-parse -q --verify origin/ensayo/principal >/dev/null; then
        for c in $(git -C "$REPO" rev-list --max-count=60 origin/ensayo/principal); do
            n=$(git -C "$REPO" ls-tree -r "$c" | awk '{print $3" "$4}' | awk 'NR==FNR {h[$2]=$1; next} ($2 in h) && h[$2]==$1 {i++} END {print i+0}' - "$SALIDA/ensayo_huellas_git.txt")
            echo "$n $(git -C "$REPO" log -1 --format='%h %ad %s' --date=short "$c" | cut -c1-90)"
        done | sort -rn | head -5 | tee "$SALIDA/ensayo_commit_mas_cercano.txt"
    fi
fi

paso "5. Trabajo de la Fase 9 en $FASE9"
[ -d "$FASE9" ] && { ls -la "$FASE9" | head -20; [ -d "$FASE9/.git" ] && { git -C "$FASE9" log -1 --oneline; git -C "$FASE9" branch --show-current; git -C "$FASE9" status --short | head -20; }; } || echo "No existe $FASE9"
ls -ld /var/lib/anfeca8088/sepomex /var/lib/anfeca8088/sepomex/* 2>/dev/null

paso "6. ANFECA_8088: conexión real (sin secretos) y código desplegado"
grep -E '^(APP_URL|DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|SESSION_DRIVER|SESSION_COOKIE|CACHE_STORE|QUEUE_CONNECTION|MAIL_MAILER)=' "$APP8088/.env" 2>/dev/null
cat "$APP8088/storage/app/version_desplegada.txt" 2>/dev/null; git -C "$REPO" log -1 --oneline 2>/dev/null; git -C "$REPO" branch --show-current 2>/dev/null

if [ -z "${PGPASSWORD:-}" ] && ! grep -q ":$PGPORT:" "$HOME/.pgpass" 2>/dev/null; then read -rsp "Contraseña del rol $ROL_APP en $PGHOST:$PGPORT: " PGPASSWORD; echo; export PGPASSWORD; fi

paso "7. PostgreSQL de 8088: identidad, bases y roles (solo nombres)"
leer "select concat_ws(' · ', current_database(), 'puerto ' || current_setting('port'), 'rol ' || current_user, version())"
leer "select datname || ' · ' || pg_size_pretty(pg_database_size(datname)) from pg_database where not datistemplate order by 1"
leer "select count(*) from pg_database where datname = '$BD_8090'" | sed "s/^/Bases con el nombre $BD_8090 (debe ser 0): /"
leer "select rolname || case when rolcanlogin then ' (login)' else '' end || case when rolcreatedb then ' createdb' else '' end from pg_roles where rolname !~ '^pg_' order by 1"

paso "8. Estructura real frente a la referencia (36 tablas, 173 atributos, 36 PK, 35 FK, 36 secuencias)"
leer "select 'tablas '||(select count(*) from information_schema.tables where table_schema='public' and table_type='BASE TABLE')
   ||' · atributos '||(select count(*) from information_schema.columns c join information_schema.tables t using (table_schema, table_name) where c.table_schema='public' and t.table_type='BASE TABLE')
   ||' · PK '||(select count(*) from pg_constraint where contype='p' and connamespace='public'::regnamespace)
   ||' · FK '||(select count(*) from pg_constraint where contype='f' and connamespace='public'::regnamespace)
   ||' · índices '||(select count(*) from pg_indexes where schemaname='public')
   ||' · secuencias '||(select count(*) from pg_sequences where schemaname='public')
   ||' · esquemas no del sistema '||(select string_agg(nspname, ',') from pg_namespace where nspname !~ '^pg_' and nspname <> 'information_schema')"
"$PG_BIN/psql" -X -U "$ROL_APP" -d "$BD" -At -F '|' -c "copy (select c.table_name, c.ordinal_position, c.column_name, c.data_type, c.character_maximum_length, c.is_nullable, c.column_default
    from information_schema.columns c join information_schema.tables t using (table_schema, table_name) where c.table_schema='public' and t.table_type='BASE TABLE' order by 1, 2) to stdout with csv header" \
    > "$SALIDA/8088_columnas.csv" && echo "Columnas: $SALIDA/8088_columnas.csv"
leer "select conname||' · '||conrelid::regclass||' · '||pg_get_constraintdef(oid) from pg_constraint where connamespace='public'::regnamespace and contype in ('p','f','u','c') order by contype, 1" > "$SALIDA/8088_restricciones.txt"
leer "select indexname||' · '||indexdef from pg_indexes where schemaname='public' order by 1" > "$SALIDA/8088_indices.txt"
leer "select sequencename||' · último '||coalesce(last_value::text,'(sin usar)') from pg_sequences where schemaname='public' order by 1" > "$SALIDA/8088_secuencias.txt"
wc -l "$SALIDA"/8088_restricciones.txt "$SALIDA"/8088_indices.txt "$SALIDA"/8088_secuencias.txt

paso "9. Registros por tabla (inventario de referencia)"
leer "select table_name from information_schema.tables where table_schema='public' and table_type='BASE TABLE' order by 1" \
    | while read -r t; do echo "$t|$(leer "select count(*) from public.$t")"; done | tee "$SALIDA/8088_conteos.txt"

paso "10. Catálogos (solo lectura): ANUIES, instituciones, zonas, certificación, documentos, estados, SEPOMEX"
tabla "select n.nipr_nivel_profesional as nivel, e.espr_estado as estado, count(*) as estudios from estudio_profesional e
       join nivel_profesional n on n.nipr_id_nivel_profesional = e.espr_id_nivel_profesional group by 1, 2 order by 1, 2"
tabla "select count(*) as instituciones, count(distinct inst_zona) as zonas_distintas, count(*) filter (where inst_zona is null) as sin_zona from institucion"
tabla "select inst_zona as zona, count(*) as instituciones from institucion group by 1 order by 1"
for q in "select area_id_area_certificacion, area_descripcion from area_certificacion order by 1" \
         "select tico_id_tipo_convocatoria, tico_siglas, tico_tipo_convocatoria, tico_estado from tipo_convocatoria order by 1" \
         "select conv_id_convocatoria, conv_nombre, conv_fecha_inicio_registro, conv_fecha_fin_registro from convocatoria order by 1" \
         "select * from tipo_personal_academico order by 1" "select * from nivel_profesional order by 1" \
         "select docu_id_documento, docu_descripcion, docu_estatus from documento order by 1" "select * from estado_documento order by 1" \
         "select * from c_estado_solicitud order by 1" "select * from tipo_comunicacion order by 1" "select rol_id_rol, rol_nombre from rol order by 1" \
         "select priv_id_privilegio, priv_nombre from privilegio order by 1"; do tabla "$q"; done
tabla "select (select count(*) from entidad_federativa) as entidades, (select count(*) from alcaldia_municipio) as municipios, (select count(*) from colonia) as colonias"
tabla "select count(*) as historicos from historico_certificado"
tabla "select e.esso_siglas as estado, count(*) as eventos from estado_solicitud e group by 1 order by 1"
tabla "select (select count(*) from persona) as personas, (select count(*) from usuario) as usuarios, (select count(*) from domicilio) as domicilios,
       (select count(*) from solicitud) as solicitudes, (select count(*) from solicitud_documento) as documentos"

paso "11. Almacenamiento documental de 8088 y correspondencia con SOLICITUD_DOCUMENTO"
P="$APP8088/storage/app/private"; du -sh "$P" 2>/dev/null; find "$P" -type f 2>/dev/null | wc -l | sed 's/^/Archivos privados: /'
leer "select socu_id_solicitud||'/'||socu_id_documentacion from solicitud_documento order by 1" > "$SALIDA/documentos_bd.txt"
find "$P/anfeca/expedientes" -type f 2>/dev/null | sed -E "s#^$P/anfeca/expedientes/##; s#\.[A-Za-z0-9]+\$##" | sort > "$SALIDA/documentos_archivos.txt"
sort -o "$SALIDA/documentos_bd.txt" "$SALIDA/documentos_bd.txt"
echo "Registros sin archivo: $(comm -23 "$SALIDA/documentos_bd.txt" "$SALIDA/documentos_archivos.txt" | wc -l) · archivos sin registro: $(comm -13 "$SALIDA/documentos_bd.txt" "$SALIDA/documentos_archivos.txt" | wc -l)"

paso "12. Diferencias con el modelo oficial (si el diagnóstico de conciliación está disponible)"
DIAG="$(dirname "$0")/../../anfeca_8088/servidor/conciliacion/diagnostico_diferencias_8088.sql"
[ -f "$DIAG" ] && PGOPTIONS='-c default_transaction_read_only=on' "$PG_BIN/psql" -X -U "$ROL_APP" -d "$BD" -v ON_ERROR_STOP=1 -f "$DIAG" > "$SALIDA/8088_diferencias_modelo.txt" 2>&1 \
    && sed -n '/== 1\./,/== 3\./p' "$SALIDA/8088_diferencias_modelo.txt" | head -30

paso "13. Áreas de certificación y programas ANUIES (solo lectura)"
AN="$(dirname "$0")/10_8_diagnostico_anuies_areas.sql"
[ -f "$AN" ] && PGOPTIONS='-c default_transaction_read_only=on' "$PG_BIN/psql" -X -U "$ROL_APP" -d "$BD" -v ON_ERROR_STOP=1 -f "$AN" > "$SALIDA/8088_anuies_areas.txt" 2>&1 \
    && sed -n '/== 2\./,/== 3\./p;/== 5\./,/== 6\./p' "$SALIDA/8088_anuies_areas.txt" | head -40
leer "select column_name||' · '||data_type||' · '||coalesce(character_maximum_length::text,'')||' · nulos '||is_nullable from information_schema.columns
      where table_schema='public' and table_name='institucion' and column_name in ('inst_coord_regional_cert_academica','ins_coord_regional_cert_academi')" \
    | sed 's/^/C-01 · /'
ls -l "$ENSAYO"/storage/app/private/catalogos/*.json "$APP8088"/storage/app/private/catalogos/*.json 2>/dev/null; sha256sum "$ENSAYO"/storage/app/private/catalogos/*.json 2>/dev/null

echo; echo "Diagnóstico 10.1 terminado sin cambios. Resultados: $SALIDA"
echo "Para enviarlos (sin secretos): tar -czf ~/fase10_1_resultados.tgz -C $(dirname "$SALIDA") $(basename "$SALIDA")"
