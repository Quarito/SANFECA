#!/bin/bash
# =====================================================================================================================
# ANFECA · Prueba de funcionamiento SIN conectividad externa (catálogo SEPOMEX cargado con los scripts SQL).
# Ejecuta la copia de prueba de ANFECA y el navegador dentro de un espacio de red aislado (unshare -n): solo existe la
# interfaz de loopback, sin DNS ni rutas a Internet. PostgreSQL se alcanza por su socket local (archivo), no por red.
#
# Requiere root (unshare -n). Variables:
#   COPIA   copia de prueba de ANFECA (public/build compilado) cuyo .env apunta por SOCKET a una base de PRUEBA
#   PHP     intérprete PHP · NODE_PATH  módulos con Playwright · SALIDA  directorio de evidencias
# =====================================================================================================================
set -uo pipefail
: "${COPIA:?}" "${SALIDA:?}"
PHP="${PHP:-php}"; DIR="$(cd "$(dirname "$0")" && pwd)"; PRUEBAS="$(cd "$DIR/../pruebas" && pwd)"
mkdir -p "$SALIDA/capturas"
grep -q '^DB_HOST=/' "$COPIA/.env" || { echo "DETENIDO: el .env de la copia debe usar el socket local de PostgreSQL (DB_HOST=/ruta)."; exit 1; }
case "$(grep '^DB_DATABASE=' "$COPIA/.env" | cut -d= -f2)" in certificacion_anfeca|bd_*) echo "DETENIDO: use una base de PRUEBA."; exit 1;; esac
export COPIA PHP SALIDA DIR PRUEBAS NODE_PATH
unshare -n env -u HTTPS_PROXY -u https_proxy -u HTTP_PROXY -u http_proxy -u NO_PROXY -u no_proxy bash -c '
    set -u; python3 -I "$DIR/levantar_lo.py"
    echo "== 1. Aislamiento de red"
    { cat /proc/net/dev | sed -n "3,\$p" | cut -d: -f1 | tr -d " " | tr "\n" " "; echo "(interfaces)"; } | tee "$SALIDA/01_red.txt"
    curl -sS -m 5 -o /dev/null https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/CodigoPostal_Exportar.aspx 2>&1 | tee -a "$SALIDA/01_red.txt"
    curl -sS -m 5 -o /dev/null https://1.1.1.1 2>&1 | tee -a "$SALIDA/01_red.txt"
    echo "== 2. Servicio interno de ANFECA por código postal (PostgreSQL local)"
    cd "$COPIA/public" && PHP_CLI_SERVER_WORKERS=4 "$PHP" -S 127.0.0.1:8191 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php > "$SALIDA/php_s.log" 2>&1 &
    SRV=$!; sleep 2
    for cp in 01000 04510 06600 20000 44130 64000 97000 99999; do
        printf "%s " "$cp"; curl -s -w " → HTTP %{http_code} en %{time_total} s\n" -H "Accept: application/json" "http://127.0.0.1:8191/catalogos/codigo-postal/$cp" | cut -c1-200
    done | tee "$SALIDA/02_codigo_postal.txt"
    echo "== 3. Autocompletado, selección de colonia y validación del domicilio en el navegador (sin Internet)"
    ANFECA_URL=http://127.0.0.1:8191 ANFECA_CAPTURAS="$SALIDA/capturas" node "$PRUEBAS/prueba_codigo_postal_navegador.cjs" 2>&1 | tee "$SALIDA/03_navegador.txt"
    pkill -P $SRV; kill $SRV
'
echo "== 4. Clientes HTTP en el código de la aplicación (deben ser solo rutas internas)"
{
    echo "-- Clientes HTTP de servidor (Http::, curl, Guzzle, sockets):"
    grep -rnE "Http::|curl_init|GuzzleHttp|file_get_contents\(['\"]https?:|fsockopen" "$COPIA/app" "$COPIA/routes" || echo "   ninguno"
    echo "-- URL absolutas en el código del navegador (resources/js):"
    grep -rnoE "https?://[a-zA-Z0-9.-]+" "$COPIA/resources/js" | grep -v "ejemplo.com" || echo "   ninguna"
    echo "-- fetch() del navegador: todas usan rutas relativas o URL internas entregadas por Laravel:"
    grep -rnE "fetch\(" "$COPIA/resources/js" | sed "s#^$COPIA/##"
} | tee "$SALIDA/04_clientes_http.txt"
grep -h "^Resultado" "$SALIDA/03_navegador.txt"
