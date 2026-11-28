#!/bin/bash
# BLOQUE 7 · PRUEBAS POSTERIORES.
#  A) Verificaciones NO destructivas sobre el 8088 real (lectura, HTTP de consulta, SELinux, rendimiento SEPOMEX).
#  B) Batería de 81 verificaciones contra una COPIA de prueba ($PRUEBAS, php -S en 127.0.0.1:8189) y una BASE DE PRUEBA
#     NUEVA ($BD_PRUEBAS) en el clúster del 8088, solo con datos ficticios. Nunca usa la base institucional.
# Uso: RESPALDO=/var/backups/anfeca8088/fase9_<fecha> sudo -E bash 07_pruebas.sh        (PARTE=A o PARTE=B para una sola)
# Requisitos de B: python3 con «requests» y Node con Playwright 1.63 + Chromium (ver OPERACION_8088_FASE9.md, §B).
source "$(dirname "$0")/comun.inc.sh"
pedir_clave
PARTE="${PARTE:-AB}"

if [[ "$PARTE" == *A* ]]; then
    paso "A1 · Versión desplegada y estructura"
    cat "$APP/storage/app/version_desplegada.txt" 2>/dev/null; DIR_APP="$APP" artisan anfeca:verificar-esquema || echo "FALLIDA estructura"
    leer "select count(*) from information_schema.tables where table_schema not in ('pg_catalog','information_schema')"
    paso "A2 · Respuestas HTTP de la aplicación real (solo consulta)"
    for ruta in /login /registro/datos-personales /recuperar /sesion-finalizada /sesion/estado /catalogos/codigo-postal/04510 /.env /storage/../.env; do
        curl -s -o /dev/null -w "%{http_code}  $ruta\n" -H 'Accept: application/json' "http://127.0.0.1:8088$ruta"
    done
    paso "A3 · Compilación Vite servida"
    M=$(python3 -c "import json;print(next(iter(json.load(open('$APP/public/build/manifest.json')).values()))['file'])"); curl -s -o /dev/null -w "%{http_code}  /build/$M\n" "http://127.0.0.1:8088/build/$M"
    paso "A4 · SELinux y permisos"
    getenforce; ls -ldZ "$APP/storage" "$APP/storage/framework/sessions" "$APP/bootstrap/cache" "$APP/public/build"
    ausearch -m avc -ts recent 2>/dev/null | grep -iE 'httpd|php' | tail -5 || echo "Sin denegaciones AVC recientes"
    sudo -u "$USUARIO_WEB" test -w "$APP/storage/framework/sessions" && echo "Apache escribe sesiones: sí"
    paso "A5 · Rendimiento de la consulta por código postal (servidor real)"
    for cp in 01000 04510 06600 20000 44130 64000 72000 97000 99999; do
        curl -s -o /dev/null -w "$cp  %{http_code}  %{time_total}s\n" -H 'Accept: application/json' "http://127.0.0.1:8088/catalogos/codigo-postal/$cp"
    done | tee "$SALIDA/rendimiento_cp.txt"
    leer "explain (analyze, buffers) select c.colo_id_colonia from colonia c join alcaldia_municipio a on a.almu_id_alcaldia = c.colo_id_alcaldia
          join entidad_federativa e on e.enfe_id_entidad_federativa = a.almu_id_entidad_federativa where c.colo_codigo_postal = '44130'" | tail -3
    paso "A6 · Catálogo sin pendientes y conservación de expedientes"
    artisan_prep anfeca:sepomex "$SEPOMEX" | grep -E 'nuev|totales'
    if [ -n "${RESPALDO:-}" ]; then
        while IFS='|' read -r t n; do
            case "$t" in entidad_federativa|alcaldia_municipio|colonia) continue;; esac
            a=$(leer "select count(*) from public.$t"); [ "$a" -ge "$n" ] && echo "ok  $t $n → $a" || echo "FALLIDA $t $n → $a"
        done < "$RESPALDO/conteos.txt"
        find "$APP/storage/app/private" -type f -exec sha256sum {} + 2>/dev/null | sort -k2 > "$SALIDA/huellas_actuales.txt"
        comm -23 "$RESPALDO/huellas_expedientes.txt" "$SALIDA/huellas_actuales.txt" | tee "$SALIDA/expedientes_alterados.txt" | head
        [ -s "$SALIDA/expedientes_alterados.txt" ] && echo "FALLIDA: archivos de expedientes alterados o ausentes" || echo "ok  archivos de expedientes intactos"
    fi
fi

