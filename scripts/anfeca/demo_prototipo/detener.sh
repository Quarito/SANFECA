#!/usr/bin/env bash
# Detiene y elimina por completo la demostración del prototipo (contenedor, copia y base SQLite ficticia).
set -euo pipefail
DEMO="$HOME/ANFECA_DEMO_PROTOTIPO"
podman rm -f anfeca-demo-prototipo >/dev/null 2>&1 || true
if [ -d "$DEMO" ]; then
    case "$DEMO" in "$HOME"/ANFECA_DEMO_PROTOTIPO) ;; *) echo "Ruta inesperada: $DEMO" >&2; exit 1 ;; esac
    podman unshare rm -rf -- "$HOME/ANFECA_DEMO_PROTOTIPO"
fi
podman container exists anfeca-demo-prototipo && { echo "El contenedor sigue existiendo" >&2; exit 1; }
[ -e "$DEMO" ] && { echo "No se pudo eliminar $DEMO" >&2; exit 1; }
echo "Demostración eliminada."
