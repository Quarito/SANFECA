<?php

namespace App\Console\Commands;

use App\Services\Anfeca\Sepomex\CatalogoSepomex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
        return CatalogoSepomex::clave($s);
    }

    /** Lleva la secuencia SERIAL al máximo existente si quedó atrás (nunca la disminuye). */
    public static function ajustarSecuencia(string $tabla, string $columna): void
    {
        $sec = DB::selectOne('select pg_get_serial_sequence(?, ?) as s', ["public.{$tabla}", $columna])->s;
        DB::statement("select setval('{$sec}', x.m) from (select max({$columna}) as m from public.{$tabla}) x, {$sec} s
            where x.m is not null and (x.m > s.last_value or (x.m = s.last_value and not s.is_called))");
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

    /** CSV con los registros consolidados por tipo de asentamiento (fuera de public/ y de .git). */
    private function reporte(string $ruta, CatalogoSepomex $catalogo): void
    {
        $dir = realpath(dirname($ruta)) ?: '';
        if (str_starts_with($dir, public_path()) || str_starts_with($dir, base_path().'/.git')) {
            throw new RuntimeException('El reporte no puede escribirse en public/ ni en .git.');
        }
        $catalogo->escribirConsolidados($ruta);
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

        // 1) Lectura y validación del archivo (sin base de datos), compartida con el generador de scripts SQL.
        $catalogo = CatalogoSepomex::leer($archivo);
        $estados = $catalogo->estados;
        $municipios = $catalogo->municipios;
        $colonias = $catalogo->colonias;
        $consolidados = $catalogo->consolidados();
        if ($ruta = $this->option('reporte')) {
            $this->reporte((string) $ruta, $catalogo);
        }

        // 2) Conciliación con PostgreSQL (lectura en instantánea) y, con --aplicar, inserción de faltantes.
        $resultado = DB::transaction(function () use ($aplicar, $estados, $municipios, $colonias) {
            if ($aplicar) {
                DB::statement("set local lock_timeout = '5s'");
                DB::statement('lock table public.entidad_federativa, public.alcaldia_municipio, public.colonia in share row exclusive mode');
            } else {
                DB::statement('set transaction isolation level repeatable read read only');
            }

            if ($aplicar) {
                DB::select("select pg_advisory_xact_lock(hashtext('anfeca:sepomex'))");
            }
            // Entidades: se concilian por nombre (sin acentos ni mayúsculas) y se reutiliza su identificador real.
            // Una entidad nueva toma la clave oficial c_estado si está libre; si esa clave la ocupa otra entidad del
            // catálogo, toma el siguiente valor de la secuencia; si la ocupa un nombre ajeno al catálogo, se detiene.
            $entidades = DB::table('public.entidad_federativa')->pluck('enfe_nombre', 'enfe_id_entidad_federativa')->all();
            $clavesCatalogo = array_map(self::clave(...), $estados);
            $nuevasEntidades = [];
            $secuenciaAjustada = false;
            foreach ($estados as $id => $nombre) {
                $coincidencias = array_keys(array_filter($entidades, fn ($n) => self::clave($n) === self::clave($nombre)));
                if (count($coincidencias) > 1) {
                    throw new RuntimeException("ENTIDAD_FEDERATIVA «{$nombre}» está registrada más de una vez (".implode(', ', $coincidencias).'): se requiere revisión.');
                }
                if ($coincidencias) {
                    continue;
                }
                if (array_key_exists($id, $entidades) && !in_array(self::clave($entidades[$id]), $clavesCatalogo, true)) {
                    throw new RuntimeException("ENTIDAD_FEDERATIVA {$id} es «{$entidades[$id]}» y SEPOMEX indica «{$nombre}»: se requiere revisión.");
                }
                $nuevasEntidades[$id] = $nombre;
                if ($aplicar) {
                    if (array_key_exists($id, $entidades)) {
                        if (!$secuenciaAjustada) {
                            self::ajustarSecuencia('entidad_federativa', 'enfe_id_entidad_federativa');
                            $secuenciaAjustada = true;
                        }
                        $nuevo = (int) DB::table('public.entidad_federativa')->insertGetId(['enfe_nombre' => $nombre], 'enfe_id_entidad_federativa');
                    } else {
                        DB::table('public.entidad_federativa')->insert(['enfe_id_entidad_federativa' => $id, 'enfe_nombre' => $nombre]);
                        $nuevo = $id;
                    }
                    $entidades[$nuevo] = $nombre;
                }
            }
            $ajenas = array_values(array_filter($entidades, fn ($n) => !in_array(self::clave($n), $clavesCatalogo, true)));
            if ($aplicar && $nuevasEntidades) {
                self::ajustarSecuencia('entidad_federativa', 'enfe_id_entidad_federativa');
            }
            $idEntidad = [];
            foreach ($entidades as $idReal => $n) {
                $idEntidad[self::clave($n)] = (int) $idReal;
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
                $entidad = $idEntidad[self::clave($estados[(int) explode('|', $m)[0]])] ?? null;
                $actual = $entidad === null ? null : ($porClave[$entidad.'|'.self::clave($nombre)] ?? null);
                if ($actual && $actual->almu_nombre !== $nombre) {
                    throw new RuntimeException("ALCALDIA_MUNICIPIO «{$actual->almu_nombre}» difiere de SEPOMEX «{$nombre}»: se requiere revisión.");
                }
                if ($actual) {
                    $idMunicipio[$m] = (int) $actual->almu_id_alcaldia;
                    continue;
                }
                $nuevosMunicipios++;
                if ($aplicar && $nuevosMunicipios === 1) {
                    self::ajustarSecuencia('alcaldia_municipio', 'almu_id_alcaldia');
                }
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
                    if ($nuevasColonias === 1) {
                        self::ajustarSecuencia('colonia', 'colo_id_colonia');
                    }
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

            return ['entidades_nuevas' => count($nuevasEntidades), 'entidades_ajenas_al_catalogo' => $ajenas,
                'municipios_nuevos' => $nuevosMunicipios, 'colonias_nuevas' => $nuevasColonias,
                'totales_base' => [
                    'entidad_federativa' => DB::table('public.entidad_federativa')->count(),
                    'alcaldia_municipio' => DB::table('public.alcaldia_municipio')->count(),
                    'colonia' => DB::table('public.colonia')->count(),
                ]];
        });

        $resumen = [
            'modo' => $aplicar ? 'aplicación' : 'diagnóstico (solo lectura)',
            'archivo' => basename($archivo), 'sha256' => $huella, 'version_sepomex' => $catalogo->version, 'base' => $base,
            'filas_leidas' => $catalogo->filas, 'entidades' => count($estados), 'municipios' => count($municipios),
            'colonias_distintas' => count($colonias), 'repetidos_solo_por_tipo_de_asentamiento' => $catalogo->repetidos,
            'colonias_con_registros_consolidados' => count($consolidados),
            'nombres_con_variante_de_acentos_o_mayusculas' => $catalogo->grafias,
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
