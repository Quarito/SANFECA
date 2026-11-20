<?php

namespace App\Console\Commands;

use App\Services\Pdm\Migracion\ConciliadorPdm;
use App\Services\Pdm\Migracion\PlanMigracionPdm;
use App\Services\Pdm\Migracion\ProteccionMigracion;
use Illuminate\Console\Command;
use Throwable;

/**
 * SOLO LECTURA. Compara anfeca_modelo con lo que public y las decisiones registradas
 * indican. Sale con código 1 si existe cualquier diferencia.
 */
class AnfecaPdmConciliar extends Command
{
    protected $signature = 'anfeca:pdm-conciliar';

    protected $description = 'Concilia anfeca_modelo contra public (solo lectura)';

    public function handle(): int
    {
        try {
            ProteccionMigracion::verificar('conciliar');
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $diferencias = (new ConciliadorPdm())->conciliar(PlanMigracionPdm::construir());

        if ($diferencias === []) {
            $this->info('Conciliación correcta: anfeca_modelo coincide con public y las decisiones registradas (0 diferencias).');

            return self::SUCCESS;
        }

        $this->table(['Tabla', 'Tipo', 'Clave', 'Detalle'], array_slice(array_map('array_values', $diferencias), 0, 100));
        $this->error(count($diferencias).' diferencia(s). No se modificó nada.');

        return self::FAILURE;
    }
}
