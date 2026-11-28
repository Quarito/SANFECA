#!/bin/bash
# BLOQUE 2 · RESPALDO Y VERIFICACIÓN. Lee la base y los archivos; escribe solo en $RESPALDOS.
# La prueba de restauración crea una base NUEVA (certificacion_anfeca_restauracion_<fecha>) en el clúster del 8088;
# no se borra ni se sobrescribe ninguna base existente. Uso: sudo -E bash 02_respaldo.sh
source "$(dirname "$0")/comun.inc.sh"
FECHA=$(date +%Y%m%d_%H%M%S); DEST="$RESPALDOS/fase9_$FECHA"; BD_RESTAURACION="${BD}_restauracion_${FECHA}"
install -d -m 700 "$DEST"
pedir_clave
paso "Identidad del clúster (debe ser el exclusivo del 8088)"
leer "select current_setting('data_directory'), inet_server_port()" | tee "$DEST/cluster.txt"
paso "Volcado lógico de $BD (pg_dump -Fc) y roles"
"$PG_BIN/pg_dump" -U "$ROL_APP" -d "$BD" -Fc -f "$DEST/$BD.dump" || alto "Falló pg_dump."
"$PG_BIN/pg_restore" -l "$DEST/$BD.dump" > "$DEST/contenido_volcado.txt" || alto "El volcado no es legible."
paso "Conteo por tabla (instantánea de referencia)"
leer "select table_name from information_schema.tables where table_schema='public' and table_type='BASE TABLE' order by 1" > "$DEST/tablas.txt"
while read -r t; do echo "$t|$(leer "select count(*) from public.$t")"; done < "$DEST/tablas.txt" | tee "$DEST/conteos.txt"
paso "Código, configuración y expedientes"
tar -C "$(dirname "$APP")" --exclude="$(basename "$APP")/storage/framework" --exclude="$(basename "$APP")/storage/logs" \
    -czf "$DEST/codigo_desplegado.tgz" "$(basename "$APP")"
install -m 600 "$APP/.env" "$DEST/env.respaldo"
tar -czf "$DEST/configuracion_servidor.tgz" /etc/httpd/conf.d/*8088* /etc/systemd/system/*anfeca8088* /etc/anfeca8088 2>/dev/null || true
find "$APP/storage/app/private" -type f -exec sha256sum {} + 2>/dev/null | sort -k2 > "$DEST/huellas_expedientes.txt"
git -C "$REPO" rev-parse HEAD > "$DEST/commit_repo.txt"
(cd "$DEST" && sha256sum ./*.dump ./*.tgz env.respaldo huellas_expedientes.txt conteos.txt > SHA256SUMS && sha256sum -c SHA256SUMS) || alto "Huellas inconsistentes."
paso "Prueba de restauración en una base NUEVA: $BD_RESTAURACION"
admin_psql -v ON_ERROR_STOP=1 -c "create database $BD_RESTAURACION owner $ROL_APP" || alto "No se pudo crear la base de restauración."
"$PG_BIN/pg_restore" -U "$ROL_APP" -d "$BD_RESTAURACION" --no-owner --exit-on-error "$DEST/$BD.dump" || alto "La restauración falló."
while read -r t; do echo "$t|$(leer "select count(*) from public.$t" "$ROL_APP" "$BD_RESTAURACION")"; done < "$DEST/tablas.txt" > "$DEST/conteos_restaurada.txt"
diff -u "$DEST/conteos.txt" "$DEST/conteos_restaurada.txt" && echo "Conteos idénticos en la base restaurada." || alto "La base restaurada difiere."
echo "$BD_RESTAURACION" > "$DEST/base_restaurada.txt"
echo; echo "Respaldo verificado: $DEST"
echo "La base $BD_RESTAURACION queda como evidencia; puede retirarse con autorización expresa."
