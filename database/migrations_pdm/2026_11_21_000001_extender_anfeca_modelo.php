<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extensiones ADITIVAS del PDM (decisión D-2).
 *
 * Está en database/migrations_pdm, NO en database/migrations: un "php artisan migrate"
 * normal no la aplica. Se ejecuta solo de forma explícita:
 *   php artisan migrate --path=database/migrations_pdm
 *
 * Protecciones:
 *  - Solo PostgreSQL y con el esquema anfeca_modelo original instalado (36 tablas).
 *  - En la base de pruebas real (config anfeca_modelo.bd_permitida) exige además
 *    ANFECA_PDM_AUTORIZAR_EXTENSIONES=si en el entorno.
 *  - down() solo procede si las tablas de dominio del PDM y las de extensión están vacías.
 *  - Nunca toca public.
 */
return new class extends Migration
{
    private const ESQUEMA = 'anfeca_modelo';

    /** Tablas que deben estar vacías para poder revertir. */
    private const TABLAS_DOMINIO = [
        'persona', 'usuario', 'domicilio', 'comunicacion', 'institucion', 'sede',
        'solicitud', 'estado_solicitud', 'persona_estudio_profesional',
        'solicitud_documento', 'estado_solicitud_documento', 'pago', 'historico_certificado',
        'zona', 'coordinador_zona', 'historico_certificado_importacion',
    ];

    public function up(): void
    {
        $this->verificarConexion();

        $tablas = (int) DB::selectOne(
            "select count(*) as total from pg_tables where schemaname = ? and tablename <> 'integracion_control'",
            [self::ESQUEMA]
        )->total;

        if ($tablas !== 36) {
            throw new RuntimeException("Se esperaban las 36 tablas originales en anfeca_modelo y hay {$tablas}. Instale primero con anfeca:modelo-instalar o revise si las extensiones ya se aplicaron.");
        }

        $bd = DB::connection()->getDatabaseName();

        if ($bd === config('anfeca_modelo.bd_permitida') && env('ANFECA_PDM_AUTORIZAR_EXTENSIONES') !== 'si') {
            throw new RuntimeException("La base {$bd} es la de pruebas en uso. Para aplicar las extensiones defina ANFECA_PDM_AUTORIZAR_EXTENSIONES=si solo en ese comando, después de un respaldo verificado.");
        }

        DB::unprepared((string) file_get_contents(database_path('sql/anfeca_modelo/anfeca_modelo_extensiones.sql')));
    }

    public function down(): void
    {
        $this->verificarConexion();

        foreach (self::TABLAS_DOMINIO as $tabla) {
            $existe = DB::selectOne('select to_regclass(?) as t', [self::ESQUEMA.'.'.$tabla])->t;

            if ($existe && DB::table(self::ESQUEMA.'.'.$tabla)->exists()) {
                throw new RuntimeException("No se revierten las extensiones: anfeca_modelo.{$tabla} tiene datos.");
            }
        }

        // estudio_profesional es catálogo sembrado; solo los títulos «Otro» (REV) son datos capturados.
        if (DB::table(self::ESQUEMA.'.estudio_profesional')->where('espr_estado', 'REV')->exists()) {
            throw new RuntimeException('No se revierten las extensiones: hay títulos «Otro» (REV) en anfeca_modelo.estudio_profesional.');
        }

        DB::unprepared(<<<'SQL'
            drop table if exists anfeca_modelo.coordinador_zona;
            alter table anfeca_modelo.institucion drop constraint if exists fk_instituc_ext_zona,
                drop column if exists inst_id_zona, drop column if exists inst_campus;
            drop table if exists anfeca_modelo.zona;
            alter table anfeca_modelo.historico_certificado drop constraint if exists fk_hice_ext_importacion,
                drop column if exists hice_id_importacion, drop column if exists hice_certificacion;
            alter table anfeca_modelo.solicitud drop constraint if exists fk_soli_ext_historico,
                drop constraint if exists ck_soli_ext_tipo_tramite, drop constraint if exists ck_soli_ext_certificacion,
                drop column if exists soli_tipo_tramite, drop column if exists soli_certificacion,
                drop column if exists soli_id_historico_importacion;
            drop table if exists anfeca_modelo.historico_certificado_importacion;
            alter table anfeca_modelo.persona_estudio_profesional drop constraint if exists fk_pees_ext_solicitud,
                drop constraint if exists ck_pees_ext_tipo,
                drop column if exists pees_id_solicitud, drop column if exists pees_tipo;
            alter table anfeca_modelo.solicitud_documento drop column if exists socu_url_archivo;
            alter table anfeca_modelo.domicilio drop column if exists domi_colonia,
                drop column if exists domi_municipio, drop column if exists domi_codigo_postal;
            alter table anfeca_modelo.persona drop column if exists pers_cedula_profesional,
                drop column if exists pers_datos_autorizados, drop column if exists pers_fecha_autorizacion;
            drop index if exists anfeca_modelo.ext_persona_curp_uk;
            drop index if exists anfeca_modelo.ext_usuario_persona_uk;
            drop index if exists anfeca_modelo.ext_usuario_nombre_uk;
            drop index if exists anfeca_modelo.ext_domicilio_persona_uk;
            drop index if exists anfeca_modelo.ext_comunicacion_persona_tipo_uk;
            drop index if exists anfeca_modelo.ext_institucion_afiliacion_uk;
            drop index if exists anfeca_modelo.ext_solicitud_folio_uk;
            drop index if exists anfeca_modelo.ext_estudio_nivel_nombre_uk;
            drop index if exists anfeca_modelo.ext_socu_solicitud_documento_idx;
            drop index if exists anfeca_modelo.ext_entidad_nombre_uk;
            drop index if exists anfeca_modelo.ext_tipo_comunicacion_uk;
            drop index if exists anfeca_modelo.ext_c_estado_solicitud_uk;
            drop index if exists anfeca_modelo.ext_documento_uk;
            drop index if exists anfeca_modelo.ext_estado_documento_uk;
            drop index if exists anfeca_modelo.ext_articulo_subindice_uk;
            drop index if exists anfeca_modelo.ext_nivel_profesional_uk;
            drop index if exists anfeca_modelo.ext_area_certificacion_uk;
            drop index if exists anfeca_modelo.ext_tipo_personal_uk;
            drop index if exists anfeca_modelo.ext_rol_nombre_uk;
            drop index if exists anfeca_modelo.ext_privilegio_nombre_uk;
            drop index if exists anfeca_modelo.ext_rol_privilegio_uk;
            SQL);
    }

    private function verificarConexion(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Las extensiones del PDM solo se aplican en PostgreSQL.');
        }

        if (!DB::selectOne('select exists(select 1 from pg_namespace where nspname = ?) as e', [self::ESQUEMA])->e) {
            throw new RuntimeException('El esquema anfeca_modelo no existe.');
        }
    }
};
