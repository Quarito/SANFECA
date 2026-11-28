#!/bin/bash
# =====================================================================================================================
# ANFECA · Arma el paquete PRIVADO SEPOMEX_ANFECA (original, SQL, herramientas, evidencias, manifiesto, README) y su ZIP.
# El paquete reproduce el catálogo oficial de Correos de México: uso interno autorizado; NUNCA se versiona en Git.
#
# Variables:
#   SEPOMEX            archivo oficial (CPdescarga.xls)       ZIP_ORIGINAL  ZIP recibido (opcional, solo su huella)
#   GENERADO           salida de «php artisan anfeca:sepomex-sql» (contiene sql/, evidencias/ y manifiesto_generacion.json)
#   ESCENARIOS         evidencias de probar_escenarios.sh      SIN_INTERNET  evidencias de probar_sin_internet.sh (opcional)
#   DESTINO            directorio NUEVO donde se crea SEPOMEX_ANFECA/ y el ZIP (fuera del repositorio)
# =====================================================================================================================
set -euo pipefail
: "${SEPOMEX:?}" "${GENERADO:?}" "${ESCENARIOS:?}" "${DESTINO:?}"
REPO="$(cd "$(dirname "$0")/../../.." && pwd)"
case "$(realpath -m "$DESTINO")/" in "$REPO"/*) echo "DETENIDO: el paquete no puede crearse dentro del repositorio."; exit 1;; esac
P="$DESTINO/SEPOMEX_ANFECA"
[ -e "$P" ] && { echo "DETENIDO: $P ya existe."; exit 1; }
mkdir -p "$P"/{original,sql,herramientas,evidencias/escenarios}
umask 027

install -m 640 "$SEPOMEX" "$P/original/"
cp "$GENERADO"/sql/*.sql "$P/sql/"
cp "$GENERADO"/evidencias/*.csv "$P/evidencias/"
cp "$REPO/scripts/anfeca_8088/sepomex_sql/pruebas.sql" "$P/evidencias/pruebas.sql"
for f in app/Console/Commands/GenerarSqlSepomex.php app/Console/Commands/ImportarSepomex.php app/Services/Anfeca/Sepomex/CatalogoSepomex.php \
         app/Services/Anfeca/Sepomex/LectorSepomex.php scripts/anfeca_8088/sepomex_sql/probar_escenarios.sh scripts/anfeca_8088/sepomex_sql/probar_sin_internet.sh \
         scripts/anfeca_8088/sepomex_sql/levantar_lo.py scripts/anfeca_8088/sepomex_sql/empaquetar.sh; do
    cp "$REPO/$f" "$P/herramientas/"
done
cat > "$P/herramientas/generar_sql.sh" <<'G'
#!/bin/bash
# Regenera los scripts SQL a partir del archivo oficial con el generador de ANFECA (rama integracion/fase9-8088 o posterior).
# Uso: ANFECA=<checkout de ANFECA con vendor/> bash generar_sql.sh <CPdescarga.xls> <directorio NUEVO fuera de Git> [sha256 esperado]
set -euo pipefail
cd "${ANFECA:?Indique ANFECA=<directorio de la aplicación>}"
php -d memory_limit=1024M artisan anfeca:sepomex-sql "$1" --salida="$2" ${3:+--sha256="$3"}
G
chmod 750 "$P/herramientas/"*.sh

# Evidencias de ejecución real (sin las exportaciones completas del catálogo).
rsync -a --exclude '*_export.txt' --exclude 'sql_con_error' "$ESCENARIOS/" "$P/evidencias/escenarios/"
[ -n "${SIN_INTERNET:-}" ] && rsync -a "$SIN_INTERNET/" "$P/evidencias/sin_internet/"

# Reporte de importación.
python3 -I - "$GENERADO/manifiesto_generacion.json" "$ESCENARIOS/resultados.txt" "${SIN_INTERNET:-}" "${ZIP_ORIGINAL:-}" > "$P/evidencias/reporte_importacion.txt" <<'PY'
import json, sys, hashlib, os
m = json.load(open(sys.argv[1], encoding='utf-8'))
print('ANFECA · Catálogo Nacional de Códigos Postales (SEPOMEX) · Reporte de importación')
print('=' * 92)
for k, t in [('archivo', 'Archivo'), ('fuente_oficial', 'Fuente oficial'), ('version_sepomex', 'Fecha de actualización (versión)'),
             ('fecha_descarga', 'Fecha de descarga'), ('sha256', 'SHA-256 del archivo'), ('registros_originales', 'Registros originales (filas)'),
             ('registros_procesados', 'Registros procesados (colonias representables)'), ('registros_consolidados', 'Registros consolidados por tipo de asentamiento'),
             ('colonias_con_registros_consolidados', 'Colonias que agrupan registros consolidados'), ('entidades', 'Entidades federativas'),
             ('municipios', 'Alcaldías y municipios'), ('colonias', 'Colonias y asentamientos'), ('total_registros_tres_tablas', 'Total en las tres tablas'),
             ('codigos_postales_distintos', 'Códigos postales distintos'), ('codigos_postales_con_cero_inicial', 'Códigos postales con cero inicial'),
             ('nombres_con_variante_de_acentos_o_mayusculas', 'Nombres con variante de acentos o mayúsculas (se conservan)')]:
    print(f'{t:<58}: {m.get(k)}')
if sys.argv[4] and os.path.exists(sys.argv[4]):
    print(f"{'SHA-256 del ZIP recibido':<58}: {hashlib.sha256(open(sys.argv[4], 'rb').read()).hexdigest()}")
print('\nInconsistencias identificadas:')
for k, v in m['inconsistencias'].items():
    print(f'  - {k}: {v}')
print(f"\nRestricciones de uso: {m['restricciones_de_uso']}")
print(f"\nGeneración de los SQL: {m['segundos']} s, memoria pico {m['memoria_pico_mb']} MB ({m['generado']})")
print('\nPruebas reales en PostgreSQL (bases nuevas y desechables):')
print(open(sys.argv[2], encoding='utf-8').read())
if sys.argv[3]:
    d = sys.argv[3]
    print('Funcionamiento sin Internet (espacio de red aislado):')
    for f in ('01_red.txt', '02_codigo_postal.txt'):
        p = os.path.join(d, f)
        if os.path.exists(p):
            print(open(p, encoding='utf-8').read())
    p = os.path.join(d, '03_navegador.txt')
    if os.path.exists(p):
        print(open(p, encoding='utf-8').read())
PY

cp "$REPO/docs/anfeca/SEPOMEX_PAQUETE_SQL.md" "$P/README.md"

# Manifiesto (huellas de todos los archivos) y SHA256SUMS.
python3 -I - "$GENERADO/manifiesto_generacion.json" "$P" > "$P/manifiesto.json" <<'PY'
import json, sys, hashlib, os
m = json.load(open(sys.argv[1], encoding='utf-8')); raiz = sys.argv[2]
m.pop('archivos', None)
archivos = {}
for d, _, fs in sorted(os.walk(raiz)):
    for f in sorted(fs):
        p = os.path.join(d, f); r = os.path.relpath(p, raiz)
        if r in ('manifiesto.json', 'SHA256SUMS'):
            continue
        archivos[r] = {'sha256': hashlib.sha256(open(p, 'rb').read()).hexdigest(), 'bytes': os.path.getsize(p)}
m['paquete'] = 'SEPOMEX_ANFECA'
m['orden_de_ejecucion'] = ['sql/00_diagnostico_previo.sql (solo lectura)', 'sql/SEPOMEX_CATALOGO_COMPLETO.sql (01, 02, 03 y 04 en una transacción)',
                           'sql/04_verificacion_catalogos.sql (solo lectura, repetible)', 'sql/05_reversion_catalogos.sql (solo si se autoriza; simula por omisión)']
m['archivos'] = archivos
print(json.dumps(m, ensure_ascii=False, indent=1))
PY
(cd "$P" && find . -type f ! -name SHA256SUMS | sort | sed 's#^\./##' | xargs -d '\n' sha256sum > SHA256SUMS)

VERSION=$(python3 -I -c "import json,sys;print(json.load(open(sys.argv[1]))['version_sepomex'].replace('/', ''))" "$P/manifiesto.json")
(cd "$DESTINO" && zip -qr -9 "SEPOMEX_ANFECA_${VERSION}.zip" SEPOMEX_ANFECA && sha256sum "SEPOMEX_ANFECA_${VERSION}.zip" > "SEPOMEX_ANFECA_${VERSION}.zip.sha256")
echo "Paquete: $P"; cat "$DESTINO/SEPOMEX_ANFECA_${VERSION}.zip.sha256"; du -sh "$P" "$DESTINO/SEPOMEX_ANFECA_${VERSION}.zip"
