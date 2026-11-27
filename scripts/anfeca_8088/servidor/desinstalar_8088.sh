#!/bin/bash
# ANFECA 8088 · Reversión de la INSTALACIÓN del 8088 (solo artefactos propios del 8088). Simula por omisión.
# Antes de usarla ejecute respaldo_8088.sh. No toca 8086, 8008, producción ni otros clústeres o sitios.
set -euo pipefail
EJECUTAR=0; [ "${1:-}" = "--ejecutar" ] && EJECUTAR=1
run() { printf '    $ %s\n' "$*"; [ "$EJECUTAR" = 1 ] && eval "$@" || true; }
APP="${APP:-/var/www/ANFECA_8088}"; PG_DATOS="${PG_DATOS:-/var/lib/pgsql/18/data_anfeca8088}"
run "rm -f /etc/httpd/conf.d/anfeca-8088.conf && apachectl configtest && systemctl reload httpd"
run "firewall-cmd --permanent --remove-port=8088/tcp && firewall-cmd --reload"
run "systemctl disable --now php-fpm-anfeca8088 && rm -f /etc/systemd/system/php-fpm-anfeca8088.service"
run "systemctl disable --now postgresql-18-anfeca8088 && rm -f /etc/systemd/system/postgresql-18-anfeca8088.service && systemctl daemon-reload"
# Los datos NO se borran: se renombran para conservarlos hasta confirmar la reversión.
run "mv '$PG_DATOS' '$PG_DATOS.retirado_$(date +%Y%m%d%H%M%S)'"
run "mv '$APP' '$APP.retirado_$(date +%Y%m%d%H%M%S)'"
[ "$EJECUTAR" = 1 ] || echo "SIMULACIÓN: no se ejecutó nada."
