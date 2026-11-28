<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Códigos de salida (los bloques de operación los distinguen):
 *   0 = el esquema coincide con el SQL oficial · 2 = hay diferencias · 3 = no hay conexión con PostgreSQL
 *   4 = no se pudo leer el SQL oficial de referencia. (Laravel devuelve 1 si el comando no existe.)
 *
 * Compara el esquema físico de la base conectada con el SQL oficial PowerDesigner (solo lectura):
 * tablas, atributos, tipos, longitudes, nulos, PK, FK (columnas y reglas), índices y secuencias,
 * y verifica que no exista ningún otro objeto (tablas, vistas, esquemas, funciones).
 */
class VerificarEsquemaOficial extends Command
{
    protected $signature = 'anfeca:verificar-esquema {--json : Salida JSON}';

    protected $description = 'Compara la base con la referencia vigente de 36 tablas: SQL oficial + correcciones autorizadas (solo lectura).';

    private const TIPOS = [
        'SERIAL' => 'integer', 'INT4' => 'integer', 'VARCHAR' => 'character varying', 'BOOL' => 'boolean',
        'DATE' => 'date', 'TIME' => 'time without time zone', 'MONEY' => 'money',
    ];

    public const SIN_DIFERENCIAS = 0;
    public const CON_DIFERENCIAS = 2;
    public const SIN_CONEXION = 3;
    public const SIN_REFERENCIA = 4;

