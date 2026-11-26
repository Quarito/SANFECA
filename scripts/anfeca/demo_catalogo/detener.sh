#!/usr/bin/env bash
# Detiene y elimina por completo la demostración del catálogo (contenedor, copia, SQLite ficticio y copia del catálogo).
set -euo pipefail
DEMO="$HOME/ANFECA_DEMO_CATALOGO"
podman rm -f anfeca-demo-catalogo >/dev/null 2>&1 || true
if [ -d "$DEMO" ]; then
    case "$DEMO" in "$HOME"/ANFECA_DEMO_CATALOGO) ;; *) echo "Ruta inesperada: $DEMO" >&2; exit 1 ;; esac
    podman unshare rm -rf -- "$HOME/ANFECA_DEMO_CATALOGO"
fi
podman container exists anfeca-demo-catalogo && { echo "El contenedor sigue existiendo" >&2; exit 1; }
[ -e "$DEMO" ] && { echo "No se pudo eliminar $DEMO" >&2; exit 1; }
echo "Demostración eliminada. Espacio libre: $(df -Pm "$HOME" | awk 'NR==2 {print $4}') MB"
