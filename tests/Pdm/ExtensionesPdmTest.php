<?php

namespace Tests\Pdm;

use App\Models\Pdm\PersonaEstudioProfesional;
use App\Models\Pdm\Zona;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Extensiones aditivas (D-2): restricciones nuevas y reversión protegida.
 */
class ExtensionesPdmTest extends PdmTestCase
{
    use ExpedienteFicticio;

    private function migracion(): object
    {
        return require database_path('migrations_pdm/2026_11_21_000001_extender_anfeca_modelo.php');
    }

    private function esperarError(string $codigo, callable $accion, string $mensaje): void
    {
        try {
            DB::transaction($accion);
            $this->fail($mensaje);
        } catch (QueryException $e) {
            $this->assertSame($codigo, $e->getCode(), $mensaje.' — '.$e->getMessage());
        }
    }

    public function test_columnas_de_extension_con_sus_tipos(): void
    {
        $esperadas = [
            'persona' => ['pers_cedula_profesional', 'pers_datos_autorizados', 'pers_fecha_autorizacion'],
            'domicilio' => ['domi_colonia', 'domi_municipio', 'domi_codigo_postal'],
            'solicitud' => ['soli_tipo_tramite', 'soli_certificacion', 'soli_id_historico_importacion'],
            'persona_estudio_profesional' => ['pees_id_solicitud', 'pees_tipo'],
            'solicitud_documento' => ['socu_url_archivo'],
            'institucion' => ['inst_id_zona', 'inst_campus'],
            'historico_certificado' => ['hice_id_importacion', 'hice_certificacion'],
        ];

        foreach ($esperadas as $tabla => $columnas) {
            $reales = DB::table('information_schema.columns')
                ->where('table_schema', 'anfeca_modelo')->where('table_name', $tabla)->pluck('column_name')->all();

            foreach ($columnas as $columna) {
                $this->assertContains($columna, $reales, "{$tabla}.{$columna}");
            }
        }

        $default = DB::selectOne("select column_default from information_schema.columns
            where table_schema = 'anfeca_modelo' and table_name = 'persona' and column_name = 'pers_datos_autorizados'")->column_default;
        $this->assertSame('false', $default, 'Una persona nueva no queda autorizada por omisión.');
    }

    public function test_restricciones_de_dominio(): void
    {
        ['solicitud' => $s, 'persona' => $p] = $this->expediente();

        $this->esperarError('23514', fn () => DB::table('anfeca_modelo.solicitud')
            ->where('soli_id_solicitud', $s->soli_id_solicitud)->update(['soli_tipo_tramite' => 'otro']),
            'tipo de trámite fuera de inicial/refrendo');

        $this->esperarError('23514', fn () => DB::table('anfeca_modelo.solicitud')
            ->where('soli_id_solicitud', $s->soli_id_solicitud)->update(['soli_certificacion' => 'XYZ']),
            'certificación fuera de ANFECA/CACSLA/CACECA');

        $this->esperarError('23505', fn () => DB::table('anfeca_modelo.persona')->insert([
            'pers_nombre' => 'Duplicada', 'pers_curp' => $p->pers_curp, 'pers_rfc' => 'XXXX800101AB1',
        ]), 'CURP duplicada');

        $this->esperarError('23505', fn () => PersonaEstudioProfesional::create([
            'pees_id_estuido_profesional' => 1, 'pees_id_persona' => $p->pers_id_persona,
            'pees_id_solicitud' => $s->soli_id_solicitud, 'pees_tipo' => 'ORIGEN',
        ]), 'dos formaciones de origen en la misma solicitud');

        $this->esperarError('23505', fn () => DB::table('anfeca_modelo.solicitud')->insert([
            'soli_id_persona' => $p->pers_id_persona, 'soli_id_area_certificacion' => $s->soli_id_area_certificacion,
            'soli_id_tipo_personal_academico' => $s->soli_id_tipo_personal_academico,
            'soli_fecha_registro_solicitud' => now()->toDateString(), 'soli_folio_solicitud' => $s->soli_folio_solicitud,
            'soli_art13' => false, 'soli_art14' => false, 'soli_tipo_tramite' => 'inicial', 'soli_certificacion' => 'ANFECA',
        ]), 'folio de solicitud duplicado');

        $this->esperarError('23514', fn () => DB::table('anfeca_modelo.historico_certificado_importacion')->insert([
            'hcim_id_importacion' => 1, 'hcim_estado_normalizacion' => 'OTRO',
        ]), 'estado de normalización inválido');
    }

    public function test_reversion_se_niega_si_hay_datos(): void
    {
        Zona::create(['zona_siglas' => 'ZF', 'zona_descripcion' => 'Zona ficticia']);

        try {
            $this->migracion()->down();
            $this->fail('down() no debe proceder con datos.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('anfeca_modelo.zona tiene datos', $e->getMessage());
        }

        $this->assertSame(39, DB::table('pg_tables')->where('schemaname', 'anfeca_modelo')->whereNotIn('tablename', ['integracion_control', 'seleccion_certificacion', 'solicitud_refrendo', 'formacion_solicitud', 'documentos_solicitud'])->count());
    }

    public function test_reversion_y_reaplicacion_sin_datos(): void
    {
        // La capa anfeca_compat (F4) depende de las extensiones: se retira primero y se reinstala al final.
        $this->capaCompatibilidad()->down();
        $this->migracion()->down();

        $this->assertSame(36, DB::table('pg_tables')->where('schemaname', 'anfeca_modelo')->whereNotIn('tablename', ['integracion_control', 'seleccion_certificacion', 'solicitud_refrendo', 'formacion_solicitud', 'documentos_solicitud'])->count());
        $this->assertSame(35, (int) DB::selectOne("select count(*) as n from pg_constraint where contype = 'f' and connamespace = 'anfeca_modelo'::regnamespace and conrelid::regclass::text not in ('anfeca_modelo.seleccion_certificacion', 'anfeca_modelo.solicitud_refrendo', 'anfeca_modelo.formacion_solicitud', 'anfeca_modelo.documentos_solicitud')")->n);
        $this->assertSame(26, DB::table('pg_tables')->where('schemaname', 'public')->count(), 'public no cambia (25 + revisiones_documentales)');
        $this->assertSame(32, DB::table('anfeca_modelo.entidad_federativa')->count(), 'los catálogos se conservan');

        $this->migracion()->up();

        $this->assertSame(39, DB::table('pg_tables')->where('schemaname', 'anfeca_modelo')->whereNotIn('tablename', ['integracion_control', 'seleccion_certificacion', 'solicitud_refrendo', 'formacion_solicitud', 'documentos_solicitud'])->count());
        $this->assertSame(42, (int) DB::selectOne("select count(*) as n from pg_constraint where contype = 'f' and connamespace = 'anfeca_modelo'::regnamespace and conrelid::regclass::text not in ('anfeca_modelo.seleccion_certificacion', 'anfeca_modelo.solicitud_refrendo', 'anfeca_modelo.formacion_solicitud', 'anfeca_modelo.documentos_solicitud')")->n);

        $this->capaCompatibilidad()->up();
        $this->assertSame(9, DB::table('information_schema.views')->where('table_schema', 'anfeca_compat')->count());
    }

    public function test_las_extensiones_no_se_revierten_con_la_capa_de_compatibilidad_instalada(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->expectExceptionMessage('anfeca_compat');
        $this->migracion()->down();
    }

    private function capaCompatibilidad(): object
    {
        return require database_path('migrations_pdm/2026_11_21_000004_instalar_capa_compatibilidad.php');
    }

    public function test_extensiones_protegen_la_base_en_uso(): void
    {
        config(['anfeca_modelo.bd_permitida' => DB::connection()->getDatabaseName()]);
        $this->capaCompatibilidad()->down();
        $this->migracion()->down();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ANFECA_PDM_AUTORIZAR_EXTENSIONES=si');
        $this->migracion()->up();
    }
}
