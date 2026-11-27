#!/usr/bin/env bash
# =============================================================================
# ANFECA · Huellas SHA-256 de los documentos de las solicitudes, obtenidas por HTTP como coordinación nacional.
# SOLO LECTURA (GET); no imprime contraseñas.
#
#   bash scripts/anfeca/huellas_documentos.sh <url_base> <salida.json>
#   p. ej.: bash scripts/anfeca/huellas_documentos.sh http://192.168.7.130:8086 /ruta/documentos_antes.json
#
# Credenciales: primera fila con rol coordinador_nacional de ~/ANFECA_CREDENCIALES/coordinacion_ensayo_8086.tsv
# (correo, rol, contraseña), o el archivo indicado en ANFECA_CREDENCIALES_TSV. Con ANFECA_DOCUMENTOS_COPIA=<directorio nuevo>
# conserva además una copia privada (600) de cada documento: respaldo funcional cuando el directorio privado pertenece a apache. Formato de salida compatible con
# documentos_huellas.json del punto de recuperación: {"documentos": {"<ruta>": {"http","bytes","sha256"}}}.
# =============================================================================
set -euo pipefail
URL="${1:?url base}"; SALIDA="${2:?archivo de salida}"
CRED="${ANFECA_CREDENCIALES_TSV:-$HOME/ANFECA_CREDENCIALES/coordinacion_ensayo_8086.tsv}"
CAMPOS="titulo_origen_archivo cedula_origen_archivo titulo_ultimo_grado_archivo cedula_ultimo_grado_archivo constancia_antiguedad_archivo comprobante_pago_archivo solicitud_articulo_14_archivo constancia_articulo_14_archivo"
COPIA="${ANFECA_DOCUMENTOS_COPIA:-}"
if [ -n "$COPIA" ]; then [ ! -e "$COPIA" ] || { echo "DETENIDO: $COPIA ya existe" >&2; exit 1; }; mkdir -m 700 -p "$COPIA"; fi
TMP=$(mktemp -d); trap 'rm -rf -- "$TMP"' EXIT; chmod 700 "$TMP"
FILA=$(awk -F'\t' '$2 == "coordinador_nacional" {print; exit}' "$CRED")
[ -n "$FILA" ] || { echo "DETENIDO: no hay credenciales de coordinación nacional" >&2; exit 1; }
CORREO=$(cut -f1 <<< "$FILA"); CLAVE=$(cut -f3 <<< "$FILA")
J="$TMP/cookies"
TOKEN=$(curl -s -c "$J" -b "$J" "$URL/login" | grep -oP 'name="_token" value="\K[^"]+' | head -1)
DESTINO=$(printf '%s' "$CLAVE" | curl -s -o /dev/null -c "$J" -b "$J" -w '%{redirect_url}' --data-urlencode "_token=$TOKEN" \
    --data-urlencode "email=$CORREO" --data-urlencode "password@-" "$URL/login")
unset CLAVE
[[ "$DESTINO" == */coordinacion* ]] || { echo "DETENIDO: no fue posible iniciar sesión como coordinación nacional" >&2; exit 1; }
FOLIOS=""; pagina=1; ultima=1
while [ "$pagina" -le "$ultima" ]; do
    LOTE=$(curl -s -b "$J" -H 'Accept: application/json' "$URL/coordinacion/api/solicitudes?por_pagina=50&pagina=$pagina" \
        | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["meta"]["ultima_pagina"], " ".join(str(x["folio"]) for x in d["datos"]))') \
        || { echo "DETENIDO: respuesta inesperada de la bandeja" >&2; exit 1; }
    ultima=${LOTE%% *}; FOLIOS="$FOLIOS ${LOTE#* }"; pagina=$((pagina + 1))
    [ "$pagina" -le 200 ] || { echo "DETENIDO: demasiadas páginas" >&2; exit 1; }
done
{
    for f in $FOLIOS; do for c in $CAMPOS; do
        ruta="/coordinacion/solicitudes/$f/documentos/$c"
        http=$(curl -s -b "$J" -o "$TMP/doc" -w '%{http_code}' "$URL$ruta")
        [ "$http" = 200 ] && printf '%s\t%s\t%s\t%s\n' "$ruta" "$http" "$(stat -c %s "$TMP/doc")" "$(sha256sum "$TMP/doc" | cut -d' ' -f1)"
        if [ "$http" = 200 ] && [ -n "$COPIA" ]; then install -m 600 "$TMP/doc" "$COPIA/folio${f}_${c}"; fi
        rm -f "$TMP/doc"
    done; done
} | python3 -c '
import json, sys
d = {}
for l in sys.stdin:
    r, h, b, s = l.rstrip("\n").split("\t")
    d[r] = {"http": int(h), "bytes": int(b), "sha256": s}
json.dump({"documentos": dict(sorted(d.items()))}, open(sys.argv[1], "w"), indent=1)
print(f"{len(d)} documentos con huella")' "$SALIDA"
chmod 600 "$SALIDA"
