<?php

namespace Tests\Pdm;

use App\Models\User;
use App\Services\Pdm\ModoPdm;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * F4 · Modo principal: ANFECA lee y escribe en anfeca_modelo a través de las vistas anfeca_compat;
 * los disparadores replican en public. Tras cada paso: conciliación PDM↔public = 0 (las reglas SQL
 * coinciden con las de F2) y equivalencia vistas↔public = 0 (Laravel lee lo mismo).
 */
class PrincipalPdmTest extends PdmTestCase
{
    use FlujosAnfeca;

    protected function setUp(): void
    {
        parent::setUp();
        $this->escenarioMigrado();
        $this->activar('principal');
        $this->assertSame('principal', ModoPdm::efectivo());
    }

    private function verificarCoincidencia(): callable
    {
        return function (string $paso) {
            $this->assertSame([], $this->conciliacion(), "Conciliación tras «{$paso}».");
            $this->assertSame([], $this->equivalencia(), "Equivalencia tras «{$paso}».");
        };
    }

    public function test_flujo_inicial_completo_en_principal(): void
    {
        [$user, $personaId, $solicitudId] = $this->flujoInicialCompleto($this->verificarCoincidencia());

        // public quedó como réplica completa (reversión inmediata posible).
        $this->assertSame('enviado', DB::table('public.solicitudes_certificacion')->where('id', $solicitudId)->value('estado'));
        $this->assertSame(1, DB::table('public.historial_documentos_academicos')->where('solicitud_id', $solicitudId)->count());
        $this->assertSame($personaId, (int) DB::table('public.datos_solicitantes')->where('user_id', $user->id)->value('id'));

        $coordinador = User::factory()->create(['rol' => 'coordinador_nacional']);
        $this->actingAs($coordinador)->get("/coordinacion/solicitudes/{$solicitudId}")
            ->assertOk()->assertSee('Institución Ficticia');
        $this->get("/coordinacion/solicitudes/{$solicitudId}/documentos/titulo_origen_archivo")->assertOk();
    }

    public function test_refrendo_articulo_14_en_principal(): void
    {
        $this->flujoRefrendo($this->verificarCoincidencia());
    }

    public function test_dato_no_registrable_se_rechaza_en_el_campo_sin_escribir_nada(): void
    {
        $this->registrar('FICT800101HDFRRN02', 'monterrey@example.test', ['entidad_federativa' => 'Monterrey'])
            ->assertSessionHasErrors('entidad_federativa');
        $this->assertDatabaseMissing('users', ['email' => 'monterrey@example.test']);
        $this->assertSame(0, $this->pdm('persona')->where('pers_curp', 'FICT800101HDFRRN02')->count());

        $this->registrar('FICT800101HDFRRN03', 'largo@example.test', ['numero_exterior' => 'Manzana 12'])
            ->assertSessionHasErrors('numero_exterior');
        $this->assertDatabaseMissing('users', ['email' => 'largo@example.test']);

        $this->assertSame([], $this->conciliacion());
        $this->assertSame([], $this->equivalencia());
    }

    public function test_restablecer_contrasena_sincroniza_el_usuario_tecnico(): void
    {
        $this->registrar('FICT800101HDFRRN06', 'clave@example.test')->assertRedirect();
        $user = User::where('email', 'clave@example.test')->firstOrFail();
        auth()->logout();

        $token = Password::broker()->createToken($user);
        $this->post('/restablecer', [
            'token' => $token, 'email' => 'clave@example.test',
            'password' => 'NuevaContrasenaFicticia2', 'password_confirmation' => 'NuevaContrasenaFicticia2',
        ])->assertRedirect(route('login'));

        $hash = $this->pdm('usuario')->where('usua_id_usuario', $user->id)->value('usua_contrasenia');
        $this->assertSame($user->fresh()->password, $hash);
        $this->assertTrue(Hash::check('NuevaContrasenaFicticia2', $hash));
        $this->assertSame([], $this->conciliacion());
    }

