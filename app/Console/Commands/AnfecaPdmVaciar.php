<?php

namespace App\Console\Commands;

use App\Services\Pdm\Migracion\ProteccionMigracion;
use App\Services\Pdm\Migracion\VaciadorPdm;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reversión de F2: elimina de anfeca_modelo los datos migrados (nunca public ni catálogos).
 */
class AnfecaPdmVaciar extends Command
{
    protected $signature = 'anfeca:pdm-vaciar {--confirmar : Omite la confirmación interactiva}';

    protected $description = 'Revierte la migración F2 vaciando los datos de dominio de anfeca_modelo';

    public function handle(): int
    {
        try {
            ProteccionMigracion::verificar('vaciar');
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (!$this->option('confirmar') && !$this->confirm('¿Eliminar los datos migrados de anfeca_modelo en '.DB::connection()->getDatabaseName().'? public no se modifica', false)) {
            $this->warn('Cancelado.');

            return self::FAILURE;
        }

        $huella = ProteccionMigracion::huellaPublic();

        try {
            $eliminadas = DB::transaction(function () use ($huella) {
                $eliminadas = (new VaciadorPdm())->vaciar();
                if (ProteccionMigracion::huellaPublic() !== $huella) {
                    throw new \RuntimeException('public cambió: se revierte.');
                }

                return $eliminadas;
            });
        } catch (Throwable $e) {
            $this->error('Revertido: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Tabla', 'Filas eliminadas'], collect($eliminadas)->map(fn ($n, $t) => [$t, $n])->values()->all());
        $this->info('anfeca_modelo quedó sin datos migrados; catálogos y public intactos.');

        return self::SUCCESS;
    }
}