    public function handle(): int
    {
        require_once base_path('scripts/anfeca_8088/ModeloOficial.php');
        $ruta = (string) config('anfeca_8088.sql_oficial');
        try {
            if (!is_file($ruta) || !is_readable($ruta)) {
                throw new \RuntimeException("No se puede leer {$ruta}.");
            }
            $modelo = \ModeloOficial::leer($ruta);
            if (count($modelo['tablas']) === 0) {
                throw new \RuntimeException("{$ruta} no contiene tablas.");
            }
        } catch (\Throwable $e) {
            $this->error('SQL oficial de referencia no disponible: '.$e->getMessage());

            return self::SIN_REFERENCIA;
        }
        try {
            DB::connection()->getPdo();
            DB::selectOne('select 1');
        } catch (\Throwable $e) {
            $this->error('Sin conexión con PostgreSQL: '.$e->getMessage());

            return self::SIN_CONEXION;
        }
        $dif = [];

        $tablasBd = collect(DB::select("select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE'"))->pluck('table_name')->all();
        foreach (array_diff($tablasBd, array_keys($modelo['tablas'])) as $t) {
            $dif[] = "Tabla adicional: {$t}";
        }
        $columnas = 0;
        foreach ($modelo['tablas'] as $tabla => $def) {
            if (!in_array($tabla, $tablasBd, true)) {
                $dif[] = "Falta la tabla {$tabla}";
                continue;
            }
            $fisicas = collect(DB::select("select column_name, data_type, character_maximum_length, is_nullable, column_default
                from information_schema.columns where table_schema = 'public' and table_name = ?", [$tabla]))->keyBy('column_name');
            foreach (array_diff($fisicas->keys()->all(), array_keys($def['columnas'])) as $c) {
                $dif[] = "{$tabla}: columna adicional {$c}";
            }
            foreach ($def['columnas'] as $c => $d) {
                $columnas++;
                $f = $fisicas->get($c);
                if (!$f) {
                    $dif[] = "{$tabla}: falta la columna {$c}";
                    continue;
                }
                if ($f->data_type !== self::TIPOS[$d['tipo']]) {
                    $dif[] = "{$tabla}.{$c}: tipo {$f->data_type}, esperado {$d['tipo']}";
                }
                if ($d['longitud'] !== null && (int) $f->character_maximum_length !== $d['longitud']) {
                    $dif[] = "{$tabla}.{$c}: longitud {$f->character_maximum_length}, esperada {$d['longitud']}";
                }
                $nulo = $f->is_nullable === 'YES';
                if ($nulo !== $d['nulo']) {
                    $dif[] = "{$tabla}.{$c}: nulos ".($nulo ? 'permitidos' : 'no permitidos').', esperado '.($d['nulo'] ? 'null' : 'not null');
                }
                if ($d['tipo'] === 'SERIAL' && !str_starts_with((string) $f->column_default, 'nextval(')) {
                    $dif[] = "{$tabla}.{$c}: sin secuencia SERIAL";
                }
            }
            $pk = collect(DB::select("select a.attname from pg_index i join pg_attribute a on a.attrelid = i.indrelid and a.attnum = any(i.indkey)
                where i.indrelid = ?::regclass and i.indisprimary order by array_position(i.indkey, a.attnum)", ["public.{$tabla}"]))->pluck('attname')->all();
            if ($pk !== $def['pk']) {
                $dif[] = "{$tabla}: PK (".implode(', ', $pk).'), esperada ('.implode(', ', $def['pk']).')';
            }
        }

        $fksBd = collect(DB::select("select c.conname, cl.relname as tabla, a.attname as columna, rf.relname as referencia, af.attname as columna_referencia,
                c.confupdtype, c.confdeltype
            from pg_constraint c join pg_class cl on cl.oid = c.conrelid join pg_class rf on rf.oid = c.confrelid
            join pg_attribute a on a.attrelid = c.conrelid and a.attnum = c.conkey[1]
            join pg_attribute af on af.attrelid = c.confrelid and af.attnum = c.confkey[1]
            where c.contype = 'f' and c.connamespace = 'public'::regnamespace"))->keyBy('conname');
        foreach ($modelo['fks'] as $fk) {
            $f = $fksBd->get($fk['nombre']);
            if (!$f || $f->tabla !== $fk['tabla'] || $f->columna !== $fk['columna'] || $f->referencia !== $fk['referencia']
                || $f->columna_referencia !== $fk['columna_referencia'] || $f->confupdtype !== 'r' || $f->confdeltype !== 'r') {
                $dif[] = "FK {$fk['nombre']} ausente o distinta";
            }
        }
        foreach (array_diff($fksBd->keys()->all(), array_column($modelo['fks'], 'nombre')) as $n) {
            $dif[] = "FK adicional: {$n}";
        }

        $indicesBd = collect(DB::select("select indexname, tablename, indexdef from pg_indexes where schemaname = 'public'"))->keyBy('indexname');
        $pkNombres = collect(DB::select("select conname from pg_constraint where contype = 'p' and connamespace = 'public'::regnamespace"))->pluck('conname')->all();
        foreach ($modelo['indices'] as $i) {
            $f = $indicesBd->get($i['nombre']);
            $columnasDef = $f && preg_match('/\((.*)\)$/', $f->indexdef, $m) ? array_map('trim', explode(',', $m[1])) : null;
            if (!$f || $f->tablename !== $i['tabla'] || $columnasDef !== $i['columnas'] || str_contains($f->indexdef, 'UNIQUE') !== $i['unico']) {
                $dif[] = "Índice {$i['nombre']} ausente o distinto";
            }
        }
        foreach (array_diff($indicesBd->keys()->all(), array_column($modelo['indices'], 'nombre'), $pkNombres) as $n) {
            $dif[] = "Índice adicional: {$n}";
        }

        $otros = [
            'esquemas' => collect(DB::select("select nspname from pg_namespace where nspname !~ '^pg_' and nspname not in ('information_schema', 'public')"))->pluck('nspname')->all(),
            'tablas_en_otros_esquemas' => collect(DB::select("select table_schema||'.'||table_name as t from information_schema.tables where table_schema not in ('public', 'pg_catalog', 'information_schema')"))->pluck('t')->all(),
            'vistas' => collect(DB::select("select viewname from pg_views where schemaname = 'public'"))->pluck('viewname')->all(),
            'funciones' => collect(DB::select("select proname from pg_proc where pronamespace = 'public'::regnamespace"))->pluck('proname')->all(),
            'disparadores' => collect(DB::select("select tgname from pg_trigger t join pg_class c on c.oid = t.tgrelid where c.relnamespace = 'public'::regnamespace and not t.tgisinternal"))->pluck('tgname')->all(),
        ];
        foreach ($otros as $tipo => $lista) {
            foreach ($lista as $n) {
                $dif[] = "Objeto adicional ({$tipo}): {$n}";
            }
        }
        $secuencias = (int) DB::selectOne("select count(*) as n from pg_sequences where schemaname = 'public'")->n;

        $resumen = [
            'sql_oficial' => basename($ruta),
            'sha256' => hash_file('sha256', $ruta),
            'base' => DB::selectOne('select current_database() as b')->b,
            'servidor' => DB::selectOne("select inet_server_addr()::text as h, inet_server_port() as p, current_setting('server_version') as v"),
            'tablas' => ['oficial' => count($modelo['tablas']), 'fisicas' => count($tablasBd)],
            'atributos' => ['oficial' => $columnas, 'fisicos' => (int) DB::selectOne("select count(*) as n from information_schema.columns c
                join information_schema.tables t on t.table_schema = c.table_schema and t.table_name = c.table_name
                where c.table_schema = 'public' and t.table_type = 'BASE TABLE'")->n],
            'estado' => $dif === [] ? 'sin_diferencias' : 'con_diferencias',
            'fk' => ['oficial' => count($modelo['fks']), 'fisicas' => $fksBd->count()],
            'indices' => ['oficial_explicitos' => count($modelo['indices']), 'fisicos' => $indicesBd->count(), 'de_pk' => count($pkNombres)],
            'secuencias' => $secuencias,
            'diferencias' => $dif,
        ];
        if ($this->option('json')) {
            $this->line(json_encode($resumen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info("SQL oficial {$resumen['sql_oficial']} (sha256 {$resumen['sha256']}) contra la base {$resumen['base']} "
                ."({$resumen['servidor']->h}:{$resumen['servidor']->p}, PostgreSQL {$resumen['servidor']->v}).");
            $this->line("Tablas {$resumen['tablas']['fisicas']}/{$resumen['tablas']['oficial']} · atributos {$resumen['atributos']['fisicos']}/{$columnas} · FK {$resumen['fk']['fisicas']}/{$resumen['fk']['oficial']}"
                ." · índices {$resumen['indices']['fisicos']} ({$resumen['indices']['oficial_explicitos']} explícitos + {$resumen['indices']['de_pk']} de PK) · secuencias {$secuencias}");
            $dif ? $this->error(count($dif).' diferencias:') : $this->info('Sin diferencias: el esquema físico coincide exactamente con el SQL oficial (referencia vigente con correcciones autorizadas).');
            foreach ($dif as $d) {
                $this->line(" - {$d}");
            }
        }

        return $dif === [] ? self::SIN_DIFERENCIAS : self::CON_DIFERENCIAS;
    }
}
