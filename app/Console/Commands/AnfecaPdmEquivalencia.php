<?php

namespace App\Console\Commands;

use App\Services\Pdm\Compat\EquivalenciaPdm;
use App\Services\Pdm\ModoPdm;
use Illuminate\Console\Command;

/**
 * SOLO LECTURA. Compara lo que ANFECA leería en modo principal/exclusivo (vistas anfeca_compat)
 * con lo que lee hoy de public. Sale con código 1 si hay cualquier diferencia.
 */
class AnfecaPdmEquivalencia extends Command
{
    protected $signature = 'anfeca:pdm-equivalencia';

    protected $description = 'Verifica que las vistas anfeca_compat equivalen a las tablas de public (solo lectura)';

    public function handle(): int
    {
        if (!ModoPdm::capaCompatibilidad()) {
            $this->error('Falta la capa anfeca_compat: php artisan migrate --path=database/migrations_pdm');

            return self::FAILURE;
        }

        $verificador = new EquivalenciaPdm();
        $diferencias = $verificador->verificar();

        if ($verificador->historialAdicional > 0) {
            $this->line("Informativo: {$verificador->historialAdicional} versión(es) anterior(es) de documentos que solo conserva el PDM.");
        }

        if ($diferencias === []) {
            $this->info('Equivalencia correcta: las vistas anfeca_compat devuelven lo mismo que public (0 diferencias).');

            return self::SUCCESS;
        }

        $this->table(['Tabla', 'Tipo', 'Clave', 'Detalle'], array_slice(array_map(fn ($d) => [$d['tabla'], $d['tipo'], $d['clave'], $d['detalle']], $diferencias), 0, 100));
        $this->error(count($diferencias).' diferencia(s). No se modificó nada.');

        return self::FAILURE;
    }
}
