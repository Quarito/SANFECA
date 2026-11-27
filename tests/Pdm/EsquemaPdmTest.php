<?php

namespace Tests\Pdm;

use App\Models\Pdm\PdmModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Estructura instalada: PDM original + extensiones, modelos Eloquent alineados
 * con las columnas reales y public intacto.
 */
class EsquemaPdmTest extends PdmTestCase
{
    /** @return list<array{0:string,1:string,2:string,3:string}> [tabla_hija, columna, tabla_padre, columna_padre] */
    private function fkDeclaradasEnSql(): array
    {
        $sql = file_get_contents(database_path('sql/anfeca_modelo/anfeca_modelo.sql'))."\n"
            .file_get_contents(database_path('sql/anfeca_modelo/anfeca_modelo_extensiones.sql'))."\n"
            .file_get_contents(database_path('sql/anfeca_modelo/anfeca_modelo_extensiones_f4.sql'));
        $sql = preg_replace('/--[^\n]*/', '', $sql);

        $fks = [];
        preg_match_all('/(?:alter|create) table anfeca_modelo\.(\w+)(.*?);/is', $sql, $sentencias, PREG_SET_ORDER);

        foreach ($sentencias as [$todo, $tabla, $cuerpo]) {
            preg_match_all('/foreign key \((\w+)\)\s*references anfeca_modelo\.(\w+) \((\w+)\)/i', $cuerpo, $m, PREG_SET_ORDER);
            foreach ($m as [$x, $col, $padre, $colPadre]) {
                $fks[] = [strtolower($tabla), strtolower($col), strtolower($padre), strtolower($colPadre)];
            }
        }

        sort($fks);

        return $fks;
    }

    public function test_pdm_original_y_extensiones_instalados(): void
    {
        $tablas = DB::table('pg_tables')->where('schemaname', 'anfeca_modelo')->where('tablename', '<>', 'integracion_control')->count();
        $fks = DB::selectOne("select count(*) as n from pg_constraint where contype = 'f' and connamespace = 'anfeca_modelo'::regnamespace")->n;

        $this->assertSame(43, $tablas, '36 del PDM + 3 de F1 (zona, coordinador_zona, historico_certificado_importacion) + 4 de F4');
        $this->assertSame(47, (int) $fks, '35 del PDM + 7 de F1 + 5 de F4');
    }

    public function test_verificador_reconoce_el_esquema_base_con_extensiones(): void
    {
        $this->artisan('anfeca:modelo-verificar')
            ->expectsOutputToContain('36/36 tablas, 35/35 FK')
            ->expectsOutputToContain('Tablas de extensión y control')
            ->assertSuccessful();
    }

