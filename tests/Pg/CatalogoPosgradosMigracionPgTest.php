<?php

namespace Tests\Pg;

use App\Services\Catalogos\CatalogoPosgrados;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use RuntimeException;
use Tests\TestCase;

/**
 * Migración PROPUESTA del catálogo de posgrados (columnas aditivas + CHECK de coherencia) en PostgreSQL 18
 * desechable. Solo bd_anfeca_desechable_pruebas_pg; datos ficticios. No se aplica en 8086.
 */
class CatalogoPosgradosMigracionPgTest extends TestCase
{
    private const MIGRACION = 'database/migrations_propuestas/2026_11_27_000000_add_titulo_especifico_to_formaciones_academicas_solicitud.php';
    private int $formacion;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql' || DB::connection()->getDatabaseName() !== 'bd_anfeca_desechable_pruebas_pg') {
            throw new RuntimeException('SEGURIDAD: la migración propuesta solo se prueba en bd_anfeca_desechable_pruebas_pg.');
        }
        Artisan::call('migrate:fresh', ['--force' => true]);   // ruta ordinaria: NO incluye la propuesta
        CatalogoPosgrados::olvidarPersistencia();
        $this->assertFalse(CatalogoPosgrados::persistenciaDisponible(), 'migrate ordinario no aplica la propuesta.');

        $u = User::create(['name' => 'Solicitante ficticio', 'email' => 'cat.pg@example.test', 'password' => Hash::make('ContrasenaFicticia1')]);
        $s = DB::table('solicitudes_certificacion')->insertGetId(['user_id' => $u->id, 'tipo_tramite' => 'inicial', 'certificacion' => 'ANFECA',
            'estado' => 'borrador', 'tipo_personal' => 'Profesoras y profesores de Tiempo Completo', 'area_certificacion' => 'Administración',
            'created_at' => now(), 'updated_at' => now()]);
        $this->formacion = DB::table('formaciones_academicas_solicitud')->insertGetId(['solicitud_id' => $s, 'nivel_origen' => 'Licenciatura',
            'titulo_origen' => 'Administración', 'nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro', 'titulo_ultimo_grado_otro' => 'Economía']);
    }

    private function actualizar(array $v): void
    {
        DB::table('formaciones_academicas_solicitud')->where('id', $this->formacion)->update($v);
    }

    private function rechaza(array $v, string $caso): void
    {
        try {
            $this->actualizar($v);
            $this->fail("Debió rechazarse: {$caso}");
        } catch (QueryException $e) {
            $this->assertStringContainsString('fas_titulo_especifico_coherente_ck', $e->getMessage(), $caso);
        }
    }

    public function test_columnas_aditivas_check_de_coherencia_y_reversion_segura(): void
    {
        $antes = (array) DB::table('formaciones_academicas_solicitud')->find($this->formacion);
        $m = require base_path(self::MIGRACION);
        $m->up();
        CatalogoPosgrados::olvidarPersistencia();
        $this->assertTrue(CatalogoPosgrados::persistenciaDisponible());

        $cols = collect(DB::select("select column_name, data_type, character_maximum_length as l, is_nullable from information_schema.columns
            where table_name = 'formaciones_academicas_solicitud' and column_name in ('titulo_ultimo_grado_especifico','origen_titulo_ultimo_grado','clave_posgrado_catalogo','version_catalogo_posgrados')"))
            ->keyBy('column_name');
        $this->assertSame(['titulo_ultimo_grado_especifico' => 300, 'origen_titulo_ultimo_grado' => 20, 'clave_posgrado_catalogo' => 40, 'version_catalogo_posgrados' => 40],
            collect(CatalogoPosgrados::COLUMNAS)->mapWithKeys(fn ($c) => [$c => (int) $cols[$c]->l])->all());
        $this->assertTrue($cols->every(fn ($c) => $c->is_nullable === 'YES' && $c->data_type === 'character varying'));

        // Los registros existentes no cambian (columnas nulas).
        $despues = (array) DB::table('formaciones_academicas_solicitud')->find($this->formacion);
        foreach ($antes as $k => $v) {
            $this->assertSame($v, $despues[$k], $k);
        }
        foreach (CatalogoPosgrados::COLUMNAS as $c) {
            $this->assertNull($despues[$c]);
        }

        // Combinaciones válidas.
        $this->actualizar(['titulo_ultimo_grado_especifico' => 'Maestría ficticia', 'origen_titulo_ultimo_grado' => 'catalogo',
            'clave_posgrado_catalogo' => 'MAE-00000000000A', 'version_catalogo_posgrados' => 'FICTICIO-1']);
        $this->actualizar(['titulo_ultimo_grado_especifico' => 'Título manual', 'origen_titulo_ultimo_grado' => 'manual',
            'clave_posgrado_catalogo' => null, 'version_catalogo_posgrados' => null]);

        // Combinaciones incoherentes: las rechaza la base aunque fallara la aplicación.
        $this->rechaza(['origen_titulo_ultimo_grado' => 'otro'], 'origen desconocido');
        $this->rechaza(['origen_titulo_ultimo_grado' => 'manual', 'clave_posgrado_catalogo' => 'MAE-00000000000A'], 'manual con clave');
        $this->rechaza(['origen_titulo_ultimo_grado' => 'manual', 'version_catalogo_posgrados' => 'X'], 'manual con versión');
        $this->rechaza(['origen_titulo_ultimo_grado' => 'catalogo'], 'catálogo sin clave ni versión');
        $this->rechaza(['origen_titulo_ultimo_grado' => 'catalogo', 'clave_posgrado_catalogo' => 'XYZ', 'version_catalogo_posgrados' => 'V'], 'clave con formato inválido');
        $this->rechaza(['titulo_ultimo_grado_especifico' => '   '], 'título vacío');
        $this->rechaza(['titulo_ultimo_grado_especifico' => null], 'origen sin título');
        $this->rechaza(['origen_titulo_ultimo_grado' => null], 'título sin origen');

        // down() se niega con datos; con todo nulo, revierte por completo.
        try {
            $m->down();
            $this->fail('down() debió negarse');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('se niega', $e->getMessage());
        }
        $this->assertSame('Título manual', DB::table('formaciones_academicas_solicitud')->where('id', $this->formacion)->value('titulo_ultimo_grado_especifico'));
        $this->actualizar(array_fill_keys(CatalogoPosgrados::COLUMNAS, null));
        $m->down();
        CatalogoPosgrados::olvidarPersistencia();
        $this->assertFalse(CatalogoPosgrados::persistenciaDisponible());
        $this->assertSame(0, (int) DB::selectOne("select count(*) as n from pg_constraint where conname = 'fas_titulo_especifico_coherente_ck'")->n);
        $this->assertSame('Economía', DB::table('formaciones_academicas_solicitud')->where('id', $this->formacion)->value('titulo_ultimo_grado_otro'));
    }
}
