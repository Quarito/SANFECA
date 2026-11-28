#!/bin/bash
# BLOQUE 4 · DIAGNÓSTICO DEL CATÁLOGO, SIN ESCRITURAS (transacción de solo lectura en la base del 8088).
# Requiere el bloque 3 y el archivo oficial en $SEPOMEX. Uso: bash 04_diagnostico_sepomex.sh  [BD_RESTAURACION=… opcional]
source "$(dirname "$0")/comun.inc.sh"
[ -f "$PREPARACION/artisan" ] || alto "Ejecute primero el bloque 3."
[ -r "$SEPOMEX" ] || alto "No se puede leer $SEPOMEX (cópielo fuera de public/ y de Git)."
paso "Huella del archivo oficial"
HUELLA=$(sha256sum "$SEPOMEX" | cut -d' ' -f1); echo "$HUELLA"
[ "$HUELLA" = "$SEPOMEX_SHA256" ] || alto "La huella no coincide con la validada ($SEPOMEX_SHA256)."
paso "Estructura oficial de la base $BD"; artisan_prep anfeca:verificar-esquema || alto "La estructura no coincide con el SQL oficial."
if [ -n "${BD_RESTAURACION:-}" ]; then
    paso "Estructura de la base restaurada $BD_RESTAURACION"; ENTORNO="DB_DATABASE=$BD_RESTAURACION" artisan_prep anfeca:verificar-esquema
fi
paso "Diagnóstico SEPOMEX (solo lectura)"
artisan_prep anfeca:sepomex "$SEPOMEX" --json --reporte="$SALIDA/sepomex_consolidados.csv" | tee "$SALIDA/diagnostico_sepomex.json" || alto "El diagnóstico SEPOMEX encontró inconsistencias: resuélvalas antes de aplicar."
python3 - "$SALIDA/diagnostico_sepomex.json" <<'P'
import json, sys
t = open(sys.argv[1], encoding='utf-8').read(); d = json.loads(t[t.index('{'):])
print(f"Entidades {d['entidades']} · municipios {d['municipios']} · colonias {d['colonias_distintas']} · consolidados {d['repetidos_solo_por_tipo_de_asentamiento']}")
print(f"Pendientes de insertar: entidades {d['entidades_nuevas']} · municipios {d['municipios_nuevos']} · colonias {d['colonias_nuevas']}")
print(f"Entidades de la base fuera del catálogo: {d['entidades_ajenas_al_catalogo'] or 'ninguna'} · {d['segundos']} s · {d['memoria_pico_mb']} MB")
P
echo; echo "Sin escrituras. Si el comando terminó sin «DETENIDO»/errores de conciliación, puede continuar al bloque 5."