    public function test_cada_fk_del_sql_existe_en_postgresql(): void
    {
        $reales = array_map(
            fn ($r) => [$r->hija, $r->col, $r->padre, $r->colpadre],
            DB::select("
                select c.relname as hija, a.attname as col, p.relname as padre, ap.attname as colpadre
                from pg_constraint k
                join pg_class c on c.oid = k.conrelid
                join pg_class p on p.oid = k.confrelid
                join pg_attribute a on a.attrelid = k.conrelid and a.attnum = k.conkey[1]
                join pg_attribute ap on ap.attrelid = k.confrelid and ap.attnum = k.confkey[1]
                where k.contype = 'f' and k.connamespace = 'anfeca_modelo'::regnamespace")
        );
        sort($reales);

        $this->assertSame($this->fkDeclaradasEnSql(), $reales);
    }

    public function test_cada_modelo_corresponde_a_columnas_reales(): void
    {
        $modelos = glob(app_path('Models/Pdm/*.php'));
        $revisados = 0;

        foreach ($modelos as $archivo) {
            $clase = 'App\\Models\\Pdm\\'.basename($archivo, '.php');
            if ($clase === 'App\\Models\\Pdm\\PdmModel') {
                continue;
            }

            $modelo = new $clase;
            [$esquema, $tabla] = explode('.', $modelo->getTable());
            $columnas = DB::table('information_schema.columns')
                ->where('table_schema', $esquema)->where('table_name', $tabla)
                ->pluck('column_name')->all();

            $this->assertNotEmpty($columnas, "{$clase}: la tabla {$modelo->getTable()} no existe");
            $pk = DB::select("
                select a.attname
                from pg_constraint c
                cross join lateral unnest(c.conkey) with ordinality as k(attnum, posicion)
                join pg_attribute a on a.attrelid = c.conrelid and a.attnum = k.attnum
                where c.conrelid = to_regclass(?) and c.contype = 'p'
                order by k.posicion", [$modelo->getTable()]);
            $compuesta = (new ReflectionClass(PdmModel::class))->getProperty('columnasClaveCompuesta');
            $this->assertSame(array_column($pk, 'attname'),
                [$modelo->getKeyName(), ...$compuesta->getValue($modelo)], "{$clase}: PK distinta de PostgreSQL");
            $this->assertEqualsCanonicalizing($columnas, $modelo->getFillable(), "{$clase}: columnas distintas");
            $revisados++;
        }

        $this->assertSame(43, $revisados);
    }

    public function test_relaciones_eloquent_coinciden_con_las_fk_en_ambas_direcciones(): void
    {
        $perteneceA = [];
        $agrupa = [];

        foreach (glob(app_path('Models/Pdm/*.php')) as $archivo) {
            $clase = new ReflectionClass('App\\Models\\Pdm\\'.basename($archivo, '.php'));
            if ($clase->isAbstract()) {
                continue;
            }

            $modelo = $clase->newInstance();
            $tabla = substr($modelo->getTable(), strlen('anfeca_modelo.'));

            foreach ($clase->getMethods() as $metodo) {
                if ($metodo->getDeclaringClass()->getName() !== $clase->getName()) {
                    continue;
                }

                $tipo = $metodo->getReturnType();
                if (! $tipo instanceof ReflectionNamedType) {
                    continue;
                }

                if (is_a($tipo->getName(), BelongsTo::class, true)) {
                    $relacion = $metodo->invoke($modelo);
                    $padre = substr($relacion->getRelated()->getTable(), strlen('anfeca_modelo.'));
                    $perteneceA[] = [$tabla, $relacion->getForeignKeyName(), $padre, $relacion->getOwnerKeyName()];
                } elseif (is_a($tipo->getName(), HasOneOrMany::class, true)) {
                    $relacion = $metodo->invoke($modelo);
                    $hija = substr($relacion->getRelated()->getTable(), strlen('anfeca_modelo.'));
                    $agrupa[] = [$hija, $relacion->getForeignKeyName(), $tabla, $relacion->getLocalKeyName()];
                }
            }
        }

        sort($perteneceA);
        sort($agrupa);

        $this->assertSame($this->fkDeclaradasEnSql(), $perteneceA, 'Relaciones belongsTo');
        $this->assertSame($this->fkDeclaradasEnSql(), $agrupa, 'Relaciones inversas');
    }

    public function test_tabla_tecnica_de_control_existe_y_esta_vacia(): void
    {
        // F3: control del modo de integración; no es parte del PDM.
        $this->assertNotNull(DB::selectOne("select to_regclass('anfeca_modelo.integracion_control') as t")->t);
        $this->assertSame(0, DB::table('anfeca_modelo.integracion_control')->count(), 'el espejo no queda activado por omisión');
    }

    public function test_public_conserva_las_26_tablas_de_laravel(): void
    {
        // 25 tablas originales + revisiones_documentales (fase 6.3.1, migración autorizada 2026-11-25).
        $this->assertSame(26, DB::table('pg_tables')->where('schemaname', 'public')->count());
        $this->assertNotNull(DB::selectOne("select to_regclass('public.revisiones_documentales') as t")->t);
    }

    public function test_tablas_de_etapa_2_listas_y_vacias(): void
    {
        foreach (['evaluacion', 'dictamen', 'tipo_dictamen', 'acta', 'certificado', 'refrendo_certificado', 'convocatoria', 'colonia', 'alcaldia_municipio'] as $tabla) {
            $this->assertSame(0, DB::table("anfeca_modelo.{$tabla}")->count(), "{$tabla} no debe tener datos inventados");
        }
    }
}
