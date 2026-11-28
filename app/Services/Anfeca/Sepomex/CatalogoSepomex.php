<?php

namespace App\Services\Anfeca\Sepomex;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Catálogo SEPOMEX leído, validado y consolidado según lo que el modelo oficial puede representar.
 * Lo comparten el importador (anfeca:sepomex) y el generador de scripts SQL (anfeca:sepomex-sql), de modo que ambos
 * cargan exactamente los mismos registros.
 *
 *  - estados:     c_estado (int) => d_estado
 *  - municipios:  "c_estado|c_mnpio" => D_mnpio
 *  - colonias:    "c_estado|c_mnpio|d_codigo|d_asenta" => [clave de municipio, d_codigo, d_asenta]
 *                 (COLONIA no tiene tipo de asentamiento: los registros que solo difieren en él se consolidan)
 *  - tipos:       misma clave => tipos de asentamiento de origen (más de uno = registro consolidado)
 */
final class CatalogoSepomex
{
    public array $estados = [];
    public array $municipios = [];
    public array $colonias = [];
    public array $tipos = [];
    public int $filas = 0;
    public int $repetidos = 0;
    public int $grafias = 0;
    public ?string $version = null;
    public string $sha256;
    public string $archivo;

    /** Clave de comparación de nombres: sin acentos, minúsculas y espacios simples (igual que en los scripts SQL). */
    public static function clave(string $s): string
    {
        return Str::of($s)->ascii()->lower()->replaceMatches('/\s+/', ' ')->trim()->value();
    }

    public static function leer(string $archivo): self
    {
        if (!is_file($archivo) || !is_readable($archivo)) {
            throw new RuntimeException('El archivo no existe o no es legible.');
        }
        $c = new self();
        $c->archivo = $archivo;
        $c->sha256 = hash_file('sha256', $archivo);
        $lector = new LectorSepomex();
        $nombresMunicipio = $variantes = [];
        foreach ($lector->filas($archivo) as $f) {
            $c->filas++;
            $e = (int) $f['c_estado'];
            if (isset($c->estados[$e]) && $c->estados[$e] !== $f['d_estado']) {
                throw new RuntimeException("La clave de entidad {$f['c_estado']} tiene dos nombres ({$f['fila']}).");
            }
            $c->estados[$e] = $f['d_estado'];
            $m = $e.'|'.$f['c_mnpio'];
            if (isset($c->municipios[$m]) && $c->municipios[$m] !== $f['D_mnpio']) {
                throw new RuntimeException("La clave de municipio {$m} tiene dos nombres ({$f['fila']}).");
            }
            $c->municipios[$m] = $f['D_mnpio'];
            $nm = $e.'|'.self::clave($f['D_mnpio']);
            if (isset($nombresMunicipio[$nm]) && $nombresMunicipio[$nm] !== $m) {
                throw new RuntimeException("Dos municipios de la entidad {$e} solo se distinguen por su clave: {$f['D_mnpio']} ({$f['fila']}).");
            }
            $nombresMunicipio[$nm] = $m;
            $k = $m.'|'.$f['d_codigo'].'|'.$f['d_asenta'];
            $c->tipos[$k][] = $f['d_tipo_asenta'];
            if (isset($c->colonias[$k])) {
                $c->repetidos++;            // mismo nombre, CP y municipio; solo difiere el tipo de asentamiento
                continue;
            }
            $c->colonias[$k] = [$m, $f['d_codigo'], $f['d_asenta']];
            $variantes[$m.'|'.$f['d_codigo'].'|'.self::clave($f['d_asenta'])][$f['d_asenta']] = true;
        }
        $c->version = $lector->version;
        if (count($c->estados) !== 32) {
            throw new RuntimeException('El archivo no contiene las 32 entidades: se requiere el catálogo nacional completo.');
        }
        $nombresEntidad = array_map(self::clave(...), $c->estados);
        if (count(array_unique($nombresEntidad)) !== 32) {
            throw new RuntimeException('Dos entidades del catálogo comparten nombre.');
        }
        ksort($c->estados);
        $c->grafias = count(array_filter($variantes, fn ($v) => count($v) > 1));

        return $c;
    }

    /** @return array<string, list<string>> registros consolidados por tipo de asentamiento */
    public function consolidados(): array
    {
        return array_filter($this->tipos, fn ($t) => count($t) > 1);
    }

    /** CSV: entidad, municipio, código postal, colonia, tipos de asentamiento de origen y registros consolidados. */
    public function escribirConsolidados(string $ruta): void
    {
        $f = fopen($ruta, 'wb');
        fputcsv($f, ['entidad_clave', 'entidad', 'municipio_clave', 'municipio', 'codigo_postal', 'colonia', 'tipos_de_asentamiento', 'registros_consolidados'], escape: '');
        foreach ($this->consolidados() as $k => $t) {
            [$e, $cm, $cp, $nombre] = explode('|', $k, 4);
            fputcsv($f, [sprintf('%02d', $e), $this->estados[(int) $e], $cm, $this->municipios[$e.'|'.$cm], $cp, $nombre, implode(' / ', $t), count($t) - 1], escape: '');
        }
        fclose($f);
    }

    /** @return array<int, array{nombre:string, municipios:int, colonias:int, codigos_postales:int, consolidados:int}> */
    public function conteosPorEntidad(): array
    {
        $r = [];
        foreach ($this->estados as $e => $nombre) {
            $r[$e] = ['nombre' => $nombre, 'municipios' => 0, 'colonias' => 0, 'codigos_postales' => [], 'consolidados' => 0];
        }
        foreach ($this->municipios as $m => $_) {
            $r[(int) explode('|', $m)[0]]['municipios']++;
        }
        foreach ($this->colonias as $k => [$m, $cp]) {
            $e = (int) explode('|', $m)[0];
            $r[$e]['colonias']++;
            $r[$e]['codigos_postales'][$cp] = true;
            $r[$e]['consolidados'] += count($this->tipos[$k]) - 1;
        }
        foreach ($r as &$x) {
            $x['codigos_postales'] = count($x['codigos_postales']);
        }

        return $r;
    }
}
