<?php

namespace App\Console\Commands;

use App\Services\AnfecaModeloInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Crea el esquema aislado del PDM (anfeca_modelo) en la BD de pruebas.
 *
 * - Por defecto NO modifica nada: muestra las comprobaciones (modo en seco).
 * - Con --ejecutar aplica el script en UNA transacción y verifica el resultado.
 * - Nunca toca public: si la lista de tablas de public cambia, revierte todo.
 */
class AnfecaModeloInstalar extends Command
{
    protected $signature = 'anfeca:modelo-instalar
        {--ejecutar : Aplica el script. Sin esta opción solo se muestran las comprobaciones}
        {--confirmar : Omite la pregunta de confirmación (usar solo en pruebas automatizadas)}';

    protected $description = 'Crea el esquema aislado anfeca_modelo con el PDM de PowerDesigner (en seco por defecto)';

    public function handle(): int
    {
        $esquema = (string) config('anfeca_modelo.esquema');
        $bdPermitida = (string) config('anfeca_modelo.bd_permitida');
        $rutaScript = (string) config('anfeca_modelo.script');
        $esperado = (array) config('anfeca_modelo.esperado');

        $inspector = new AnfecaModeloInspector();

        $this->info('ANFECA — Instalación del esquema aislado del PDM');
        $this->line('Modo: '.($this->option('ejecutar') ? 'EJECUTAR' : 'EN SECO (no se modifica nada)'));

        // 1. Comprobaciones que no requieren tocar la base de datos.
        if ($esquema === '' || strtolower($esquema) === 'public') {
            return $this->rechazar('El esquema configurado no puede ser vacío ni public.');
        }

        if (!is_file($rutaScript)) {
            return $this->rechazar("No se encontró el script: {$rutaScript}");
        }

        $sql = (string) file_get_contents($rutaScript);
        $problemas = AnfecaModeloInspector::revisarScript($sql, $esquema);

        if ($problemas !== []) {
            foreach ($problemas as $problema) {
                $this->error(' - '.$problema);
            }

            return $this->rechazar('El script no pasó la revisión estática.');
        }

        $conteo = AnfecaModeloInspector::contarEnScript($sql);

        if ($conteo['tablas'] !== (int) $esperado['tablas'] || $conteo['llaves_foraneas'] !== (int) $esperado['llaves_foraneas']) {
            return $this->rechazar(sprintf(
                'El script declara %d tablas y %d FK; se esperaban %d y %d.',
                $conteo['tablas'], $conteo['llaves_foraneas'], $esperado['tablas'], $esperado['llaves_foraneas']
            ));
        }

        $this->line("Script: {$rutaScript} ({$conteo['tablas']} tablas, {$conteo['llaves_foraneas']} FK) — revisión estática correcta.");

        // 2. Comprobaciones de conexión (solo lectura).
        if (!$inspector->esPostgres()) {
            return $this->rechazar('La conexión por defecto no es PostgreSQL (pgsql). No se instala nada.');
        }

        $bd = $inspector->baseDeDatos();

        if ($bd !== $bdPermitida) {
            return $this->rechazar("La base de datos conectada es '{$bd}' y solo se permite '{$bdPermitida}' (ANFECA_MODELO_BD_PERMITIDA).");
        }

        $this->line("Base de datos: {$bd} — permitida.");

        if ($inspector->esquemaExiste($esquema)) {
            $tablas = count($inspector->tablas($esquema));

            return $this->rechazar("El esquema {$esquema} ya existe ({$tablas} tablas). No se reinstala; use anfeca:modelo-verificar.");
        }

        $publicAntes = $inspector->tablas('public');
        $this->line('Tablas actuales en public: '.count($publicAntes).' (no se modificarán).');

        if (!$this->option('ejecutar')) {
            $this->info('Comprobaciones correctas. Para aplicar: php artisan anfeca:modelo-instalar --ejecutar');

            return self::SUCCESS;
        }

        if (!$this->option('confirmar') && !$this->confirm("¿Crear el esquema {$esquema} en {$bd}? Confirme que existe un respaldo pg_dump verificado", false)) {
            $this->warn('Cancelado. No se modificó nada.');

            return self::FAILURE;
        }

        // 3. Ejecución en una sola transacción, con verificación antes del COMMIT.
        $db = $inspector->conexion();

        try {
            $db->transaction(function () use ($db, $inspector, $sql, $esquema, $esperado, $publicAntes) {
                $db->unprepared($sql);

                $tablas = count($inspector->tablas($esquema));
                $llaves = $inspector->totalLlavesForaneas($esquema);

                if ($tablas !== (int) $esperado['tablas'] || $llaves !== (int) $esperado['llaves_foraneas']) {
                    throw new RuntimeException("Resultado inesperado: {$tablas} tablas y {$llaves} FK.");
                }

                if ($inspector->tablas('public') !== $publicAntes) {
                    throw new RuntimeException('La lista de tablas de public cambió; se revierte todo.');
                }
            });
        } catch (Throwable $e) {
            Log::error('anfeca:modelo-instalar revertido', ['error' => $e->getMessage()]);

            return $this->rechazar('Error durante la instalación; la transacción se revirtió y no quedó nada a medias: '.$e->getMessage());
        }

        Log::info('anfeca:modelo-instalar completado', ['bd' => $bd, 'esquema' => $esquema]);

        $this->info(sprintf(
            'Esquema %s creado: %d tablas, %d FK. public sin cambios.',
            $esquema,
            count($inspector->tablas($esquema)),
            $inspector->totalLlavesForaneas($esquema)
        ));
        $this->line('Siguiente paso: php artisan anfeca:modelo-verificar');

        return self::SUCCESS;
    }

    private function rechazar(string $mensaje): int
    {
        $this->error($mensaje);

        return self::FAILURE;
    }
}
