<?php

namespace App\Console\Commands;

use App\Services\Anfeca\Sepomex\LectorSepomex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Catálogo SEPOMEX → ENTIDAD_FEDERATIVA, ALCALDIA_MUNICIPIO y COLONIA (modelo oficial de 36 tablas).
 *
 *  ENTIDAD_FEDERATIVA  ← c_estado (clave = ENFE_ID_ENTIDAD_FEDERATIVA) y d_estado (ENFE_NOMBRE)
 *  ALCALDIA_MUNICIPIO  ← D_mnpio dentro de su entidad (ALMU_ID_ENTIDAD_FEDERATIVA)
 *  COLONIA             ← d_asenta y d_codigo dentro de su alcaldía o municipio (COLO_ID_ALCALDIA)
 *
 * Fuente de verdad: el archivo oficial y la estructura real de PostgreSQL (no la configuración de la aplicación).
 * Por omisión SOLO DIAGNOSTICA en una transacción de solo lectura. Con --aplicar inserta únicamente lo faltante,
 * en una sola transacción: nunca actualiza ni elimina filas, ni toca DOMICILIO u otras tablas.
 * COLONIA no tiene tipo de asentamiento: los registros que solo difieren en ese dato se guardan una vez.
 */
class ImportarSepomex extends Command
{
    protected $signature = 'anfeca:sepomex
        {archivo : CPdescarga.xls o CPdescarga.txt oficial de Correos de México}
        {--aplicar : Inserta únicamente los registros faltantes}
        {--base= : Nombre exacto de la base autorizada (obligatorio con --aplicar)}
        {--sha256= : Huella SHA-256 esperada del archivo (obligatoria con --aplicar)}
        {--json : Resumen en JSON}
        {--reporte= : Ruta (fuera de public/ y de Git) del CSV con los registros consolidados por tipo de asentamiento}';

    protected $description = 'Diagnostica (solo lectura) o importa el catálogo SEPOMEX a entidad_federativa, alcaldia_municipio y colonia.';

    private static function clave(string $s): string
    {
        return Str::of($s)->ascii()->lower()->replaceMatches('/\s+/', ' ')->trim()->value();
    }

