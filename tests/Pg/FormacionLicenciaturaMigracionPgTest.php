<?php

namespace Tests\Pg;

use App\Models\User;
use App\Services\Catalogos\CatalogoLicenciaturas;
use App\Services\Formacion\RequisitosFormacion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * Migración PROPUESTA del catálogo de licenciaturas y la declaración de posgrado en PostgreSQL 18 desechable.
 * Solo bd_anfeca_desechable_pruebas_pg; datos ficticios. No se aplica en 8086.
 */
class FormacionLicenciaturaMigracionPgTest extends TestCase
{
    private const MIG_POS = 'database/migrations_propuestas/2026_11_27_000000_add_titulo_especifico_to_formaciones_academicas_solicitud.php';
    private const MIG_LIC = 'database/migrations_propuestas/2026_11_28_000000_add_titulo_origen_especifico_y_declaracion_posgrado.php';
    private int $id;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql' || DB::connection()->getDatabaseName() !== 'bd_anfeca_desechable_pruebas_pg') {
            throw new RuntimeException('SEGURIDAD: solo en bd_anfeca_desechable_pruebas_pg.');
        }
        Artisan::call('migrate:fresh', ['--force' => true]);
        (require base_path(self::MIG_POS))->up();
        CatalogoLicenciaturas::olvidarPersistencia();
        RequisitosFormacion::olvidarPersistencia();
        $u = User::create(['name' => 'Ficticio', 'email' => 'lic.pg@example.test', 'password' => Hash::make('ContrasenaFicticia1')]);
        $s = DB::table('solicitudes_certificacion')->insertGetId(['user_id' => $u->id, 'tipo_tramite' => 'inicial', 'certificacion' => 'ANFECA',
            'estado' => 'borrador', 'tipo_personal' => 'Profesoras y profesores de Tiempo Completo', 'area_certificacion' => 'Administración',
            'created_at' => now(), 'updated_at' => now()]);
        // Registro histórico: último grado «Licenciatura» del formulario anterior (debe seguir siendo válido con NULL).
        $this->id = DB::table('formaciones_academicas_solicitud')->insertGetId(['solicitud_id' => $s, 'nivel_origen' => 'Licenciatura',
            'titulo_origen' => 'Administración', 'nivel_ultimo_grado' => 'Licenciatura', 'titulo_ultimo_grado' => 'Administración']);
    }

    private function act(array $v): void
    {
        DB::table('formaciones_academicas_solicitud')->where('id', $this->id)->update($v);
    }

    private function rechaza(array $v, string $restriccion, string $caso): void
    {
        try {
            $this->act($v);
            $this->fail("Debió rechazarse: {$caso}");
        } catch (QueryException $e) {
            $this->assertStringContainsString($restriccion, $e->getMessage(), $caso);
        }
    }

    public function test_columnas_check_de_coherencia_declaracion_y_reversion_segura(): void
    {
        $antes = (array) DB::table('formaciones_academicas_solicitud')->find($this->id);
        $m = require base_path(self::MIG_LIC);
        $m->up();
        CatalogoLicenciaturas::olvidarPersistencia();
        RequisitosFormacion::olvidarPersistencia();
        $this->assertTrue(CatalogoLicenciaturas::persistenciaDisponible() && RequisitosFormacion::persistenciaDisponible());

        $cols = collect(DB::select("select column_name, data_type, character_maximum_length as l, is_nullable from information_schema.columns
            where table_name='formaciones_academicas_solicitud' and column_name in
            ('titulo_origen_especifico','origen_titulo_origen','clave_licenciatura_catalogo','version_catalogo_licenciaturas','cuenta_con_posgrado')"))->keyBy('column_name');
        $esperado = ['titulo_origen_especifico' => 'character varying:300', 'origen_titulo_origen' => 'character varying:20',
            'clave_licenciatura_catalogo' => 'character varying:40', 'version_catalogo_licenciaturas' => 'character varying:60', 'cuenta_con_posgrado' => 'boolean:'];
        $this->assertSame($esperado, collect(array_keys($esperado))->mapWithKeys(fn ($c) => [$c => $cols[$c]->data_type.':'.$cols[$c]->l])->all());
        $this->assertTrue($cols->every(fn ($c) => $c->is_nullable === 'YES'));
        $despues = (array) DB::table('formaciones_academicas_solicitud')->find($this->id);
        foreach ($antes as $k => $v) {
            $this->assertSame($v, $despues[$k], "registro existente sin cambios: {$k}");
        }
        $this->assertNull($despues['cuenta_con_posgrado'], 'Sin actualización retrospectiva.');

        // Escenario 1 (solo licenciatura) y escenario 2 (con posgrado): válidos.
        $this->act(['titulo_origen_especifico' => 'Ingeniería en Computación', 'origen_titulo_origen' => 'catalogo',
            'clave_licenciatura_catalogo' => 'LIC-0123456789AB', 'version_catalogo_licenciaturas' => 'ANUIES-2000-2026-LIC-PRELIMINAR',
            'cuenta_con_posgrado' => false, 'nivel_ultimo_grado' => null, 'titulo_ultimo_grado' => null]);
        $this->act(['cuenta_con_posgrado' => true, 'nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Administración']);
        $this->act(['titulo_origen_especifico' => 'Licenciatura antigua', 'origen_titulo_origen' => 'manual', 'clave_licenciatura_catalogo' => null,
            'version_catalogo_licenciaturas' => null]);
        // Con FALSE se conservan datos de posgrado anteriores (no vigentes).
        $this->act(['cuenta_con_posgrado' => false]);

        $ck = 'fas_titulo_origen_coherente_ck';
        $this->rechaza(['origen_titulo_origen' => 'otro'], $ck, 'origen desconocido');
        $this->rechaza(['clave_licenciatura_catalogo' => 'LIC-0123456789AB'], $ck, 'manual con clave');
        $this->rechaza(['origen_titulo_origen' => 'catalogo'], $ck, 'catálogo sin clave');
        $this->rechaza(['origen_titulo_origen' => 'catalogo', 'clave_licenciatura_catalogo' => 'MAE-0123456789AB', 'version_catalogo_licenciaturas' => 'V'], $ck, 'clave de posgrado');
        $this->rechaza(['titulo_origen_especifico' => '  '], $ck, 'título en blanco');
        $this->rechaza(['origen_titulo_origen' => null], $ck, 'título sin origen');
        $dp = 'fas_declaracion_posgrado_ck';
        $this->rechaza(['cuenta_con_posgrado' => true, 'nivel_ultimo_grado' => 'Licenciatura'], $dp, 'segunda licenciatura como posgrado');
        $this->rechaza(['cuenta_con_posgrado' => true, 'nivel_ultimo_grado' => null], $dp, 'posgrado sin nivel');
        $this->rechaza(['cuenta_con_posgrado' => true, 'nivel_ultimo_grado' => 'Doctorado', 'titulo_ultimo_grado' => null], $dp, 'posgrado sin área');

        try {
            $m->down();
            $this->fail('down() debió negarse');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('se niega', $e->getMessage());
        }
        $this->act(['titulo_origen_especifico' => null, 'origen_titulo_origen' => null, 'cuenta_con_posgrado' => null]);
        $m->down();
        $this->assertSame(0, (int) DB::selectOne("select count(*) n from pg_constraint where conname in ('fas_titulo_origen_coherente_ck','fas_declaracion_posgrado_ck')")->n);
        $this->assertSame(0, (int) DB::selectOne("select count(*) n from information_schema.columns where table_name='formaciones_academicas_solicitud' and column_name='cuenta_con_posgrado'")->n);
        $this->assertSame(1, (int) DB::selectOne("select count(*) n from information_schema.columns where table_name='formaciones_academicas_solicitud' and column_name='clave_posgrado_catalogo'")->n, 'Las columnas de posgrado se conservan.');
    }
}