if [[ "$PARTE" == *B* ]]; then
    paso "B0 · Requisitos"
    python3 -c "import requests" || alto "Falta python3-requests (dnf install python3-requests) para la parte B."
    NODE_PATH="${NODE_PATH:-$HOME/anfeca_herramientas/node_modules}"; export NODE_PATH
    node -e "require('playwright')" 2>/dev/null || alto "Falta Playwright en $NODE_PATH (ver OPERACION_8088_FASE9.md §B)."
    paso "B1 · Base de prueba NUEVA $BD_PRUEBAS en el clúster del 8088"
    [ "$(leer "select count(*) from pg_database where datname = '$BD_PRUEBAS'" "$ROL_APP" postgres)" = 0 ] \
        || alto "$BD_PRUEBAS ya existe: use otro nombre (BD_PRUEBAS=…); no se reutiliza ni se borra."
    admin_psql -v ON_ERROR_STOP=1 -c "create database $BD_PRUEBAS owner $ROL_APP" || alto "No se creó $BD_PRUEBAS."
    "$PG_BIN/psql" -X -U "$ROL_APP" -d "$BD_PRUEBAS" -v ON_ERROR_STOP=1 -q -f "$PREPARACION/database/sql/oficial/anfeca_modelo_powerdesigner_certificacion_anfeca_sin_esquema.sql"
    paso "B2 · Copia de prueba $PRUEBAS (servida solo en 127.0.0.1:8189)"
    [ -e "$PRUEBAS" ] && alto "$PRUEBAS ya existe."
    rsync -a --exclude .git --exclude node_modules --exclude storage "$PREPARACION/" "$PRUEBAS/"
    mkdir -p "$PRUEBAS/storage/framework/"{cache/data,sessions,views} "$PRUEBAS/storage/logs" "$PRUEBAS/storage/app/private/catalogos"
    cp "$PRUEBAS/tests/fixtures/catalogos/licenciaturas_ficticio.json" "$PRUEBAS/storage/app/private/catalogos/licenciaturas_anuies.json"
    cp "$PRUEBAS/tests/fixtures/catalogos/posgrados_ficticio.json" "$PRUEBAS/storage/app/private/catalogos/posgrados_anuies_2025.json"
    sed -e "s/^APP_ENV=.*/APP_ENV=local/" -e "s/^APP_DEBUG=.*/APP_DEBUG=false/" -e "s#^APP_URL=.*#APP_URL=http://127.0.0.1:8189#" \
        -e "s/^DB_DATABASE=.*/DB_DATABASE=$BD_PRUEBAS/" -e "s/^LOG_CHANNEL=.*/LOG_CHANNEL=single/" -e "s/^MAIL_MAILER=.*/MAIL_MAILER=log/" \
        -e "/^ANFECA_CATALOGO_.*_RUTA=/d" -e "/^ANFECA_CATALOGO_.*_SHA256=/d" "$PREPARACION/.env" > "$PRUEBAS/.env"; chmod 600 "$PRUEBAS/.env"
    grep -q "^DB_DATABASE=$BD_PRUEBAS$" "$PRUEBAS/.env" || alto "La copia de prueba no apunta a $BD_PRUEBAS."
    (cd "$PRUEBAS" && "$PHP" artisan key:generate --force -q && "$PHP" artisan config:clear -q)
    paso "B3 · Datos de prueba: SEPOMEX, catálogos, datos ficticios y coordinación ficticia"
    (cd "$PRUEBAS" && "$PHP" -d memory_limit=1024M artisan anfeca:sepomex "$SEPOMEX" --aplicar --base="$BD_PRUEBAS" --sha256="$SEPOMEX_SHA256" | grep -E 'nuev|segundos|memoria' \
        && "$PHP" artisan anfeca:catalogos --convocatoria-anio="$(date +%Y)" && "$PHP" artisan anfeca:laboratorio) || alto "Preparación de datos de prueba fallida."
    CRED="$SALIDA/credenciales_ficticias.txt"; install -m 600 /dev/null "$CRED"
    for c in "coord.nacional@example.test coordinador_nacional - FICN800101HDFCRD01" "coord.zona7@example.test coordinador_zona 7 FICZ800101HDFCRD07" \
             "coord.zona6@example.test coordinador_zona 6 FICZ800101HDFCRD06"; do
        set -- $c; Z=""; [ "$3" != "-" ] && Z="--zona=$3"
        (cd "$PRUEBAS" && "$PHP" artisan anfeca:coordinador-alta "$1" "$2" $Z --nombre="Coordinación Ficticia" --apellido=Prueba --curp="$4" --rfc="${4:0:10}AB1" --credenciales="$CRED" >/dev/null)
    done
    paso "B4 · Servidor de la copia de prueba y batería"
    (cd "$PRUEBAS/public" && PHP_CLI_SERVER_WORKERS=4 nohup "$PHP" -S 127.0.0.1:8189 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php > "$SALIDA/php_s.log" 2>&1 & echo $! > "$SALIDA/php_s.pid")
    sleep 2
    COPIA="$PRUEBAS" URL=http://127.0.0.1:8189 PHP="$PHP" PSQL="$PG_BIN/psql" PGDATABASE="$BD_PRUEBAS" PGUSER="$ROL_APP" CREDENCIALES="$CRED" \
        SALIDA="$SALIDA/bateria" CATALOGO_LIC_VERSION=FICTICIO-LIC-1 CATALOGO_POS_VERSION=FICTICIO-1 \
        bash "$PRUEBAS/scripts/anfeca_8088/pruebas/ejecutar_bateria.sh"
    kill "$(cat "$SALIDA/php_s.pid")" 2>/dev/null
    echo "La copia $PRUEBAS y la base $BD_PRUEBAS se conservan como evidencia; retirarlas requiere autorización."
fi
echo; echo "Resultados en $SALIDA"
