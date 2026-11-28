<?php

namespace App\Services\Anfeca\Sepomex;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Lector del Catálogo Nacional de Códigos Postales de Correos de México (SEPOMEX), en sus formatos oficiales:
 *  - XLS (CPdescarga.xls): una hoja por entidad más la hoja «Nota» con la versión.
 *  - TXT (CPdescarga.txt): campos separados por «|», Windows-1252 o UTF-8, con línea de aviso y encabezado.
 * Entrega cada fila con las columnas oficiales y valida su estructura. No toca la base de datos.
 */
final class LectorSepomex
{
    public const COLUMNAS = ['d_codigo', 'd_asenta', 'd_tipo_asenta', 'D_mnpio', 'd_estado', 'c_estado', 'c_mnpio'];

    public ?string $version = null;

    /** @return \Generator<int, array{fila:string, d_codigo:string, d_asenta:string, d_tipo_asenta:string, D_mnpio:string, d_estado:string, c_estado:string, c_mnpio:string}> */
    public function filas(string $archivo): \Generator
    {
        if (!is_file($archivo) || !is_readable($archivo)) {
            throw new RuntimeException('El archivo no existe o no es legible.');
        }

        return match (strtolower(pathinfo($archivo, PATHINFO_EXTENSION))) {
            'xls' => $this->xls($archivo),
            'txt' => $this->txt($archivo),
            default => throw new RuntimeException('Formato no admitido: use el XLS o el TXT oficial de SEPOMEX.'),
        };
    }

    private function xls(string $archivo): \Generator
    {
        $lector = IOFactory::createReader('Xls');
        $lector->setReadDataOnly(true);
        foreach ($lector->listWorksheetNames($archivo) as $hoja) {
            $lector->setLoadSheetsOnly([$hoja]);
            $libro = $lector->load($archivo);
            $filas = $libro->getActiveSheet()->toArray(null, false, false, false);
            $libro->disconnectWorksheets();
            unset($libro);
            if ($hoja === 'Nota') {
                foreach ($filas as $f) {
                    foreach ($f as $celda) {
                        if (is_string($celda) && preg_match('/Versi[oó]n del ([0-9]{2}\/[0-9]{2}\/[0-9]{4})/u', $celda, $m)) {
                            $this->version = $m[1];
                        }
                    }
                }
                continue;
            }
            $encabezado = array_map(fn ($c) => trim((string) $c), $filas[0] ?? []);
            $this->validarEncabezado($encabezado, "hoja {$hoja}");
            foreach (array_slice($filas, 1, null, true) as $i => $valores) {
                if (array_filter($valores, fn ($v) => trim((string) $v) !== '') === []) {
                    continue;
                }
                yield $this->fila(array_combine($encabezado, $valores), "hoja {$hoja}, fila ".($i + 1));
            }
            unset($filas);
        }
    }

    private function txt(string $archivo): \Generator
    {
        $manejador = fopen($archivo, 'rb');
        $encabezado = null;
        $numero = 0;
        while (($linea = fgets($manejador)) !== false) {
            $numero++;
            if (!mb_check_encoding($linea, 'UTF-8')) {
                $linea = mb_convert_encoding($linea, 'UTF-8', 'Windows-1252');
            }
            $linea = rtrim(preg_replace('/^\x{FEFF}/u', '', $linea), "\r\n");
            if (trim($linea) === '') {
                continue;
            }
            if ($encabezado === null) {
                if (preg_match('/Versi[oó]n del ([0-9]{2}\/[0-9]{2}\/[0-9]{4})/u', $linea, $m)) {
                    $this->version = $m[1];
                }
                $columnas = array_map('trim', explode('|', $linea));
                if (in_array('d_codigo', $columnas, true)) {
                    $this->validarEncabezado($columnas, 'TXT');
                    $encabezado = $columnas;
                }
                continue;
            }
            $valores = explode('|', $linea);
            if (count($valores) !== count($encabezado)) {
                throw new RuntimeException("Número de campos inválido en la línea {$numero}.");
            }
            yield $this->fila(array_combine($encabezado, $valores), "línea {$numero}");
        }
        fclose($manejador);
        if ($encabezado === null) {
            throw new RuntimeException('No se encontró el encabezado oficial (d_codigo|d_asenta|…).');
        }
    }

    private function validarEncabezado(array $columnas, string $origen): void
    {
        if (count(array_unique($columnas)) !== count($columnas) || array_diff(self::COLUMNAS, $columnas)) {
            throw new RuntimeException("Encabezado inválido en {$origen}.");
        }
    }

    private function fila(array $r, string $origen): array
    {
        $f = ['fila' => $origen];
        foreach (self::COLUMNAS as $c) {
            $f[$c] = trim(preg_replace('/\s+/u', ' ', (string) ($r[$c] ?? '')));
        }
        if (!preg_match('/^[0-9]{5}$/D', $f['d_codigo']) || !preg_match('/^[0-9]{2}$/D', $f['c_estado'])
            || !preg_match('/^[0-9]{3}$/D', $f['c_mnpio'])) {
            throw new RuntimeException("Claves inválidas en {$origen} (código postal, entidad o municipio).");
        }
        foreach (['d_asenta' => 150, 'D_mnpio' => 150, 'd_estado' => 85] as $c => $max) {
            if ($f[$c] === '' || mb_strlen($f[$c]) > $max || preg_match('/[\x00-\x1F]/u', $f[$c])) {
                throw new RuntimeException("Valor inválido en {$c} ({$origen}).");
            }
        }

        return $f;
    }
}
