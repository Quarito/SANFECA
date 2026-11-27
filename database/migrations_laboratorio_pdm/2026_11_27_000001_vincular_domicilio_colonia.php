<?php

use App\Services\Pdm\BasePdm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->verificarDestino();
        DB::statement('alter table anfeca_modelo.domicilio
            add column domi_id_colonia integer,
            add constraint fk_domicilio_colonia foreign key (domi_id_colonia)
                references anfeca_modelo.colonia (colo_id_colonia) on update restrict on delete restrict');
    }

    public function down(): void
    {
        $this->verificarDestino();
        DB::transaction(function (): void {
            DB::statement('lock table anfeca_modelo.domicilio in access exclusive mode');
            if (DB::table('anfeca_modelo.domicilio')->whereNotNull('domi_id_colonia')->exists()) {
                throw new RuntimeException('Hay domicilios vinculados. Exporte y conserve los datos antes de revertir.');
            }

            DB::statement('alter table anfeca_modelo.domicilio drop column domi_id_colonia');
        });
    }

    private function verificarDestino(): void
    {
        if (! app()->environment('local', 'testing') || DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('La migración de domicilio está autorizada únicamente en PostgreSQL de laboratorio.');
        }

        $destino = DB::selectOne('select current_database() as base, host(inet_server_addr()) as direccion');
        if ($destino->base !== DB::connection()->getDatabaseName() || ! BasePdm::descartable($destino->base, $destino->direccion)) {
            throw new RuntimeException('La base conectada no es un destino desechable autorizado.');
        }
    }
};
