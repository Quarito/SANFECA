<?php

namespace App\Console\Commands;

use App\Services\AnfecaModeloInspector;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use PDOException;

/**
 * SOLO LECTURA. Inventario del esquema aislado del PDM y comparación del
 * mapeo PDM ↔ Laravel (config/anfeca_modelo.php) contra la BD real.
 */
class AnfecaModeloVerificar extends Command
{
    protected $signature = 'anfeca:modelo-verificar
        {--solo-divergencias : Muestra únicamente las filas con diferencias}';

    protected $description = 'Inventario del esquema anfeca_modelo y divergencias PDM ↔ Laravel (solo lectura)';

    public function handle(): int
    {
        try {
            return $this->procesar();
        } catch (QueryException|PDOException $e) {
            // Sin conexión o sin permisos: mensaje breve, sin traza ni credenciales.
            $this->error('No fue posible consultar la base de datos: '.strtok($e->getMessage(), "\n"));
            $this->line('Revise que PostgreSQL esté activo y que .env apunte a la base correcta. No se modificó nada.');

            return self::FAILURE;
        }
    }

    private function procesar(): int
    {
        $esquema = (string) config('anfeca_modelo.esquema');
        $esperado = (array) config('anfeca_modelo.esperado');
        $mapeo = (array) config('anfeca_modelo.mapeo');

        $inspector = new AnfecaModeloInspector();

        if (!$inspector->esPostgres()) {
            $this->error('La conexión por defecto no es PostgreSQL (pgsql).');

            return self::FAILURE;
        }

        $this->info('ANFECA — Verificación PDM ↔ Laravel (solo lectura) en '.$inspector->baseDeDatos());

        // 1. Inventario del esquema aislado.
        $instalado = $inspector->esquemaExiste($esquema);
        $resultado = self::SUCCESS;

        if (!$instalado) {
            $this->warn("El esquema {$esquema} no existe. Se compara solo el lado Laravel.");
        } else {
            $catalogo = json_decode(file_get_contents(database_path('sql/anfeca_modelo/ANFECA_PDM_CATALOGO_TABLAS.json')), true, flags: JSON_THROW_ON_ERROR);
            $tablasBase = array_map('strtolower', array_column($catalogo['tablas'], 'name'));
            $llavesBase = array_map('strtolower', array_column($catalogo['relaciones_foraneas'], 'restriccion'));
            $tablasInstaladas = $inspector->tablas($esquema);
            $tablas = count(array_intersect($tablasBase, $tablasInstaladas));
            $llaves = $inspector->totalLlavesForaneas($esquema, $llavesBase);
            $filas = array_filter($inspector->filasPorTabla($esquema));

            $this->line("Esquema base {$esquema}: {$tablas}/{$esperado['tablas']} tablas, {$llaves}/{$esperado['llaves_foraneas']} FK.");
            $extensiones = array_diff($tablasInstaladas, $tablasBase);
            if ($extensiones !== []) {
                $this->line('Tablas de extensión y control: '.implode(', ', $extensiones));
            }
            $this->line($filas === []
                ? 'Todas las tablas del PDM están vacías.'
                : 'Tablas con datos: '.collect($filas)->map(fn ($n, $t) => "{$t}={$n}")->implode(', '));

            if ($tablas !== (int) $esperado['tablas'] || $llaves !== (int) $esperado['llaves_foraneas']) {
                $this->error('El esquema no coincide con lo esperado.');
                $resultado = self::FAILURE;
            }
        }

        // 2. Mapeo tabla por tabla.
        $publicTablas = $inspector->tablas('public');
        $filasTabla = [];
        $divergencias = 0;

        foreach ($mapeo as $tablaPdm => $definicion) {
            $tablaLaravel = $definicion['laravel'];
            $existeLaravel = in_array($tablaLaravel, $publicTablas, true);
            $columnasLaravel = $existeLaravel ? $inspector->columnas('public', $tablaLaravel) : [];
            $columnasPdm = $instalado ? $inspector->columnas($esquema, $tablaPdm) : [];

            $estado = $existeLaravel ? 'OK' : 'FALTA TABLA LARAVEL';
            $notas = [];

            foreach ($definicion['campos'] as $campoPdm => $campoLaravel) {
                if ($instalado && !in_array($campoPdm, $columnasPdm, true)) {
                    $notas[] = "PDM sin {$campoPdm}";
                }

                if ($campoLaravel === null) {
                    $notas[] = "{$campoPdm}: sin equivalente";
                } elseif ($existeLaravel && !in_array($campoLaravel, $columnasLaravel, true)) {
                    $notas[] = "falta {$tablaLaravel}.{$campoLaravel}";
                }
            }

            if ($estado !== 'OK' || $notas !== []) {
                $divergencias++;
                $estado = $estado === 'OK' ? 'DIVERGE' : $estado;
            }

            if (!$this->option('solo-divergencias') || $estado !== 'OK') {
                $filasTabla[] = [
                    $tablaPdm,
                    $tablaLaravel,
                    $definicion['origen'],
                    $estado,
                    implode('; ', $notas),
                ];
            }
        }

        $this->table(['PDM', 'Laravel (public)', 'Origen', 'Estado', 'Notas'], $filasTabla);

        $sinContraparte = (array) config('anfeca_modelo.sin_contraparte');
        $this->line('Tablas PDM sin contraparte Laravel (etapa posterior): '.implode(', ', $sinContraparte));
        $this->line("Entradas del mapeo con divergencias: {$divergencias} de ".count($mapeo).'.');
        $this->comment('Este comando no modifica la base de datos.');

        return $resultado;
    }
}
