#!/bin/bash
# LABORATORIO · Prueba de la clasificación de anfeca:verificar-esquema en los bloques (0/2/3/4/5) y de la detención.
# Requiere: COPIA (copia de prueba de ANFECA con vendor/), BASE_OK (modelo oficial), BASE_DIF (simular_diferencias_8088.sql),
# PGHOST, PGPORT, ROL, ROL_CLAVE, PHP, SALIDA. Solo bases de PRUEBA. Oculta y restaura el SQL oficial SOLO en la COPIA.
: "${COPIA:?}" "${BASE_OK:?}" "${BASE_DIF:?}" "${ROL:?}" "${ROL_CLAVE:?}" "${SALIDA:?}"
S=$SALIDA; mkdir -p "$S/prueba_esquema"
export PREPARACION=$COPIA PHP=${PHP:-php} SALIDA=$S/prueba_esquema BD=prueba
source "$(dirname "$0")/../fase9/comun.inc.sh" > /dev/null
C="DB_HOST=$PGHOST DB_PORT=$PGPORT DB_USERNAME=$ROL"
ok=0; n=0
caso() { n=$((n+1)); local esperado=$1; shift; "$@" > $S/prueba_esquema/caso_$n.txt 2>&1; local rc=$?; local l=$(grep "^ESQUEMA" $S/prueba_esquema/caso_$n.txt | tail -1)
  [ "$rc" = "$esperado" ] && { ok=$((ok+1)); echo "APROBADA código $rc · $l"; } || echo "FALLIDA  esperado $esperado, obtenido $rc · $l"; }
ENTORNO="$C DB_PASSWORD=$ROL_CLAVE DB_DATABASE=$BASE_OK" caso 0 verificar_esquema artisan_prep
ENTORNO="$C DB_PASSWORD=$ROL_CLAVE DB_DATABASE=$BASE_DIF" caso 2 verificar_esquema artisan_prep
ENTORNO="$C DB_PASSWORD=incorrecta DB_DATABASE=$BASE_OK" caso 3 verificar_esquema artisan_prep
ENTORNO="$C DB_PASSWORD=$ROL_CLAVE DB_DATABASE=no_existe_base" caso 3 verificar_esquema artisan_prep
F=$PREPARACION/database/sql/oficial/anfeca_modelo_powerdesigner_certificacion_anfeca_sin_esquema.sql; mv $F $F.oculto   # solo en la copia de laboratorio
ENTORNO="$C DB_PASSWORD=$ROL_CLAVE DB_DATABASE=$BASE_OK" caso 4 verificar_esquema artisan_prep; mv $F.oculto $F
sin_comando() { [ "$1" = list ] && echo "about   Display basic information"; return 0; }
caso 5 verificar_esquema sin_comando
version_anterior() { [ "$1" = list ] && { echo "anfeca:verificar-esquema  Compara"; return 0; }; echo "15 diferencias:"; echo " - x"; return 1; }
caso 2 verificar_esquema version_anterior
anterior_conexion() { [ "$1" = list ] && { echo "anfeca:verificar-esquema  Compara"; return 0; }; echo "SQLSTATE[08006] [7] connection to server failed"; return 1; }
caso 3 verificar_esquema anterior_conexion
# Bloques: con diferencias se detienen; con lista autorizada idéntica continúan; con lista distinta se detienen.
( ENTORNO="$C DB_PASSWORD=$ROL_CLAVE DB_DATABASE=$BASE_DIF" exigir_esquema_oficial artisan_prep ) > $S/prueba_esquema/exigir1.txt 2>&1; r=$?; n=$((n+1)); [ $r != 0 ] && grep -q DETENIDO $S/prueba_esquema/exigir1.txt && { ok=$((ok+1)); echo "APROBADA el bloque se detiene con diferencias"; } || echo "FALLIDA detención"
grep '^ - ' $(ls -t $S/prueba_esquema/esquema_*.txt | head -1) > $S/prueba_esquema/autorizadas.txt
( DIFERENCIAS_AUTORIZADAS=$S/prueba_esquema/autorizadas.txt ENTORNO="$C DB_PASSWORD=$ROL_CLAVE DB_DATABASE=$BASE_DIF" exigir_esquema_oficial artisan_prep ) > $S/prueba_esquema/exigir2.txt 2>&1; r=$?; n=$((n+1)); [ $r = 0 ] && { ok=$((ok+1)); echo "APROBADA continúa solo con la lista autorizada idéntica"; } || echo "FALLIDA lista autorizada"
head -14 $S/prueba_esquema/autorizadas.txt > $S/prueba_esquema/autorizadas_parcial.txt
( DIFERENCIAS_AUTORIZADAS=$S/prueba_esquema/autorizadas_parcial.txt ENTORNO="$C DB_PASSWORD=$ROL_CLAVE DB_DATABASE=$BASE_DIF" exigir_esquema_oficial artisan_prep ) > $S/prueba_esquema/exigir3.txt 2>&1; r=$?; n=$((n+1)); [ $r != 0 ] && { ok=$((ok+1)); echo "APROBADA se detiene si la lista autorizada no coincide"; } || echo "FALLIDA lista parcial"
echo "Resultado: $ok/$n verificaciones aprobadas."
