<?php

namespace App\Console\Commands;

use App\Services\Pdm\Compat\EquivalenciaPdm;
use App\Services\Pdm\Migracion\ConciliadorPdm;
use App\Services\Pdm\Migracion\PlanMigracionPdm;
use App\Services\Pdm\Migracion\ProteccionMigracion;
use App\Services\Pdm\BasePdm;
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
 *  anfeca:pdm-modo principal    → F4: Laravel lee y escribe en el PDM (vistas anfeca_compat) y los
 *                                 disparadores replican en public. Exige ANFECA_PDM_MODO=principal, la
 *                                 capa instalada, plan sin bloqueos, conciliación Y equivalencia en 0
 *                                 (bajo LOCK SHARE). En la base en uso: ANFECA_PDM_AUTORIZAR_PRINCIPAL=si.
 *  anfeca:pdm-modo exclusivo    → F5: solo PDM; public queda congelado (huella registrada). Exige venir
 *                                 de principal y las mismas verificaciones. ANFECA_PDM_AUTORIZAR_EXCLUSIVO=si.
 *  anfeca:pdm-modo apagado      → REVERSIÓN INMEDIATA desde espejo o principal. Desde exclusivo exige
 *                                 antes anfeca:pdm-exportar-public (public al día: conciliación y
 *                                 equivalencia en 0); nunca se vuelve a un public desactualizado.
 */
class AnfecaPdmModo extends Command
{
    protected $signature = 'anfeca:pdm-modo {modo? : apagado | espejo | principal | exclusivo} {--confirmar : Omite la confirmación interactiva}';

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
                'principal', 'exclusivo' => $this->activarSobreVistas($modo),
                'apagado' => $this->apagar(),
                default => throw new RuntimeException("Modo '{$modo}' no válido: apagado | espejo | principal | exclusivo."),
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
        $this->salidaDeExclusivo();

        $bd = DB::connection()->getDatabaseName();
        BasePdm::verificarEscritura('ANFECA_PDM_AUTORIZAR_ESPEJO');

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

        BasePdm::verificarEscritura('ANFECA_PDM_AUTORIZAR_APAGADO');

        DB::transaction(function () {
            $this->salidaDeExclusivo();
            $this->registrar(ModoPdm::APAGADO, DB::connection()->getDatabaseName(), 0, 'Apagado manual.');
        });
        $this->info('Modo efectivo: apagado. ANFECA escribe solo en public. Cambie también ANFECA_PDM_MODO=apagado en .env y ejecute config:clear.');

