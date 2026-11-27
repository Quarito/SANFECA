#!/usr/bin/env bash
# Diagnóstico ANFECA: exclusivamente lectura, sin cambios en aplicaciones ni bases.
set -uo pipefail
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"
INVENTARIO="$ROOT/scripts/anfeca/inventario_servidor.php"
[[ -f "$INVENTARIO" ]] || { echo "Falta el inventario del PR #13" >&2; exit 2; }
printf 'ANFECA — diagnóstico previo (%s)\n' "$(date -Is)"
printf 'Checkout de auditoría: '; git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || echo desconocido
for cmd in php psql pg_dump pg_restore podman; do
  if command -v "$cmd" >/dev/null 2>&1; then
    case "$cmd" in
      php) php -v | head -1 ;;
      podman) podman --version ;;
      *) "$cmd" --version | head -1 ;;
    esac
  else echo "$cmd: no disponible"; fi
done
echo 'Espacio de almacenamiento (sin escrituras):'
df -Ph "$HOME" /var/tmp 2>/dev/null || true
fallos=0
for app in /var/www/ANFECA_ENSAYO /var/www/anfeca; do
  printf '\n=== %s ===\n' "$app"
  if [[ ! -d "$app" ]]; then
    echo 'Instalación ausente en este host; inspeccionar en el host correspondiente.'
    continue
  fi
  printf 'HEAD: '; git -C "$app" rev-parse --short HEAD 2>/dev/null || echo desconocido
  printf 'Rama: '; git -C "$app" branch --show-current 2>/dev/null || echo desconocida
  printf 'Espacio: '; df -Ph "$app" | tail -1 || true
  if ! php "$INVENTARIO" "$app"; then
    echo 'Inventario incompleto; revisar permisos/conexión sin cambiar nada.' >&2
    fallos=$((fallos+1))
  fi
done
echo 'No se realizaron respaldos, migraciones ni cambios de configuración.'
echo 'Antes de restaurar: verificar topología, capacidad real de datos y tamaño del volumen temporal.'
[[ "$fallos" -eq 0 ]]
