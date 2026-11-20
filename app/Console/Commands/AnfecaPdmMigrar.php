<?php

namespace App\Console\Commands;

use App\Services\Pdm\Migracion\ConciliadorPdm;
use App\Services\Pdm\Migracion\Incidencia;
use App\Services\Pdm\Migracion\MigradorPdm;
use App\Services\Pdm\Migracion\PlanMigracionPdm;
use App\Services\Pdm\Migracion\ProteccionMigracion;
use App\Services\Pdm\Migracion\SimulacionRevertida;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * F2 · Migración de public a anfeca_modelo.
 *
 *  (sin opciones)  Plan: solo lectura. Conteos e incidencias; nada se escribe.
 *  --simular       Escribe, concilia y REVIERTE (solo base aislada *_pruebas_pg).
 *  --ejecutar      Escribe y concilia en una transacción; confirma solo si hay 0 diferencias
 *                  y public no cambió. Se niega mientras haya incidencias bloqueantes.
 */
class AnfecaPdmMigrar extends Command
{
    protected $signature = 'anfeca:pdm-migrar
        {--simular : Escribe y concilia dentro de una transacción que se revierte (base aislada)}
        {--ejecutar : Migra de forma definitiva}
        {--confirmar : Omite la confirmación interactiva (pruebas automatizadas)}
        {--informe= : Ruta del informe JSON (por omisión storage/app/anfeca_pdm/)}';

    protected $description = 'Migra los datos de public al modelo PowerDesigner (plan de solo lectura por omisión)';

    public function handle(): int
    {
        $operacion = $this->option('ejecutar') ? 'ejecutar' : ($this->option('simular') ? 'simular' : 'planear');

        try {
            ProteccionMigracion::verificar($operacion);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $plan = PlanMigracionPdm::construir();
        $bloqueantes = $plan->bloqueantes();
        $this->mostrarPlan($plan);

        if ($operacion === 'planear') {
            $this->guardarInforme($operacion, $plan, null, null);
            $this->line($bloqueantes === [] ? 'Sin bloqueos: puede simularse y ejecutarse.' : count($bloqueantes).' incidencia(s) bloqueante(s): resuelva las decisiones en config/anfeca_pdm_decisiones.php.');

            return self::SUCCESS;
        }

        if ($operacion === 'ejecutar' && $bloqueantes !== []) {
            $this->error('No se migra: hay '.count($bloqueantes).' incidencia(s) bloqueante(s) sin decisión registrada.');

            return self::FAILURE;
        }

        if ($operacion === 'ejecutar' && !$this->option('confirmar')
            && !$this->confirm('¿Migrar a anfeca_modelo en '.DB::connection()->getDatabaseName().'? Confirme que existe un respaldo pg_dump verificado', false)) {
            $this->warn('Cancelado. No se modificó nada.');

            return self::FAILURE;
        }

        $huellaAntes = ProteccionMigracion::huellaPublic();
        $diferencias = [];
        $escritas = [];

        try {
            DB::transaction(function () use ($plan, $operacion, $huellaAntes, &$diferencias, &$escritas) {
                $escritas = (new MigradorPdm())->escribir($plan);
                $diferencias = (new ConciliadorPdm())->conciliar($plan);

                if (ProteccionMigracion::huellaPublic() !== $huellaAntes) {
                    throw new RuntimeException('public cambió durante la migración: se revierte todo.');
                }
                if ($diferencias !== []) {
                    throw new RuntimeException(count($diferencias).' diferencia(s) en la conciliación: se revierte todo.');
                }
                if ($operacion === 'simular') {
                    throw new SimulacionRevertida();
                }
            });
        } catch (SimulacionRevertida) {
            $this->tablaEscritas($escritas);
            $this->info('Simulación correcta: conciliación sin diferencias y public sin cambios. Transacción revertida; nada quedó escrito.');
            $this->guardarInforme($operacion, $plan, $escritas, []);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->mostrarDiferencias($diferencias);
            $this->error('Revertido: '.$e->getMessage());
            $this->guardarInforme($operacion, $plan, $escritas, $diferencias, $e->getMessage());

            return self::FAILURE;
        }

        $this->tablaEscritas($escritas);
        $this->info('Migración confirmada: conciliación sin diferencias y public sin cambios.');
        $this->guardarInforme($operacion, $plan, $escritas, []);

        return self::SUCCESS;
    }

    private function mostrarPlan(PlanMigracionPdm $plan): void
    {
        $this->info('Plan de migración de public a anfeca_modelo ('.DB::connection()->getDatabaseName().')');
        $this->table(['Tabla PDM', 'Filas'], collect($plan->conteos())->map(fn ($n, $t) => [$t, $n])->values()->all());

        $grupos = collect($plan->incidencias)->groupBy(fn (Incidencia $i) => ($i->bloqueante ? 'BLOQUEA' : 'info').'|'.$i->codigo.'|'.($i->decision ?? ''));
        $this->table(['Tipo', 'Código', 'Decisión', 'Casos', 'IDs (tabla)'], $grupos->map(function ($items, $k) {
            [$tipo, $codigo, $decision] = explode('|', $k);

            return [$tipo, $codigo, $decision ?: '—', count($items),
                $items->map(fn ($i) => $i->id)->filter(fn ($id) => $id !== null)->take(15)->implode(', ').' ('.$items->first()->tabla.')'];
        })->sortBy(0)->values()->all());
    }

    private function tablaEscritas(array $escritas): void
    {
        $this->table(['Tabla PDM', 'Escritas'], collect($escritas)->map(fn ($n, $t) => [$t, $n])->values()->all());
    }

    private function mostrarDiferencias(array $diferencias): void
    {
        if ($diferencias !== []) {
            $this->table(['Tabla', 'Tipo', 'Clave', 'Detalle'], array_slice(array_map('array_values', $diferencias), 0, 50));
        }
    }

    private function guardarInforme(string $operacion, PlanMigracionPdm $plan, ?array $escritas, ?array $diferencias, ?string $error = null): void
    {
        $ruta = $this->option('informe') ?: storage_path('app/anfeca_pdm/informe_'.$operacion.'_'.now()->format('Ymd_His').'.json');
        @mkdir(dirname($ruta), 0700, true);

        file_put_contents($ruta, json_encode([
            'operacion' => $operacion,
            'base' => DB::connection()->getDatabaseName(),
            'fecha' => now()->toIso8601String(),
            'conteos_plan' => $plan->conteos(),
            'escritas' => $escritas,
            'incidencias' => array_map(fn (Incidencia $i) => $i->aArreglo(), $plan->incidencias),
            'diferencias' => $diferencias,
            'error' => $error,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        @chmod($ruta, 0600);

        $this->line("Informe: {$ruta}");
    }
}