    public function test_la_guardia_impide_escribir_directo_en_public(): void
    {
        $this->registrar('FICT800101HDFRRN10', 'guardia@example.test')->assertRedirect();
        $id = DB::table('public.datos_solicitantes')->where('curp', 'FICT800101HDFRRN10')->value('id');

        try {
            DB::transaction(fn () => DB::table('public.datos_solicitantes')->where('id', $id)->update(['calle' => 'Escritura directa']));
            $this->fail('La guardia debió rechazar la escritura directa en public.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('ANFECA_VALIDACION|pdm|', $e->getMessage());
        }
        $this->assertSame([], $this->conciliacion());
    }

    public function test_apagar_es_inmediato_y_public_conserva_todo(): void
    {
        [$user, , $solicitudId] = $this->flujoInicialCompleto(fn () => null, 'FICT800101HDFRRN11', 'apagar@example.test');

        $this->artisan('anfeca:pdm-modo', ['modo' => 'apagado'])->assertExitCode(0);
        config(['anfeca_pdm.modo' => 'apagado']);
        DB::statement('set search_path to public');
        $this->assertSame('apagado', ModoPdm::efectivo());

        // La aplicación sigue sobre public, con el trámite completo.
        $this->actingAs($user)->get("/registro/acuse/{$solicitudId}")->assertOk();
        $this->assertSame('enviado', DB::table('solicitudes_certificacion')->where('id', $solicitudId)->value('estado'));
    }

    public function test_activacion_protegida(): void
    {
        $this->registrar('FICT800101HDFRRN12', 'protegida@example.test')->assertRedirect();
        $this->artisan('anfeca:pdm-modo', ['modo' => 'apagado'])->assertExitCode(0);

        // Sin la primera llave.
        config(['anfeca_pdm.modo' => 'apagado']);
        $this->artisan('anfeca:pdm-modo', ['modo' => 'principal', '--confirmar' => true])
            ->expectsOutputToContain('ANFECA_PDM_MODO=principal')->assertExitCode(1);

        // Base en uso sin autorización explícita.
        config(['anfeca_pdm.modo' => 'principal', 'anfeca_modelo.bd_permitida' => DB::connection()->getDatabaseName()]);
        $this->artisan('anfeca:pdm-modo', ['modo' => 'principal', '--confirmar' => true])
            ->expectsOutputToContain('ANFECA_PDM_AUTORIZAR_PRINCIPAL=si')->assertExitCode(1);
        config(['anfeca_modelo.bd_permitida' => 'otra_base']);

        // PDM desfasado respecto a public (cambio directo en el PDM).
        $this->pdm('persona')->where('pers_curp', 'FICT800101HDFRRN12')->update(['pers_nombre' => 'Desfasado']);
        $this->artisan('anfeca:pdm-modo', ['modo' => 'principal', '--confirmar' => true])
            ->expectsOutputToContain('diferencia(s)')->assertExitCode(1);

        // Exclusivo solo desde principal.
        config(['anfeca_pdm.modo' => 'exclusivo']);
        $this->artisan('anfeca:pdm-modo', ['modo' => 'exclusivo', '--confirmar' => true])
            ->expectsOutputToContain('solo se activa desde principal')->assertExitCode(1);

        $this->assertSame('apagado', ModoPdm::efectivo());
    }

    public function test_dos_llaves_sin_activacion_o_sin_capa_opera_apagado(): void
    {
        // La activación registrada es principal, pero .env dice espejo: no coincide → apagado.
        config(['anfeca_pdm.modo' => 'espejo']);
        $this->assertSame('apagado', ModoPdm::efectivo());

        config(['anfeca_pdm.modo' => 'principal']);
        $this->assertSame('principal', ModoPdm::efectivo());
    }

    public function test_las_vistas_de_catalogo_son_de_solo_lectura(): void
    {
        DB::statement('set search_path to anfeca_compat, public');
        try {
            DB::transaction(fn () => DB::table('instituciones_afiliadas')->where('id', 1)->update(['nombre' => 'Cambio']));
            $this->fail('El catálogo de instituciones no debe modificarse desde ANFECA.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('ANFECA_VALIDACION|pdm|', $e->getMessage());
        }
        $this->assertSame('Institución Ficticia', DB::table('instituciones_afiliadas')->where('id', 1)->value('nombre'));
    }
}