    public function handle(): int
    {
        try {
            return $this->ejecutar();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /** CSV: entidad, municipio, código postal, colonia, tipos de asentamiento de origen y registros consolidados. */
    private function reporte(string $ruta, array $consolidados, array $estados, array $municipios): void
    {
        if (str_starts_with(realpath(dirname($ruta)) ?: '', public_path()) || str_starts_with(realpath(dirname($ruta)) ?: '', base_path().'/.git')) {
            throw new RuntimeException('El reporte no puede escribirse en public/ ni en .git.');
        }
        $f = fopen($ruta, 'wb');
        fputcsv($f, ['entidad_clave', 'entidad', 'municipio', 'codigo_postal', 'colonia', 'tipos_de_asentamiento', 'registros_consolidados']);
        foreach ($consolidados as $k => $t) {
            [$e, $cm, $cp, $nombre] = explode('|', $k, 4);
            fputcsv($f, [$e, $estados[(int) $e], $municipios[$e.'|'.$cm], $cp, $nombre, implode(' / ', $t), count($t) - 1]);
        }
        fclose($f);
        $this->info("Reporte de consolidación: {$ruta}");
    }

    private function ejecutar(): int
    {
        if ((int) ini_get('memory_limit') > 0 && (int) ini_get('memory_limit') < 1024) {
            ini_set('memory_limit', '1024M');
        }
        $inicio = microtime(true);
        $archivo = (string) $this->argument('archivo');
        $aplicar = (bool) $this->option('aplicar');
        $huella = is_file($archivo) ? hash_file('sha256', $archivo) : null;
        if ($huella === null) {
            throw new RuntimeException('El archivo no existe o no es legible.');
        }
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Solo PostgreSQL.');
        }
        $base = DB::selectOne('select current_database() as b')->b;
        if ($aplicar) {
            if (!$this->option('sha256') || !hash_equals($huella, strtolower((string) $this->option('sha256')))) {
                throw new RuntimeException('Con --aplicar, --sha256 debe coincidir con la huella del archivo verificado.');
            }
            if ($this->option('base') !== $base) {
                throw new RuntimeException("Base no autorizada: la conexión apunta a «{$base}».");
            }
        }

        // 1) Lectura y validación del archivo (sin base de datos).
        $lector = new LectorSepomex();
        $estados = $municipios = $nombresMunicipio = $colonias = $variantes = $tipos = [];
        $filas = $repetidos = 0;
        foreach ($lector->filas($archivo) as $f) {
            $filas++;
            $e = (int) $f['c_estado'];
            if (isset($estados[$e]) && $estados[$e] !== $f['d_estado']) {
                throw new RuntimeException("La clave de entidad {$f['c_estado']} tiene dos nombres ({$f['fila']}).");
            }
            $estados[$e] = $f['d_estado'];
            $m = $e.'|'.$f['c_mnpio'];
            if (isset($municipios[$m]) && $municipios[$m] !== $f['D_mnpio']) {
                throw new RuntimeException("La clave de municipio {$m} tiene dos nombres ({$f['fila']}).");
            }
            $municipios[$m] = $f['D_mnpio'];
            $nm = $e.'|'.self::clave($f['D_mnpio']);
            if (isset($nombresMunicipio[$nm]) && $nombresMunicipio[$nm] !== $m) {
                throw new RuntimeException("Dos municipios de la entidad {$e} solo se distinguen por su clave: {$f['D_mnpio']} ({$f['fila']}).");
            }
            $nombresMunicipio[$nm] = $m;
            $k = $m.'|'.$f['d_codigo'].'|'.$f['d_asenta'];
            $tipos[$k][] = $f['d_tipo_asenta'];
            if (isset($colonias[$k])) {
                $repetidos++;               // mismo nombre, CP y municipio; solo difiere el tipo de asentamiento
                continue;
            }
            $colonias[$k] = [$m, $f['d_codigo'], $f['d_asenta']];
            $variantes[$m.'|'.$f['d_codigo'].'|'.self::clave($f['d_asenta'])][$f['d_asenta']] = true;
        }
        if (count($estados) !== 32) {
            throw new RuntimeException('El archivo no contiene las 32 entidades: se requiere el catálogo nacional completo.');
        }
        $grafias = count(array_filter($variantes, fn ($v) => count($v) > 1));
        $consolidados = array_filter($tipos, fn ($t) => count($t) > 1);
        if ($ruta = $this->option('reporte')) {
            $this->reporte((string) $ruta, $consolidados, $estados, $municipios);
        }

        // 2) Conciliación con PostgreSQL (lectura en instantánea) y, con --aplicar, inserción de faltantes.
        $resultado = DB::transaction(function () use ($aplicar, $estados, $municipios, $colonias) {
            if ($aplicar) {
                DB::statement("set local lock_timeout = '5s'");
                DB::statement('lock table public.entidad_federativa, public.alcaldia_municipio, public.colonia in share row exclusive mode');
            } else {
                DB::statement('set transaction isolation level repeatable read read only');
            }

            $entidades = DB::table('public.entidad_federativa')->pluck('enfe_nombre', 'enfe_id_entidad_federativa')->all();
            $nuevasEntidades = [];
            foreach ($estados as $id => $nombre) {
                if (!array_key_exists($id, $entidades)) {
                    $nuevasEntidades[$id] = $nombre;
                } elseif (self::clave($entidades[$id]) !== self::clave($nombre)) {
                    throw new RuntimeException("ENTIDAD_FEDERATIVA {$id} es «{$entidades[$id]}» y SEPOMEX indica «{$nombre}»: se requiere revisión.");
                }
            }
            $ajenas = array_diff_key($entidades, $estados);
            if ($aplicar && $nuevasEntidades) {
                foreach ($nuevasEntidades as $id => $nombre) {
                    DB::table('public.entidad_federativa')->insert(['enfe_id_entidad_federativa' => $id, 'enfe_nombre' => $nombre]);
                }
                DB::statement("select setval(pg_get_serial_sequence('public.entidad_federativa', 'enfe_id_entidad_federativa'),
                    greatest((select max(enfe_id_entidad_federativa) from public.entidad_federativa), 1))");
            }

            $existentes = $porClave = [];
            foreach (DB::table('public.alcaldia_municipio')->get() as $a) {
                $k = $a->almu_id_entidad_federativa.'|'.self::clave($a->almu_nombre);
                if (isset($porClave[$k])) {
                    throw new RuntimeException("ALCALDIA_MUNICIPIO duplicada en la base: {$a->almu_nombre} (entidad {$a->almu_id_entidad_federativa}).");
                }
                $porClave[$k] = $a;
            }
            $idMunicipio = [];
            $nuevosMunicipios = 0;
            foreach ($municipios as $m => $nombre) {
                $entidad = (int) explode('|', $m)[0];
                $actual = $porClave[$entidad.'|'.self::clave($nombre)] ?? null;
                if ($actual && $actual->almu_nombre !== $nombre) {
                    throw new RuntimeException("ALCALDIA_MUNICIPIO «{$actual->almu_nombre}» difiere de SEPOMEX «{$nombre}»: se requiere revisión.");
                }
                if ($actual) {
                    $idMunicipio[$m] = (int) $actual->almu_id_alcaldia;
                    continue;
                }
                $nuevosMunicipios++;
                $idMunicipio[$m] = $aplicar
                    ? (int) DB::table('public.alcaldia_municipio')->insertGetId(['almu_id_entidad_federativa' => $entidad, 'almu_nombre' => $nombre], 'almu_id_alcaldia')
                    : null;
            }

            foreach (DB::table('public.colonia')->select('colo_id_alcaldia', 'colo_codigo_postal', 'colo_nombre')->cursor() as $c) {
                $existentes[$c->colo_id_alcaldia.'|'.$c->colo_codigo_postal.'|'.$c->colo_nombre] = true;
            }
            $nuevasColonias = 0;
            $lote = [];
            foreach ($colonias as [$m, $cp, $nombre]) {
                $id = $idMunicipio[$m];
                if ($id !== null && isset($existentes[$id.'|'.$cp.'|'.$nombre])) {
                    continue;
                }
                $nuevasColonias++;
                if ($aplicar) {
                    $lote[] = ['colo_id_alcaldia' => $id, 'colo_nombre' => $nombre, 'colo_codigo_postal' => $cp];
                    if (count($lote) === 1000) {
                        DB::table('public.colonia')->insert($lote);
                        $lote = [];
                    }
                }
            }
            if ($lote) {
                DB::table('public.colonia')->insert($lote);
            }

            return ['entidades_nuevas' => count($nuevasEntidades), 'entidades_ajenas_al_catalogo' => array_values($ajenas),
                'municipios_nuevos' => $nuevosMunicipios, 'colonias_nuevas' => $nuevasColonias,
                'totales_base' => [
                    'entidad_federativa' => DB::table('public.entidad_federativa')->count(),
                    'alcaldia_municipio' => DB::table('public.alcaldia_municipio')->count(),
                    'colonia' => DB::table('public.colonia')->count(),
                ]];
        });

        $resumen = [
            'modo' => $aplicar ? 'aplicación' : 'diagnóstico (solo lectura)',
            'archivo' => basename($archivo), 'sha256' => $huella, 'version_sepomex' => $lector->version, 'base' => $base,
            'filas_leidas' => $filas, 'entidades' => count($estados), 'municipios' => count($municipios),
            'colonias_distintas' => count($colonias), 'repetidos_solo_por_tipo_de_asentamiento' => $repetidos,
            'colonias_con_registros_consolidados' => count($consolidados),
            'nombres_con_variante_de_acentos_o_mayusculas' => $grafias,
        ] + $resultado + [
            'segundos' => round(microtime(true) - $inicio, 1),
            'memoria_pico_mb' => round(memory_get_peak_usage(true) / 1048576),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($resumen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            foreach ($resumen as $k => $v) {
                $this->line(str_pad($k, 44).': '.(is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v));
            }
        }

        return self::SUCCESS;
    }
}