        return self::SUCCESS;
    }

    /**
     * F4/F5. principal: desde apagado, espejo o exclusivo (este último solo con public exportado).
     * exclusivo: solo desde principal.
     */
    private function activarSobreVistas(string $modo): int
    {
        if (ModoPdm::configurado() !== $modo) {
            throw new RuntimeException("Primero defina ANFECA_PDM_MODO={$modo} en .env y ejecute php artisan config:clear (primera llave).");
        }

        ProteccionMigracion::verificar('conciliar');

        if (!ModoPdm::capaCompatibilidad()) {
            throw new RuntimeException('Falta la capa anfeca_compat: php artisan migrate --path=database/migrations_pdm');
        }

        $actual = ModoPdm::activacion()?->ic_modo ?? ModoPdm::APAGADO;
        if ($modo === ModoPdm::EXCLUSIVO && $actual !== ModoPdm::PRINCIPAL) {
            throw new RuntimeException("El modo exclusivo solo se activa desde principal (registrado: {$actual}).");
        }

        $bd = DB::connection()->getDatabaseName();
        $variable = 'ANFECA_PDM_AUTORIZAR_'.strtoupper($modo);
        BasePdm::verificarEscritura($variable);

        if (!DB::table('anfeca_modelo.persona')->exists() && DB::table('public.datos_solicitantes')->exists()) {
            throw new RuntimeException('El PDM no tiene datos migrados: ejecute primero anfeca:pdm-migrar --ejecutar (F2).');
        }

        $aviso = $modo === ModoPdm::PRINCIPAL
            ? "ANFECA leerá y escribirá en anfeca_modelo; public se mantendrá como réplica"
            : "ANFECA operará SOLO sobre anfeca_modelo; public quedará congelado";
        if (!$this->option('confirmar') && !$this->confirm("¿Activar el modo {$modo} en {$bd}? {$aviso}", false)) {
            $this->warn('Cancelado.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($modo, $bd) {
            $this->verificarCoincidencia('No se activa');

            $huella = $modo === ModoPdm::EXCLUSIVO ? json_encode(self::huellaDominio()) : null;
            $this->registrar($modo, $bd, 0, "Activado tras conciliación y equivalencia sin diferencias.", $huella);
        });

        $this->info("Modo {$modo} ACTIVADO en {$bd}. Modo efectivo: ".ModoPdm::efectivo().'.');
        $this->line($modo === ModoPdm::PRINCIPAL
            ? 'Reversión inmediata: php artisan anfeca:pdm-modo apagado (public se mantuvo al día).'
            : 'Reversión: php artisan anfeca:pdm-exportar-public --confirmar y después anfeca:pdm-modo principal o apagado.');

        return self::SUCCESS;
    }

    /**
     * Bloquea public (nadie escribe durante la verificación) y exige plan sin bloqueos,
     * conciliación PDM↔public y equivalencia vistas↔public en 0. Debe llamarse dentro de una transacción.
     */
    private function verificarCoincidencia(string $prefijo): void
    {
        DB::statement('lock table '.implode(', ', array_map(fn ($t) => "public.{$t}", self::TABLAS_PUBLIC)).' in share mode');

        $plan = PlanMigracionPdm::construir();
        if (($bloqueantes = count($plan->bloqueantes())) > 0) {
            throw new RuntimeException("{$prefijo}: {$bloqueantes} incidencia(s) bloqueante(s) en el plan (anfeca:pdm-migrar).");
        }

        $diferencias = (new ConciliadorPdm())->conciliar($plan);
        if ($diferencias !== []) {
            throw new RuntimeException("{$prefijo}: la conciliación tiene ".count($diferencias).' diferencia(s) (anfeca:pdm-conciliar).');
        }

        if (ModoPdm::capaCompatibilidad()) {
            $equivalencia = (new EquivalenciaPdm())->verificar();
            if ($equivalencia !== []) {
                throw new RuntimeException("{$prefijo}: la equivalencia vistas↔public tiene ".count($equivalencia).' diferencia(s) (anfeca:pdm-equivalencia).');
            }
        }
    }

    /** Desde exclusivo solo se sale con public exportado y verificado. */
    private function salidaDeExclusivo(): void
    {
        if ((ModoPdm::activacion()?->ic_modo) !== ModoPdm::EXCLUSIVO) {
            return;
        }

        DB::transaction(fn () => $this->verificarCoincidencia(
            'Public está desactualizado respecto al PDM (modo exclusivo). Ejecute antes anfeca:pdm-exportar-public --confirmar'
        ));
    }

    /** Huella de las tablas de dominio de public (sin users, que sigue siendo técnica). */
    public static function huellaDominio(): array
    {
        return array_diff_key(ProteccionMigracion::huellaPublic(), ['users' => true]);
    }

    private function registrar(string $modo, string $bd, int $diferencias, string $nota, ?string $huella = null): void
    {
        $fila = [
            'ic_id' => 1, 'ic_modo' => $modo, 'ic_base_datos' => $bd, 'ic_diferencias' => $diferencias,
            'ic_cambiado_en' => now(), 'ic_nota' => $nota,
        ];
        $columnas = ['ic_modo', 'ic_base_datos', 'ic_diferencias', 'ic_cambiado_en', 'ic_nota'];
        if (DB::selectOne("select 1 as x from information_schema.columns where table_schema = 'anfeca_modelo'
                and table_name = 'integracion_control' and column_name = 'ic_huella_public'")) {
            $fila['ic_huella_public'] = $huella;
            $columnas[] = 'ic_huella_public';
        }

        DB::table('anfeca_modelo.integracion_control')->upsert([$fila], ['ic_id'], $columnas);
    }
}
