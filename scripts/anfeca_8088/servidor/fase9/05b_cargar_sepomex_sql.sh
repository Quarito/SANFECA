#!/bin/bash
# BLOQUE 5B · CARGA DEL CATÁLOGO CON LOS SCRIPTS SQL (alternativa B, equivalente al bloque 5 con Artisan; basta una).
# Una sola transacción con bloqueo; inserta SOLO lo faltante en entidad_federativa, alcaldia_municipio y colonia; ante
# cualquier error se revierte completa. No modifica otras tablas. No requiere PHP ni Laravel.
# Uso: CONFIRMAR=si RESPALDO=/var/backups/anfeca8088/fase9_<fecha> bash 05b_cargar_sepomex_sql.sh
source "$(dirname "$0")/comun.inc.sh"
exigir_confirmacion; exigir_respaldo
pedir_clave
paso "Paquete SQL"; exigir_paquete
[ -f "$PREPARACION/artisan" ] || alto "Ejecute primero el bloque 3."
paso "Estructura oficial de $BD antes de la carga"; exigir_esquema_oficial artisan_prep
paso "Carga (SEPOMEX_CATALOGO_COMPLETO.sql) en $BD"
(cd "$PAQUETE_SQL/sql" && "$PG_BIN/psql" -X -U "$ROL_APP" -d "$BD" -v ON_ERROR_STOP=1 -f SEPOMEX_CATALOGO_COMPLETO.sql) > "$SALIDA/carga.log" 2>&1
r=$?; grep -aE "^Insertados|^Marcas|Transacción confirmada|DETENIDO|ERROR" "$SALIDA/carga.log"
[ $r = 0 ] || alto "La carga no se completó (rc=$r): la transacción se revirtió completa. Revise $SALIDA/carga.log."
grep -a "^Marcas de reversión" "$SALIDA/carga.log" | sed "s/^.*): //" > "$SALIDA/marcas_reversion.txt"
cp "$SALIDA/marcas_reversion.txt" "$RESPALDO/marcas_reversion_sepomex.txt" 2>/dev/null || true
paso "Idempotencia: una segunda ejecución no debe insertar nada"
(cd "$PAQUETE_SQL/sql" && "$PG_BIN/psql" -X -U "$ROL_APP" -d "$BD" -v ON_ERROR_STOP=1 -f SEPOMEX_CATALOGO_COMPLETO.sql) > "$SALIDA/repeticion.log" 2>&1 \
    || alto "La repetición falló (rc=$?)."
grep -a "^Insertados" "$SALIDA/repeticion.log"
grep -aq "^Insertados · entidades: 0 · municipios: 0 · colonias: 0" "$SALIDA/repeticion.log" || alto "La repetición insertó registros: revisar."
paso "Verificación (04): cobertura por entidad e integridad"
sed -n '/5\/5 · Verificación final/,$p' "$SALIDA/carga.log" | sed -n '1,60p'
paso "Estructura y expedientes sin cambios"
exigir_esquema_oficial artisan_prep
while IFS='|' read -r t n; do
    case "$t" in entidad_federativa|alcaldia_municipio|colonia) continue;; esac
    actual=$(leer "select count(*) from public.$t"); [ "$actual" = "$n" ] || echo "AVISO: $t tenía $n filas y ahora $actual (actividad de usuarios o diferencia a revisar)"
done < "$RESPALDO/conteos.txt"
echo; echo "Catálogo cargado con SQL. Conserve $SALIDA/marcas_reversion.txt (copiado también al respaldo)."
