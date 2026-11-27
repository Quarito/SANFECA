<?php

use App\Services\Pdm\BasePdm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F4 · Extensiones ADITIVAS del PDM para operar sin depender de public
 * (database/sql/anfeca_modelo/anfeca_modelo_extensiones_f4.sql) y ampliación de la
 * tabla técnica de control a los modos principal y exclusivo.
 *
 * Igual que F1: ruta separada (un migrate normal no la aplica), solo PostgreSQL,
 * autorización explícita en la base en uso, down() solo con las tablas nuevas vacías.
 */
return new class extends Migration
{
    private const TABLAS = ['seleccion_certificacion', 'solicitud_refrendo', 'formacion_solicitud', 'documentos_solicitud'];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql'
            || !DB::selectOne("select to_regclass('anfeca_modelo.integracion_control') as t")->t) {
            throw new RuntimeException('Requiere PostgreSQL con las extensiones F1 y la tabla de control (F3).');
        }
        if (DB::selectOne("select to_regclass('anfeca_modelo.seleccion_certificacion') as t")->t) {
            throw new RuntimeException('Las extensiones F4 ya están aplicadas.');
        }

        BasePdm::verificarEscritura('ANFECA_PDM_AUTORIZAR_EXTENSIONES');

        DB::unprepared((string) file_get_contents(database_path('sql/anfeca_modelo/anfeca_modelo_extensiones_f4.sql')));
        DB::unprepared(<<<'SQL'
            alter table anfeca_modelo.integracion_control drop constraint ck_integracion_control_modo;
            alter table anfeca_modelo.integracion_control
                add constraint ck_integracion_control_modo check (ic_modo in ('apagado','espejo','principal','exclusivo')),
                add column ic_huella_public text null;
            SQL);
    }

    public function down(): void
    {
        BasePdm::verificarEscritura('ANFECA_PDM_AUTORIZAR_EXTENSIONES');

        foreach (self::TABLAS as $t) {
            if (DB::table("anfeca_modelo.{$t}")->exists()) {
                throw new RuntimeException("No se revierte F4: anfeca_modelo.{$t} tiene datos.");
            }
        }
        $modo = DB::table('anfeca_modelo.integracion_control')->value('ic_modo');
        if (in_array($modo, ['principal', 'exclusivo'], true)) {
            throw new RuntimeException("El modo registrado es '{$modo}': vuelva primero a espejo o apagado.");
        }

        DB::unprepared(<<<'SQL'
            drop table anfeca_modelo.documentos_solicitud;
            drop table anfeca_modelo.formacion_solicitud;
            drop table anfeca_modelo.solicitud_refrendo;
            drop table anfeca_modelo.seleccion_certificacion;
            alter table anfeca_modelo.persona drop column pers_creado_en, drop column pers_actualizado_en;
            alter table anfeca_modelo.solicitud drop column soli_creado_en, drop column soli_actualizado_en;
            alter table anfeca_modelo.integracion_control drop column ic_huella_public, drop constraint ck_integracion_control_modo;
            alter table anfeca_modelo.integracion_control add constraint ck_integracion_control_modo check (ic_modo in ('apagado','espejo'));
            SQL);
    }
};
