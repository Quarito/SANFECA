#!/bin/bash
# BLOQUE 5 · APLICACIÓN CONTROLADA DEL CATÁLOGO en la base del 8088: inserta SOLO lo faltante en entidad_federativa,
# alcaldia_municipio y colonia (una transacción; nunca actualiza ni elimina). No toca otras tablas.
# Uso: CONFIRMAR=si RESPALDO=/var/backups/anfeca8088/fase9_<fecha> bash 05_aplicar_sepomex.sh
source "$(dirname "$0")/comun.inc.sh"
exigir_confirmacion; exigir_respaldo
[ -f "$PREPARACION/artisan" ] || alto "Ejecute primero el bloque 3."
pedir_clave
paso "Aplicación (huella $SEPOMEX_SHA256, base $BD)"
artisan_prep anfeca:sepomex "$SEPOMEX" --aplicar --base="$BD" --sha256="$SEPOMEX_SHA256" --json | tee "$SALIDA/aplicacion.json" \
    || alto "La aplicación no se completó (la transacción se revierte completa)."
paso "Idempotencia: un nuevo diagnóstico debe dar 0 pendientes"
artisan_prep anfeca:sepomex "$SEPOMEX" --json | tee "$SALIDA/diagnostico_posterior.json"
python3 - "$SALIDA/diagnostico_posterior.json" <<'P' || exit 1
import json, sys
t = open(sys.argv[1], encoding='utf-8').read(); d = json.loads(t[t.index('{'):])
p = (d['entidades_nuevas'], d['municipios_nuevos'], d['colonias_nuevas'])
print('Pendientes:', p, '· totales:', d['totales_base'])
sys.exit(0 if p == (0, 0, 0) else 'Quedaron registros pendientes.')
P
paso "Estructura y expedientes sin cambios"
artisan_prep anfeca:verificar-esquema || alto "La estructura cambió."
while IFS='|' read -r t n; do
    case "$t" in entidad_federativa|alcaldia_municipio|colonia) continue;; esac
    actual=$(leer "select count(*) from public.$t"); [ "$actual" = "$n" ] || echo "AVISO: $t tenía $n filas y ahora $actual (actividad de usuarios o diferencia a revisar)"
done < "$RESPALDO/conteos.txt"
echo; echo "Catálogo aplicado. Registros de expedientes revisados contra el respaldo."
