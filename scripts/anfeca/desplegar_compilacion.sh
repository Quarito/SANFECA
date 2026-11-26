#!/usr/bin/env bash
# =============================================================================
# ANFECA · Despliegue CONTROLADO de la compilación Vite y recuperación de la anterior.
#
#   bash scripts/anfeca/desplegar_compilacion.sh desplegar <directorio public/build nuevo> <directorio de respaldo>
#   bash scripts/anfeca/desplegar_compilacion.sh restaurar <manifest.json anterior>
#
# desplegar: respalda public/build (tar + manifiestos), AGREGA los recursos nuevos junto a los existentes
#            (nunca borra ni sobrescribe uno distinto), verifica que existan todos los archivos que cita el
#            manifiesto nuevo y lo sustituye de forma atómica (mv). El manifiesto nunca apunta a archivos inexistentes.
# restaurar: vuelve a poner el manifiesto anterior (atómico) tras verificar que sus recursos siguen presentes.
# Debe ejecutarse desde la raíz de la aplicación.
# =============================================================================
set -euo pipefail
ACCION="${1:?desplegar|restaurar}"; DEST=public/build
detener() { echo "DETENIDO: $*" >&2; exit 1; }
recursos() { python3 -c 'import json,sys; m=json.load(open(sys.argv[1])); print("\n".join(sorted({f for v in m.values() for f in [v["file"]]+v.get("css",[])+v.get("assets",[])})))' "$1"; }
verificar() { local m=$1 base=$2 f falta=0; for f in $(recursos "$m"); do [ -s "$base/$f" ] || { echo "FALTA $f" >&2; falta=1; }; done; [ $falta = 0 ]; }
[ -f artisan ] && [ -d "$DEST/assets" ] || detener "ejecute desde la raíz de la aplicación"

case "$ACCION" in
desplegar)
    NUEVO="${2:?directorio public/build nuevo}"; RESP="${3:?directorio de respaldo}"
    [ -f "$NUEVO/manifest.json" ] || detener "no existe $NUEVO/manifest.json"
    verificar "$NUEVO/manifest.json" "$NUEVO" || detener "la compilación nueva está incompleta"
    mkdir -p "$RESP"; chmod 700 "$RESP"
    tar -czf "$RESP/public_build_antes.tar.gz" "$DEST"; cp "$DEST/manifest.json" "$RESP/manifest_anterior.json"
    [ -f "$DEST/fonts-manifest.json" ] && cp "$DEST/fonts-manifest.json" "$RESP/fonts-manifest_anterior.json"
    for f in "$NUEVO"/assets/*; do b=$(basename "$f")
        if [ -e "$DEST/assets/$b" ]; then cmp -s "$f" "$DEST/assets/$b" || detener "conflicto: $b existe con otro contenido"
        else cp "$f" "$DEST/assets/$b.tmp" && mv "$DEST/assets/$b.tmp" "$DEST/assets/$b" && echo "agregado $b"; fi
    done
    verificar "$NUEVO/manifest.json" "$DEST" || detener "faltan recursos en $DEST; el manifiesto NO se cambió"
    if [ -f "$NUEVO/fonts-manifest.json" ] && ! cmp -s "$NUEVO/fonts-manifest.json" "$DEST/fonts-manifest.json"; then
        cp "$NUEVO/fonts-manifest.json" "$DEST/fonts-manifest.json.tmp" && mv "$DEST/fonts-manifest.json.tmp" "$DEST/fonts-manifest.json"; fi
    cp "$NUEVO/manifest.json" "$DEST/manifest.json.tmp" && mv "$DEST/manifest.json.tmp" "$DEST/manifest.json"
    echo "Desplegado: $(grep -o 'assets/app-[^"]*\.js' "$DEST/manifest.json" | head -1) · anterior en $RESP/manifest_anterior.json" ;;
restaurar)
    ANT="${2:?manifest.json anterior}"
    verificar "$ANT" "$DEST" || detener "faltan recursos de la compilación anterior: restaure primero public_build_antes.tar.gz"
    cp "$ANT" "$DEST/manifest.json.tmp" && mv "$DEST/manifest.json.tmp" "$DEST/manifest.json"
    echo "Restaurada: $(grep -o 'assets/app-[^"]*\.js' "$DEST/manifest.json" | head -1)" ;;
*) detener "acción desconocida: $ACCION" ;;
esac
