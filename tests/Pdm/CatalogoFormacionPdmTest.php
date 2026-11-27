<?php

namespace Tests\Pdm;

use App\Models\User;
use App\Services\Catalogos\CatalogoLicenciaturas;
use App\Services\Catalogos\CatalogoPosgrados;
use App\Services\Formacion\RequisitosFormacion;
use App\Services\Pdm\Compat\FormacionCatalogoPdm;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

class CatalogoFormacionPdmTest extends PdmTestCase
{
    use DatosPublicFicticios;
    use FlujosAnfeca;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            '2026_11_27_000000_add_titulo_especifico_to_formaciones_academicas_solicitud.php',
            '2026_11_28_000000_add_titulo_origen_especifico_y_declaracion_posgrado.php',
        ] as $archivo) {
            $migracion = require database_path("migrations_propuestas/{$archivo}");
            $migracion->up();
        }

        CatalogoLicenciaturas::olvidarPersistencia();
        CatalogoPosgrados::olvidarPersistencia();
        RequisitosFormacion::olvidarPersistencia();

        config([
            'anfeca_catalogos.licenciaturas.activo' => true,
            'anfeca_catalogos.licenciaturas.ruta' => base_path('tests/fixtures/catalogos/licenciaturas_ficticio.json'),
            'anfeca_catalogos.licenciaturas.version' => 'FICTICIO-LIC-1',
            'anfeca_catalogos.posgrados.activo' => true,
            'anfeca_catalogos.posgrados.ruta' => base_path('tests/fixtures/catalogos/posgrados_ficticio.json'),
            'anfeca_catalogos.posgrados.version' => 'FICTICIO-1',
            'anfeca_catalogos.declaracion_posgrado.activo' => true,
        ]);
    }

    protected function tearDown(): void
    {
        CatalogoLicenciaturas::olvidarPersistencia();
        CatalogoPosgrados::olvidarPersistencia();
        RequisitosFormacion::olvidarPersistencia();

        parent::tearDown();
    }

    public static function modos(): array
    {
        return ['espejo' => ['espejo'], 'principal' => ['principal'], 'exclusivo y retorno' => ['exclusivo']];
    }

    #[DataProvider('modos')]
    public function test_captura_catalogo_y_titulo_manual_extenso_en_cada_modo(string $modo): void
    {
        $this->escenarioMigrado();
        if ($modo === 'exclusivo') {
            $this->activar('principal');
        }
        $this->activar($modo);

        $correo = 'catalogo-pdm@example.test';
        $this->registrar('FICT800101HDFRRN21', $correo)->assertRedirect(route('registro.certificacion'));
        $usuario = User::where('email', $correo)->firstOrFail();
        $this->actingAs($usuario)->post('/mis-datos/autorizar', ['confirmacion_datos' => '1'])
            ->assertRedirect(route('registro.certificacion'));
        $this->actingAs($usuario)->post('/registro/certificacion', [
            'certificacion' => 'ANFECA', 'tipo_personal' => self::TC, 'area_certificacion' => 'Administración',
        ])->assertRedirect(route('registro.formacion'));

        $tituloLargo = str_repeat('Formación avanzada ', 9).'aplicada';
        $this->post('/registro/formacion-academica', [
            'nivel_origen' => 'Licenciatura', 'modo_titulo_origen' => 'catalogo', 'clave_licenciatura_catalogo' => 'LIC-00000000000B',
            'institucion_afiliada_id' => 1, 'cuenta_con_posgrado' => '1',
            'nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro',
            'modo_titulo_ultimo_grado' => 'manual', 'titulo_ultimo_grado_manual' => $tituloLargo,
            'titulo_origen_archivo' => $this->pdf('to.pdf'), 'cedula_origen_archivo' => $this->pdf('co.pdf'),
            'titulo_ultimo_grado_archivo' => $this->pdf('tu.pdf'), 'cedula_ultimo_grado_archivo' => $this->pdf('cu.pdf'),
        ])->assertSessionHasNoErrors();

        $pdm = $this->pdm('formacion_solicitud')->first();
        $this->assertSame('LIC-00000000000B', $pdm->clave_licenciatura_catalogo);
        $this->assertSame('FICTICIO-LIC-1', $pdm->version_catalogo_licenciaturas);
        $this->assertSame($tituloLargo, $pdm->titulo_ultimo_grado_especifico);
        $this->assertTrue($pdm->cuenta_con_posgrado);
        $this->assertSame($tituloLargo, DB::table('anfeca_compat.formaciones_academicas_solicitud')->first()->titulo_ultimo_grado_otro);

        $this->post('/registro/formacion-academica', [
            'nivel_origen' => 'Licenciatura', 'modo_titulo_origen' => 'catalogo', 'clave_licenciatura_catalogo' => 'LIC-00000000000B',
            'institucion_afiliada_id' => 1, 'cuenta_con_posgrado' => '1',
            'nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Administración',
            'modo_titulo_ultimo_grado' => 'catalogo', 'clave_posgrado_catalogo' => 'MAE-00000000000A',
        ])->assertSessionHasNoErrors();
        $this->assertSame('MAE-00000000000A', $this->pdm('formacion_solicitud')->first()->clave_posgrado_catalogo);
        $this->assertSame('FICTICIO-1', $this->pdm('formacion_solicitud')->first()->version_catalogo_posgrados);

        if ($modo === 'exclusivo') {
            $this->assertSame(0, DB::table('public.formaciones_academicas_solicitud')->count());
            $this->artisan('anfeca:pdm-exportar-public', ['--confirmar' => true])->assertSuccessful();
            $this->activar('apagado');
        }

        $columnas = array_keys(FormacionCatalogoPdm::COLUMNAS);
        $this->assertEquals((array) DB::table('public.formaciones_academicas_solicitud')->first($columnas), (array) $this->pdm('formacion_solicitud')->first($columnas));
        $this->assertSame([], $this->conciliacion());
        $this->assertSame([], $this->equivalencia());
    }

    public function test_migracion_conserva_declaracion_falsa_y_procedencia(): void
    {
        $this->crearPublicFicticio();
        config(['anfeca_pdm_decisiones' => $this->decisionesCompletas()]);
        DB::table('public.formaciones_academicas_solicitud')->where('id', 1)->update([
            'titulo_origen_especifico' => 'Licenciatura Ficticia en Contaduría Pública',
            'origen_titulo_origen' => 'catalogo', 'clave_licenciatura_catalogo' => 'LIC-00000000000A',
            'version_catalogo_licenciaturas' => 'FICTICIO-LIC-1', 'cuenta_con_posgrado' => false,
        ]);

        DB::statement("select setval(pg_get_serial_sequence('anfeca_modelo.rol', 'rol_id_rol'), 1, false)");
        $this->artisan('anfeca:pdm-migrar', ['--ejecutar' => true, '--confirmar' => true])->assertSuccessful();
        $this->assertFalse($this->pdm('formacion_solicitud')->where('fosc_id_formacion', 1)->value('cuenta_con_posgrado'));
        $this->assertSame([], $this->conciliacion());
        $this->assertSame(1, $this->pdm('rol')->where('rol_nombre', 'administrador')->value('rol_id_rol'));
        $this->assertGreaterThan(1, $this->pdm('rol')->where('rol_nombre', 'solicitante')->value('rol_id_rol'));
        $this->assertSame([], array_values(array_filter($this->equivalencia(),
            fn (array $diferencia) => $diferencia['tabla'] === 'formaciones_academicas_solicitud')));
    }

    public function test_actualizar_una_instalacion_f4_recupera_metadatos_sin_rehacer_el_expediente(): void
    {
        $this->crearPublicFicticio();
        config(['anfeca_pdm_decisiones' => $this->decisionesCompletas()]);
        $this->artisan('anfeca:pdm-migrar', ['--ejecutar' => true, '--confirmar' => true])->assertSuccessful();

        $formacionAntes = $this->pdm('formacion_solicitud')->where('fosc_id_formacion', 1)
            ->first(['fosc_id_solicitud', 'fosc_id_institucion', 'fosc_creado_en', 'fosc_actualizado_en']);
        DB::statement('drop view anfeca_compat.formaciones_academicas_solicitud');
        Schema::table('anfeca_modelo.formacion_solicitud',
            fn (Blueprint $tabla) => $tabla->dropColumn(array_keys(FormacionCatalogoPdm::COLUMNAS)));
        FormacionCatalogoPdm::actualizarVista(false);

        DB::table('public.formaciones_academicas_solicitud')->where('id', 1)->update([
            'titulo_origen_especifico' => 'Nombre exacto capturado antes de actualizar',
            'origen_titulo_origen' => 'manual',
            'cuenta_con_posgrado' => false,
        ]);
        $migracion = require database_path('migrations_pdm/2026_11_28_000001_integrar_catalogos_formacion_pdm.php');
        $migracion->up();

        $formacion = $this->pdm('formacion_solicitud')->where('fosc_id_formacion', 1)->first();
        $this->assertSame('Nombre exacto capturado antes de actualizar', $formacion->titulo_origen_especifico);
        $this->assertFalse($formacion->cuenta_con_posgrado);
        $this->assertEquals((array) $formacionAntes, array_intersect_key((array) $formacion, (array) $formacionAntes));
        $this->assertSame([], $this->conciliacion());
    }
}
