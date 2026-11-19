<?php

namespace Tests\Pdm;

use Illuminate\Support\Facades\DB;

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
            .file_get_contents(database_path('sql/anfeca_modelo/anfeca_modelo_extensiones.sql'));
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
        $tablas = DB::table('pg_tables')->where('schemaname', 'anfeca_modelo')->count();
        $fks = DB::selectOne("select count(*) as n from pg_constraint where contype = 'f' and connamespace = 'anfeca_modelo'::regnamespace")->n;

        $this->assertSame(39, $tablas, '36 del PDM + zona, coordinador_zona, historico_certificado_importacion');
        $this->assertSame(42, (int) $fks, '35 del PDM + 7 de extensión');
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

            $modelo = new $clase();
            [$esquema, $tabla] = explode('.', $modelo->getTable());
            $columnas = DB::table('information_schema.columns')
                ->where('table_schema', $esquema)->where('table_name', $tabla)
                ->pluck('column_name')->all();

            $this->assertNotEmpty($columnas, "{$clase}: la tabla {$modelo->getTable()} no existe");
            $this->assertContains($modelo->getKeyName(), $columnas, "{$clase}: PK inexistente");
            $this->assertEqualsCanonicalizing($columnas, $modelo->getFillable(), "{$clase}: columnas distintas");
            $revisados++;
        }

        $this->assertSame(39, $revisados);
    }

    public function test_public_conserva_las_25_tablas_de_laravel(): void
    {
        $this->assertSame(25, DB::table('pg_tables')->where('schemaname', 'public')->count());
    }

    public function test_tablas_de_etapa_2_listas_y_vacias(): void
    {
        foreach (['evaluacion', 'dictamen', 'tipo_dictamen', 'acta', 'certificado', 'refrendo_certificado', 'convocatoria', 'colonia', 'alcaldia_municipio'] as $tabla) {
            $this->assertSame(0, DB::table("anfeca_modelo.{$tabla}")->count(), "{$tabla} no debe tener datos inventados");
        }
    }
}
