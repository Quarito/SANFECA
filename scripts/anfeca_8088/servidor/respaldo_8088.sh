#!/bin/bash
# ANFECA 8088 · Respaldo VERIFICADO (solo lectura sobre la base): volcado lógico de certificacion_anfeca del clúster
# exclusivo del 8088 + expedientes + .env, con huellas SHA-256. No detiene servicios ni modifica datos.
# Uso: sudo ./respaldo_8088.sh [/ruta/de/respaldos]
set -euo pipefail
APP="${APP:-/var/www/ANFECA_8088}"; PG_BIN="${PG_BIN:-/usr/pgsql-18/bin}"; PG_PUERTO="${PG_PUERTO:-5488}"
DESTINO="${1:-/var/backups/anfeca8088}/$(date +%Y%m%d_%H%M%S)"
install -d -m 700 "$DESTINO"
# Identidad del clúster: debe ser el del 8088 (nunca el de 8086).
DATOS=$(sudo -u postgres "$PG_BIN/psql" -h 127.0.0.1 -p "$PG_PUERTO" -Atc 'show data_directory')
case "$DATOS" in *anfeca8088*) ;; *) echo "El puerto $PG_PUERTO no corresponde al clúster del 8088 ($DATOS)."; exit 1;; esac
sudo -u postgres "$PG_BIN/pg_dump" -h 127.0.0.1 -p "$PG_PUERTO" -d certificacion_anfeca -Fc -f /tmp/anfeca8088.dump
mv /tmp/anfeca8088.dump "$DESTINO/certificacion_anfeca.dump"
"$PG_BIN/pg_restore" -l "$DESTINO/certificacion_anfeca.dump" > "$DESTINO/contenido.txt"   # el volcado es legible
tar -C "$APP/storage/app" -czf "$DESTINO/expedientes.tgz" private
install -m 600 "$APP/.env" "$DESTINO/env.respaldo"
git -C "$APP" rev-parse HEAD > "$DESTINO/commit.txt"
(cd "$DESTINO" && sha256sum certificacion_anfeca.dump expedientes.tgz env.respaldo commit.txt > SHA256SUMS && sha256sum -c SHA256SUMS)
echo "Respaldo verificado en $DESTINO"
