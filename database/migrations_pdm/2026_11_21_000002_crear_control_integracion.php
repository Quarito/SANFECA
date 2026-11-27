<?php

use App\Services\Pdm\BasePdm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F3 · Tabla TÉCNICA de control del modo de integración (no forma parte del modelo
 * PowerDesigner y no debe agregarse al .pdm).
 *
 * El modo efectivo exige DOS llaves: ANFECA_PDM_MODO en .env Y una fila de activación
 * aquí, escrita solo por "anfeca:pdm-modo espejo" tras una conciliación con 0 diferencias.
 * Misma protección que la migración de extensiones para la base en uso.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || !DB::selectOne("select to_regclass('anfeca_modelo.zona') as t")->t) {
            throw new RuntimeException('Requiere PostgreSQL con anfeca_modelo y las extensiones de F1.');
        }

        BasePdm::verificarEscritura('ANFECA_PDM_AUTORIZAR_EXTENSIONES');

        DB::unprepared(<<<'SQL'
            create table anfeca_modelo.integracion_control (
                ic_id            smallint     not null default 1,
                ic_modo          varchar(12)  not null,
                ic_base_datos    varchar(128) not null,
                ic_diferencias   integer      not null,
                ic_cambiado_en   timestamp(0) not null,
                ic_nota          varchar(300) null,
                constraint pk_integracion_control primary key (ic_id),
                constraint ck_integracion_control_unica check (ic_id = 1),
                constraint ck_integracion_control_modo check (ic_modo in ('apagado','espejo'))
            );
            comment on table anfeca_modelo.integracion_control is
                'TÉCNICA ANFECA (no PDM): activación protegida del modo de integración. Una sola fila.';
            SQL);
    }

    public function down(): void
    {
        BasePdm::verificarEscritura('ANFECA_PDM_AUTORIZAR_EXTENSIONES');

        $modo = DB::table('anfeca_modelo.integracion_control')->value('ic_modo');
        if ($modo !== null && $modo !== 'apagado') {
            throw new RuntimeException("El modo registrado es '{$modo}': ejecute primero anfeca:pdm-modo apagado.");
        }

        DB::statement('drop table if exists anfeca_modelo.integracion_control');
    }
};
