<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ImportarSepomex extends Command
{
    protected $signature = 'anfeca:sepomex {archivo : TXT nacional oficial} {--aplicar : Inserta únicamente faltantes} {--base= : Nombre exacto de la base autorizada} {--sha256= : Huella esperada del archivo}';
    protected $description = 'Diagnostica o importa SEPOMEX sin modificar el esquema ni domicilios';

    private function normal(string $s): string
    {
        return Str::lower(Str::ascii(preg_replace('/\s+/u', ' ', trim($s))));
    }

    public function handle(): int
    {
        try {
            $file = $this->argument('archivo');
            $raw = file_get_contents($file);
            if ($raw === false) throw new RuntimeException('Archivo ilegible');
            $hash = hash('sha256', $raw);
            $this->info('SHA-256: '.$hash);
            $apply = (bool) $this->option('aplicar');
            if ($apply && (! $this->option('sha256') || ! hash_equals($hash, strtolower($this->option('sha256'))))) {
                throw new RuntimeException('Aplicación requiere SHA-256 verificado');
            }
            if (! mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
            $raw = preg_replace('/^\x{FEFF}/u', '', $raw);
            $header = null;
            $rows = [];
            $states = [];
            $municipalCodes = [];
            $seen = [];
            $duplicates = 0;
            $required = ['d_codigo','d_asenta','D_mnpio','d_estado','c_estado','c_mnpio','id_asenta_cpcons'];
            foreach (preg_split('/\r\n|\n|\r/', $raw) as $i => $line) {
                if (trim($line) === '') continue;
                $cols = array_map('trim', explode('|', $line));
                if ($header === null) {
                    if (in_array('d_codigo', $cols, true)) {
                        if (count(array_unique($cols)) !== count($cols) || array_diff($required, $cols)) throw new RuntimeException('Encabezado inválido');
                        $header = $cols;
                    }
                    continue;
                }
                if (count($cols) !== count($header)) throw new RuntimeException('Número de campos inválido en línea '.($i+1));
                $r = array_combine($header, $cols);
                if (! preg_match('/^[0-9]{5}$/D', $r['d_codigo']) || ! preg_match('/^[0-9]{2}$/D', $r['c_estado']) || ! preg_match('/^[0-9]{3}$/D', $r['c_mnpio']) || ! preg_match('/^[0-9]+$/D', $r['id_asenta_cpcons'])) throw new RuntimeException('Claves inválidas en línea '.($i+1));
                $state = (int) $r['c_estado'];
                $official = config('anfeca_8088.entidades_federativas.'.$state);
                $name = $this->normal($r['d_estado']);
                $alias = config('anfeca_8088.alias_entidades', [])[$name] ?? $r['d_estado'];
                if (! $official || $this->normal($alias) !== $this->normal($official)) throw new RuntimeException('Entidad no conciliada en línea '.($i+1));
                foreach (['D_mnpio','d_asenta'] as $field) {
                    if ($r[$field] === '' || mb_strlen($r[$field]) > 150 || preg_match('/[\x00-\x1F]/u', $r[$field])) throw new RuntimeException('Nombre inválido en línea '.($i+1));
                }
                $m = $state.'|'.$this->normal($r['D_mnpio']);
                $code = $state.'|'.$r['c_mnpio'];
                if (isset($municipalCodes[$m]) && $municipalCodes[$m] !== $code) throw new RuntimeException('Municipios homónimos: '.$m);
                $municipalCodes[$m] = $code;
                $key = $m.'|'.$r['d_codigo'].'|'.$this->normal($r['d_asenta']);
                $source = $code.'|'.$r['id_asenta_cpcons'].'|'.($r['d_tipo_asenta'] ?? '');
                if (isset($seen[$key])) {
                    if ($seen[$key] !== $source) throw new RuntimeException('Asentamientos indistinguibles en el esquema: '.$key);
                    $duplicates++;
                    continue;
                }
                $seen[$key] = $source;
                $states[$state] = $official;
                $rows[$m][] = [$r['D_mnpio'], $r['d_asenta'], $r['d_codigo'], $state];
            }
            if (count($states) !== 32 || ! $rows) throw new RuntimeException('Se requiere catálogo nacional de 32 entidades');
            $this->info('Entidades: '.count($states).'; municipios: '.count($rows).'; asentamientos: '.count($seen).'; duplicados equivalentes: '.$duplicates);
            if (DB::connection()->getDriverName() !== 'pgsql') throw new RuntimeException('Solo PostgreSQL');
            $db = DB::selectOne('select current_database() as nombre')->nombre;
            $this->info('Base: '.$db);
            if ($apply && ($this->option('base') !== $db)) throw new RuntimeException('Base no autorizada');
            $counts = DB::transaction(function () use ($apply, $states, $rows) {
                if ($apply) {
                    DB::statement("SET LOCAL lock_timeout = '5s'");
                    DB::statement('LOCK TABLE public.entidad_federativa, public.alcaldia_municipio, public.colonia IN SHARE ROW EXCLUSIVE MODE');
                } else {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
                }
                $entities = DB::table('public.entidad_federativa')->get();
                foreach ($states as $id => $name) {
                    $matches = $entities->filter(fn ($e) => $this->normal($e->enfe_nombre) === $this->normal($name));
                    if ($matches->count() !== 1 || (int) $matches->first()->enfe_id_entidad_federativa !== $id) throw new RuntimeException('Entidad existente no coincide con configuración: '.$name);
                }
                $municipalities = [];
                foreach (DB::table('public.alcaldia_municipio')->get() as $m) {
                    $key = $m->almu_id_entidad_federativa.'|'.$this->normal($m->almu_nombre);
                    if (isset($municipalities[$key])) throw new RuntimeException('Municipio duplicado existente: '.$key);
                    $municipalities[$key] = $m->almu_id_alcaldia;
                }
                $colonies = [];
                foreach (DB::table('public.colonia')->cursor() as $c) {
                    $key = $c->colo_id_alcaldia.'|'.$c->colo_codigo_postal.'|'.$this->normal($c->colo_nombre);
                    if (isset($colonies[$key])) throw new RuntimeException('Colonia duplicada existente: '.$key);
                    $colonies[$key] = true;
                }
                $nm = $nc = 0;
                foreach ($rows as $key => $items) {
                    [$name,,, $state] = $items[0];
                    $id = $municipalities[$key] ?? null;
                    if ($id === null) {
                        $nm++;
                        if ($apply) $id = DB::table('public.alcaldia_municipio')->insertGetId(['almu_id_entidad_federativa'=>$state,'almu_nombre'=>$name], 'almu_id_alcaldia');
                    }
                    $batch = [];
                    foreach ($items as [, $colony, $cp]) {
                        if ($id !== null && isset($colonies[$id.'|'.$cp.'|'.$this->normal($colony)])) continue;
                        $nc++;
                        if ($apply) {
                            $batch[] = ['colo_id_alcaldia'=>$id,'colo_nombre'=>$colony,'colo_codigo_postal'=>$cp];
                            if (count($batch) === 500) {
                                DB::table('public.colonia')->insert($batch);
                                $batch = [];
                            }
                        }
                    }
                    if ($batch) DB::table('public.colonia')->insert($batch);
                }
                return [$nm,$nc];
            });
            $this->info(($apply ? 'Insertados' : 'Pendientes (solo lectura)').': municipios '.$counts[0].'; colonias '.$counts[1]);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
