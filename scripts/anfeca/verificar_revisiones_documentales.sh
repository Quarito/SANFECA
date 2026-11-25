#!/usr/bin/env bash
# =============================================================================
# ANFECA · Verificación posterior de revisiones_documentales (SOLO LECTURA + 1 prueba con ROLLBACK).
#   bash scripts/anfeca/verificar_revisiones_documentales.sh        (desde el directorio de la aplicación)
# Lee la conexión de .env (o de PG* ya exportadas). No inserta revisiones: la única prueba activa es
# TRUNCATE dentro de BEGIN … ROLLBACK, que debe ser rechazado por el disparador.
# =============================================================================
set -uo pipefail
valor() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
export PGHOST="${PGHOST:-$(valor DB_HOST)}" PGPORT="${PGPORT:-$(valor DB_PORT)}" PGUSER="${PGUSER:-$(valor DB_USERNAME)}" \
       PGPASSWORD="${PGPASSWORD:-$(valor DB_PASSWORD)}" PGDATABASE="${PGDATABASE:-$(valor DB_DATABASE)}"
FALLAS=0
ok() { if [ "$2" = "$3" ]; then echo "  ✔ $1"; else echo "  ✘ $1 (esperado: $3; obtenido: $2)"; FALLAS=$((FALLAS+1)); fi; }
q() { psql -Atc "$1"; }

echo "Base: $PGDATABASE"
ok "Tabla public.revisiones_documentales" "$(q "select to_regclass('public.revisiones_documentales') is not null")" t
ok "Vista public.revisiones_documentales_vigentes" "$(q "select to_regclass('public.revisiones_documentales_vigentes') is not null")" t
ok "Tabla vacía" "$(q 'select count(*) from public.revisiones_documentales')" 0
ok "Columnas (17)" "$(q "select count(*) from information_schema.columns where table_schema='public' and table_name='revisiones_documentales'")" 17
ok "Identidad GENERATED ALWAYS en id" "$(q "select is_identity||identity_generation from information_schema.columns where table_schema='public' and table_name='revisiones_documentales' and column_name='id'")" YESALWAYS
ok "Llave primaria" "$(q "select count(*) from pg_constraint where conrelid='public.revisiones_documentales'::regclass and contype='p'")" 1
ok "3 llaves foráneas, todas ON DELETE/UPDATE RESTRICT" "$(q "select count(*) filter (where confdeltype='r' and confupdtype='r')||'/'||count(*) from pg_constraint where conrelid='public.revisiones_documentales'::regclass and contype='f'")" 3/3
ok "FK a solicitudes, usuarios y a sí misma" "$(q "select string_agg(confrelid::regclass::text, ',' order by confrelid::regclass::text) from pg_constraint where conrelid='public.revisiones_documentales'::regclass and contype='f'")" "revisiones_documentales,solicitudes_certificacion,users"
ok "Restricciones CHECK (15)" "$(q "select count(*) from pg_constraint where conrelid='public.revisiones_documentales'::regclass and contype='c'")" 15
ok "Únicas: sucesor e idempotencia" "$(q "select string_agg(conname, ',' order by conname) from pg_constraint where conrelid='public.revisiones_documentales'::regclass and contype='u'")" "rd_idempotencia_uq,rd_sucesor_unico_uq"
ok "Resultados permitidos" "$(q "select pg_get_constraintdef(oid) like '%validado%con_observaciones%no_valido%' and pg_get_constraintdef(oid) not like '%no_aplica%' and pg_get_constraintdef(oid) not like '%por_revisar%' from pg_constraint where conname='rd_resultado_ck'")" t
ok "Límite de observaciones 1000" "$(q "select pg_get_constraintdef(oid) like '%<= 1000%' from pg_constraint where conname='rd_observaciones_largo_ck'")" t
ok "Motivo de cambio 10..1000" "$(q "select pg_get_constraintdef(oid) ~ '10.*1000' from pg_constraint where conname='rd_motivo_ck'")" t
ok "Identificación de versión (ruta/huella/tamaño según estado)" "$(q "select count(*) from pg_constraint where conname in ('rd_version_ck','rd_sha256_ck','rd_ruta_ck','rd_bytes_ck','rd_validado_requiere_archivo_ck')")" 5
ok "Índices (pk, 2 únicos, parcial, 2 de consulta)" "$(q "select string_agg(indexname, ',' order by indexname) from pg_indexes where schemaname='public' and tablename='revisiones_documentales'")" "rd_coordinador_idx,rd_documento_idx,rd_idempotencia_uq,rd_primera_revision_uq,rd_sucesor_unico_uq,revisiones_documentales_pkey"
ok "Índice parcial de primera revisión" "$(q "select indexdef like '%WHERE (revision_anterior_id IS NULL)%' from pg_indexes where indexname='rd_primera_revision_uq'")" t
ok "3 disparadores habilitados" "$(q "select string_agg(tgname||':'||tgenabled::text, ',' order by tgname) from pg_trigger where tgrelid='public.revisiones_documentales'::regclass and not tgisinternal")" "rd_sin_truncate_trg:O,rd_sin_update_delete_trg:O,rd_validar_insercion_trg:O"
ok "Disparador BEFORE UPDATE OR DELETE por fila" "$(q "select pg_get_triggerdef(oid) like '%BEFORE DELETE OR UPDATE%FOR EACH ROW%' from pg_trigger where tgname='rd_sin_update_delete_trg'")" t
ok "Funciones de disparador" "$(q "select count(*) from pg_proc where proname in ('rd_validar_insercion','rd_impedir_modificacion') and pronamespace='public'::regnamespace")" 2
ok "PUBLIC sin privilegios" "$(q "select count(*) from information_schema.role_table_grants where table_name='revisiones_documentales' and grantee='PUBLIC'")" 0
RESULTADO_TRUNCATE=$(psql -v ON_ERROR_STOP=1 -Atc "begin; truncate public.revisiones_documentales; rollback;" 2>&1 | grep -c "solo inserción" || true)
ok "TRUNCATE rechazado por el disparador (BEGIN … ROLLBACK)" "$RESULTADO_TRUNCATE" 1
ok "Migración registrada" "$(q "select count(*) from migrations where migration='2026_11_26_000000_create_revisiones_documentales_table'")" 1
ok "Migración ajena escuela_facultad SIN aplicar" "$(q "select count(*) from migrations where migration='2026_11_20_190000_add_escuela_facultad_to_instituciones_afiliadas'")" 0
echo "Fallas: $FALLAS"
exit $FALLAS
