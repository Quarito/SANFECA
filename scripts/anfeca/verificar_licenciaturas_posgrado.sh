#!/usr/bin/env bash
# =============================================================================
# ANFECA · Verificación de la migración del catálogo de licenciaturas y la declaración de posgrado.
#   bash scripts/anfeca/verificar_licenciaturas_posgrado.sh      (desde el directorio de la aplicación)
# SOLO LECTURA sobre datos reales; los CHECK se prueban en una TABLA TEMPORAL dentro de BEGIN … ROLLBACK.
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
ok "5 columnas aditivas y nulas" "$(q "select string_agg(column_name||':'||data_type||':'||coalesce(character_maximum_length::text,'-')||':'||is_nullable, ',' order by column_name)
  from information_schema.columns where table_schema='public' and table_name='formaciones_academicas_solicitud'
  and column_name in ('titulo_origen_especifico','origen_titulo_origen','clave_licenciatura_catalogo','version_catalogo_licenciaturas','cuenta_con_posgrado')")" \
  "clave_licenciatura_catalogo:character varying:40:YES,cuenta_con_posgrado:boolean:-:YES,origen_titulo_origen:character varying:20:YES,titulo_origen_especifico:character varying:300:YES,version_catalogo_licenciaturas:character varying:60:YES"
ok "CHECK de título de origen y de declaración validados" "$(q "select string_agg(conname||':'||convalidated, ',' order by conname) from pg_constraint where conrelid='$T'::regclass and conname in ('fas_titulo_origen_coherente_ck','fas_declaracion_posgrado_ck')")" "fas_declaracion_posgrado_ck:true,fas_titulo_origen_coherente_ck:true"
ok "Columnas del catálogo de posgrados intactas" "$(q "select count(*) from information_schema.columns where table_name='formaciones_academicas_solicitud' and column_name in ('titulo_ultimo_grado_especifico','origen_titulo_ultimo_grado','clave_posgrado_catalogo','version_catalogo_posgrados')")" 4
ok "Migración registrada una vez" "$(q "select count(*) from migrations where migration='2026_11_28_000000_add_titulo_origen_especifico_y_declaracion_posgrado'")" 1
echo "  · Formaciones: $(q "select count(*) from $T") · con declaración: $(q "select count(*) from $T where cuenta_con_posgrado is not null") (solo licenciatura: $(q "select count(*) from $T where cuenta_con_posgrado = false")) · con título de origen específico: $(q "select count(*) from $T where origen_titulo_origen is not null")"
RES=$(psql -v ON_ERROR_STOP=1 -At <<'SQL' 2>&1
begin;
create temp table t_lic (like public.formaciones_academicas_solicitud including constraints) on commit drop;
do $$ declare c text; begin
  for c in select a.attname from pg_attribute a where a.attrelid = 't_lic'::regclass and a.attnum > 0 and not a.attisdropped and a.attnotnull loop
    execute format('alter table t_lic alter column %I drop not null', c);
  end loop; end $$;
create temp table t_res (caso text, aceptado bool) on commit drop;
do $$ declare
  casos text[][] := array[
    ['valido_solo_licenciatura', 'catalogo', 'Ingeniería', 'LIC-0123456789AB', 'V1', 'false', null, null],
    ['valido_con_posgrado', 'manual', 'Lic. antigua', null, null, 'true', 'Maestría', 'Administración'],
    ['valido_historico', null, null, null, null, null, 'Licenciatura', 'Administración'],
    ['origen_desconocido', 'otro', 'X', null, null, null, null, null],
    ['manual_con_clave', 'manual', 'X', 'LIC-0123456789AB', null, null, null, null],
    ['catalogo_clave_posgrado', 'catalogo', 'X', 'MAE-0123456789AB', 'V1', null, null, null],
    ['titulo_sin_origen', null, 'X', null, null, null, null, null],
    ['posgrado_licenciatura', null, null, null, null, 'true', 'Licenciatura', 'Administración'],
    ['posgrado_sin_area', null, null, null, null, 'true', 'Doctorado', null]];
  i int;
begin
  for i in 1 .. array_length(casos, 1) loop
    begin
      insert into t_lic (origen_titulo_origen, titulo_origen_especifico, clave_licenciatura_catalogo, version_catalogo_licenciaturas,
                         cuenta_con_posgrado, nivel_ultimo_grado, titulo_ultimo_grado)
        values (casos[i][2], casos[i][3], casos[i][4], casos[i][5], casos[i][6]::boolean, casos[i][7], casos[i][8]);
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
ok "CHECK: 3 válidas aceptadas y 6 incoherentes rechazadas (tabla temporal, ROLLBACK)" "$RES" \
  "catalogo_clave_posgrado=rechazado,manual_con_clave=rechazado,origen_desconocido=rechazado,posgrado_licenciatura=rechazado,posgrado_sin_area=rechazado,titulo_sin_origen=rechazado,valido_con_posgrado=aceptado,valido_historico=aceptado,valido_solo_licenciatura=aceptado"
echo "Fallas: $FALLAS"
exit $FALLAS
