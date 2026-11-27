<?php

use App\Services\Pdm\BasePdm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RENOMBRES = [
        ['comunicacion', 'cumu_id_persona', 'comu_id_persona'],
        ['convocatoria', 'con_lugar_entrega', 'conv_lugar_entrega'],
        ['convocatoria', 'con_evento_entrega', 'conv_evento_entrega'],
        ['solicitud', 'solo_art13', 'soli_art13'],
        ['solicitud_documento', 'docu_id_documento', 'socu_id_documento'],
    ];

    public function up(): void
    {
        $db = DB::connection();
        if ($db->getDriverName() !== 'pgsql' ||
            ! $db->selectOne("select to_regnamespace('anfeca_modelo') is not null as instalado")->instalado) {
            throw new RuntimeException('Instale primero el esquema base del PDM en PostgreSQL.');
        }

        BasePdm::verificarEscritura('ANFECA_PDM_AUTORIZAR_EXTENSIONES');

        // Ejecutar antes de las extensiones pendientes y también sobre instalaciones que ya tienen F4/F5.
        // El bloqueo transaccional serializa reintentos antes de consultar los nombres.
        $db->statement("select pg_advisory_xact_lock(hashtext('anfeca_modelo.alinear_identificadores'))");

        $pendientes = [];
        foreach (self::RENOMBRES as [$tabla, $anterior, $actual]) {
            $columnas = $db->table('information_schema.columns')
                ->where('table_schema', 'anfeca_modelo')
                ->where('table_name', $tabla)
                ->whereIn('column_name', [$anterior, $actual])
                ->pluck('column_name')
                ->all();

            if (count($columnas) !== 1) {
                throw new RuntimeException("Estado ambiguo en anfeca_modelo.{$tabla}: se esperaba exactamente una columna entre {$anterior} y {$actual}.");
            }

            if ($columnas[0] === $anterior) {
                $pendientes[] = [$tabla, $anterior, $actual];
            }
        }

        if ($pendientes === []) {
            return;
        }

        // PostgreSQL mantiene datos, PK, FK, índices y dependencias de vistas al renombrar.
        foreach ($pendientes as [$tabla, $anterior, $actual]) {
            $db->statement("alter table anfeca_modelo.{$tabla} rename column {$anterior} to {$actual}");
        }

        if ($db->selectOne("select to_regnamespace('anfeca_compat') is not null as instalado")->instalado) {
            // Los cuerpos SQL y PL/pgSQL se guardan como texto y requieren actualización explícita.
            $db->unprepared((string) file_get_contents(database_path('sql/anfeca_modelo/anfeca_compat_funciones_pdm.sql')));
        }
    }

    public function down(): void
    {
        // Los nombres pertenecen al esquema base corregido. Retirar extensiones no cambia ese contrato.
    }
};
