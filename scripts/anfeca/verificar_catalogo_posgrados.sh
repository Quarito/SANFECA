#!/usr/bin/env bash
# =============================================================================
# ANFECA · Verificación de la migración del catálogo de posgrados (columnas aditivas + CHECK).
#   bash scripts/anfeca/verificar_catalogo_posgrados.sh      (desde el directorio de la aplicación)
# SOLO LECTURA sobre datos reales. La prueba activa del CHECK se hace en una TABLA TEMPORAL creada con
# «LIKE … INCLUDING CONSTRAINTS» dentro de BEGIN … ROLLBACK: nunca se insertan ni actualizan filas reales.
# =============================================================================
set -uo pipefail
valor() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
export PGHOST="${PGHOST:-$(valor DB_HOST)}" PGPORT="${PGPORT:-$(valor DB_PORT)}" PGUSER="${PGUSER:-$(valor DB_USERNAME)}" \
       PGPASSWORD="${PGPASSWORD:-$(valor DB_PASSWORD)}" PGDATABASE="${PGDATABASE:-$(valor DB_DATABASE)}"
source "$(dirname "$0")/pg_herramientas.inc.sh"
anfeca_pg_preparar
FALLAS=0
ok() { if [ "$2" = "$3" ]; then echo "  ✔ $1"; else echo "  ✘ $1 (esperado: $3; obtenido: $2)"; FALLAS=$((FALLAS+1)); fi; }
q() { psql -Atc "$1"; }
T=public.formaciones_academicas_solicitud

echo "Base: $PGDATABASE"
ok "4 columnas aditivas, varchar y nulas" "$(q "select string_agg(column_name||':'||character_maximum_length||':'||is_nullable, ',' order by column_name)
  from information_schema.columns where table_schema='public' and table_name='formaciones_academicas_solicitud'
  and column_name in ('titulo_ultimo_grado_especifico','origen_titulo_ultimo_grado','clave_posgrado_catalogo','version_catalogo_posgrados')
  and data_type='character varying'")" "clave_posgrado_catalogo:40:YES,origen_titulo_ultimo_grado:20:YES,titulo_ultimo_grado_especifico:300:YES,version_catalogo_posgrados:40:YES"
ok "CHECK fas_titulo_especifico_coherente_ck validado" "$(q "select convalidated from pg_constraint where conrelid='$T'::regclass and conname='fas_titulo_especifico_coherente_ck'")" t
ok "CHECK con protección contra NULL (coalesce)" "$(q "select pg_get_constraintdef(oid) like '%COALESCE%' from pg_constraint where conname='fas_titulo_especifico_coherente_ck'")" t
ok "Migración registrada una vez" "$(q "select count(*) from migrations where migration='2026_11_27_000000_add_titulo_especifico_to_formaciones_academicas_solicitud'")" 1
echo "  · Formaciones: $(q "select count(*) from $T") · con título específico: $(q "select count(*) from $T where origen_titulo_ultimo_grado is not null") (catálogo: $(q "select count(*) from $T where origen_titulo_ultimo_grado='catalogo'"), manual: $(q "select count(*) from $T where origen_titulo_ultimo_grado='manual'"))"
echo "  · escuela_facultad registrada: $(q "select count(*) from migrations where migration like '%escuela_facultad%'") (informativo; no la aplica este procedimiento)"

# Prueba activa del CHECK en tabla temporal (sin filas reales; ROLLBACK).
RES=$(psql -v ON_ERROR_STOP=1 -At <<'SQL' 2>&1
begin;
create temp table t_cat (like public.formaciones_academicas_solicitud including constraints) on commit drop;
do $$ declare c text; begin
  for c in select a.attname from pg_attribute a where a.attrelid = 't_cat'::regclass and a.attnum > 0 and not a.attisdropped and a.attnotnull loop
    execute format('alter table t_cat alter column %I drop not null', c);
  end loop; end $$;
create temp table t_res (caso text, aceptado bool) on commit drop;
do $$ declare
  casos text[][] := array[
    ['valido_vacio', null, null, null, null],
    ['valido_catalogo', 'catalogo', 'Título', 'MAE-00000000000A', 'V1'],
    ['valido_manual', 'manual', 'Título manual', null, null],
    ['origen_desconocido', 'otro', 'Título', null, null],
    ['manual_con_clave', 'manual', 'Título', 'MAE-00000000000A', null],
    ['manual_con_version', 'manual', 'Título', null, 'V1'],
    ['catalogo_sin_clave', 'catalogo', 'Título', null, 'V1'],
    ['catalogo_clave_invalida', 'catalogo', 'Título', 'XYZ', 'V1'],
    ['catalogo_sin_version', 'catalogo', 'Título', 'MAE-00000000000A', null],
    ['titulo_en_blanco', 'manual', '   ', null, null],
    ['titulo_sin_origen', null, 'Título', null, null]];
  i int;
begin
  for i in 1 .. array_length(casos, 1) loop
    begin
      insert into t_cat (origen_titulo_ultimo_grado, titulo_ultimo_grado_especifico, clave_posgrado_catalogo, version_catalogo_posgrados)
        values (casos[i][2], casos[i][3], casos[i][4], casos[i][5]);
      insert into t_res values (casos[i][1], true);
    exception when check_violation then
      insert into t_res values (casos[i][1], false);
    end;
  end loop;
end $$;
select string_agg(caso || '=' || case when aceptado then 'aceptado' else 'rechazado' end, ',' order by caso) from t_res;
rollback;
SQL
)
RES=$(grep -E '=' <<< "$RES" | tail -1)
ok "CHECK: 3 combinaciones válidas aceptadas y 8 incoherentes rechazadas (tabla temporal, ROLLBACK)" "$RES" \
  "catalogo_clave_invalida=rechazado,catalogo_sin_clave=rechazado,catalogo_sin_version=rechazado,manual_con_clave=rechazado,manual_con_version=rechazado,origen_desconocido=rechazado,titulo_en_blanco=rechazado,titulo_sin_origen=rechazado,valido_catalogo=aceptado,valido_manual=aceptado,valido_vacio=aceptado"
echo "Fallas: $FALLAS"
exit $FALLAS
