<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

#[Signature('app:importar-certificados-historicos {archivo} {--preview}')]
#[Description('Revisa la estructura del Excel histórico ANFECA sin importar registros')]
class ImportarCertificadosHistoricos extends Command
{
    public function handle(): int
    {
        $archivo = $this->argument('archivo');

        if (!is_file($archivo) || !is_readable($archivo)) {
            $this->error('El archivo no existe o no tiene permisos de lectura.');
            return self::FAILURE;
        }

        $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));

        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            $this->error('Solo se permiten archivos XLSX o XLS.');
            return self::FAILURE;
        }

        if (!$this->option('preview')) {
            $this->warn('Por seguridad, utiliza --preview. La importación aún no está habilitada.');
            return self::FAILURE;
        }

        try {
            $lector = IOFactory::createReaderForFile($archivo);
            $lector->setReadDataOnly(true);

            $libro = $lector->load($archivo);
            $hoja = $libro->getActiveSheet();

            $ultimaFila = $hoja->getHighestDataRow();
            $ultimaColumna = $hoja->getHighestDataColumn();

            $encabezados = $hoja->rangeToArray(
                "A1:{$ultimaColumna}1",
                null,
                true,
                false
            )[0];

            $this->info('Vista previa del Excel histórico ANFECA');
            $this->line('Hoja: ' . $hoja->getTitle());
            $this->line('Última fila con datos: ' . $ultimaFila);
            $this->line('Filas potenciales de datos: ' . max(0, $ultimaFila - 1));
            $this->line('Última columna con datos: ' . $ultimaColumna);

            $tabla = [];

            foreach ($encabezados as $indice => $encabezado) {
                $tabla[] = [
                    $indice + 1,
                    $encabezado === null || trim((string) $encabezado) === ''
                        ? '(SIN ENCABEZADO)'
                        : trim((string) $encabezado),
                ];
            }

            $this->table(['Número', 'Encabezado'], $tabla);

            $libro->disconnectWorksheets();
            unset($libro);

            $this->info('Vista previa finalizada. No se modificó la base de datos.');

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error('No fue posible leer el archivo Excel.');
            $this->warn('Verifica que el archivo sea válido y no esté dañado.');

            return self::FAILURE;
        }
    }
}
