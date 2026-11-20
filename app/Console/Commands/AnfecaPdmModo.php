<?php

namespace App\Console\Commands;

use App\Services\Pdm\Migracion\ConciliadorPdm;
use App\Services\Pdm\Migracion\PlanMigracionPdm;
use App\Services\Pdm\Migracion\ProteccionMigracion;
use App\Services\Pdm\ModoPdm;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Consulta o cambia el modo de integración con el PDM (segunda llave del modo efectivo).
 *
 *  anfeca:pdm-modo              → muestra modo configurado, activación registrada y modo efectivo.
 *  anfeca:pdm-modo espejo       → ACTIVACIÓN PROTEGIDA: exige ANFECA_PDM_MODO=espejo, plan sin
 *                                 bloqueos y conciliación con 0 diferencias, bloqueando las tablas
 *                                 de dominio de public durante la verificación (nadie escribe
 *                                 entre la conciliación y la activación). En la base en uso exige
 *                                 además ANFECA_PDM_AUTORIZAR_ESPEJO=si.
 *  anfeca:pdm-modo apagado      → REVERSIÓN INMEDIATA: siempre permitida.
 */
class AnfecaPdmModo extends Command
{
    protected $signature = 'anfeca:pdm-modo {modo? : apagado | espejo} {--confirmar : Omite la confirmación interactiva}';

    protected $description = 'Muestra o cambia el modo de integración con el modelo PowerDesigner';

    private const TABLAS_PUBLIC = [
        'users', 'datos_solicitantes', 'solicitudes_certificacion', 'datos_refrendo', 'selecciones_certificacion',
        'formaciones_academicas_solicitud', 'documentos_probatorios_solicitud', 'historial_documentos_academicos',
        'certificados_historicos', 'instituciones_afiliadas',
    ];

    public function handle(): int
    {
        $modo = $this->argument('modo');

        if ($modo === null) {
            return $this->mostrar();
        }

        try {
            return match ($modo) {
                'espejo' => $this->activarEspejo(),
                'apagado' => $this->apagar(),
                default => throw new RuntimeException("Modo '{$modo}' no admitido aquí. En esta fase solo: apagado | espejo."),
            };
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function mostrar(): int
    {
        $activacion = ModoPdm::activacion();

        $this->table(['Dato', 'Valor'], [
            ['Modo configurado (ANFECA_PDM_MODO)', ModoPdm::configurado()],
            ['Activación registrada', $activacion ? "{$activacion->ic_modo} en {$activacion->ic_base_datos} ({$activacion->ic_cambiado_en})" : 'ninguna'],
            ['Modo EFECTIVO', ModoPdm::efectivo()],
            ['Base actual', DB::connection()->getDatabaseName()],
        ]);

        return self::SUCCESS;
    }

    private function activarEspejo(): int
    {
        if (ModoPdm::configurado() !== ModoPdm::ESPEJO) {
            throw new RuntimeException('Primero defina ANFECA_PDM_MODO=espejo en .env y ejecute php artisan config:clear (primera llave).');
        }

        ProteccionMigracion::verificar('conciliar');

        if (!DB::selectOne("select to_regclass('anfeca_modelo.integracion_control') as t")->t) {
            throw new RuntimeException('Falta la tabla de control: php artisan migrate --path=database/migrations_pdm');
        }

        $bd = DB::connection()->getDatabaseName();
        if ($bd === config('anfeca_modelo.bd_permitida') && env('ANFECA_PDM_AUTORIZAR_ESPEJO') !== 'si') {
            throw new RuntimeException("Para activar el espejo en {$bd} defina ANFECA_PDM_AUTORIZAR_ESPEJO=si solo en este comando, con F2 aprobada y un respaldo verificado.");
        }

        if (!DB::table('anfeca_modelo.persona')->exists() && DB::table('public.datos_solicitantes')->exists()) {
            throw new RuntimeException('El PDM no tiene datos migrados: ejecute primero anfeca:pdm-migrar --ejecutar (F2).');
        }

        if (!$this->option('confirmar') && !$this->confirm("¿Activar el modo espejo en {$bd}? Toda escritura de ANFECA se registrará también en anfeca_modelo", false)) {
            $this->warn('Cancelado.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($bd) {
            // Nadie escribe en public entre la conciliación y la activación.
            DB::statement('lock table '.implode(', ', array_map(fn ($t) => "public.{$t}", self::TABLAS_PUBLIC)).' in share mode');

            $plan = PlanMigracionPdm::construir();
            if (($bloqueantes = count($plan->bloqueantes())) > 0) {
                throw new RuntimeException("No se activa: {$bloqueantes} incidencia(s) bloqueante(s) en el plan (anfeca:pdm-migrar).");
            }

            $diferencias = (new ConciliadorPdm())->conciliar($plan);
            if ($diferencias !== []) {
                throw new RuntimeException('No se activa: la conciliación tiene '.count($diferencias).' diferencia(s) (anfeca:pdm-conciliar).');
            }

            $this->registrar(ModoPdm::ESPEJO, $bd, 0, 'Activado tras conciliación sin diferencias.');
        });

        $this->info("Modo espejo ACTIVADO en {$bd}. Modo efectivo: ".ModoPdm::efectivo().'.');
        $this->line('Reversión inmediata: php artisan anfeca:pdm-modo apagado');

        return self::SUCCESS;
    }

    private function apagar(): int
    {
        if (!DB::selectOne("select to_regclass('anfeca_modelo.integracion_control') as t")->t) {
            $this->info('No hay tabla de control: el modo efectivo ya es apagado.');

            return self::SUCCESS;
        }

        $this->registrar(ModoPdm::APAGADO, DB::connection()->getDatabaseName(), 0, 'Apagado manual.');
        $this->info('Modo efectivo: apagado. ANFECA escribe solo en public. Cambie también ANFECA_PDM_MODO=apagado en .env y ejecute config:clear.');

        return self::SUCCESS;
    }

    private function registrar(string $modo, string $bd, int $diferencias, string $nota): void
    {
        DB::table('anfeca_modelo.integracion_control')->upsert([[
            'ic_id' => 1, 'ic_modo' => $modo, 'ic_base_datos' => $bd, 'ic_diferencias' => $diferencias,
            'ic_cambiado_en' => now(), 'ic_nota' => $nota,
        ]], ['ic_id'], ['ic_modo', 'ic_base_datos', 'ic_diferencias', 'ic_cambiado_en', 'ic_nota']);
    }
}
