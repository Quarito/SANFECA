<?php

namespace Tests\Pdm;

use App\Console\Commands\AnfecaPdmModo;
use App\Models\User;
use App\Services\Pdm\ModoPdm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * F5 · Modo exclusivo: el PDM es la ÚNICA fuente. public queda congelado (huella idéntica tras
 * cada paso) y la aplicación funciona completa. Reversión: exportar a public y volver a
 * principal o apagado, con conciliación y equivalencia en 0.
 */
class ExclusivoPdmTest extends PdmTestCase
{
    use FlujosAnfeca;

    private array $huella;

    protected function setUp(): void
    {
        parent::setUp();
        $this->escenarioMigrado();
        $this->activar('principal');
        $this->activar('exclusivo');
        $this->assertSame('exclusivo', ModoPdm::efectivo());
        $this->huella = AnfecaPdmModo::huellaDominio();
        $this->assertSame(json_encode($this->huella), ModoPdm::activacion()->ic_huella_public, 'huella de public registrada al activar');
    }

    private function publicCongelado(): callable
    {
        return fn (string $paso) => $this->assertSame($this->huella, AnfecaPdmModo::huellaDominio(), "public no cambia tras «{$paso}».");
    }

    public function test_flujo_inicial_completo_solo_en_el_pdm(): void
    {
        [$user, $personaId, $solicitudId] = $this->flujoInicialCompleto($this->publicCongelado());

        $this->assertSame(0, DB::table('public.datos_solicitantes')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('public.solicitudes_certificacion')->count());
        $this->assertSame(1, $this->pdm('persona')->where('pers_id_persona', $personaId)->count());
        $this->assertSame(['BO', 'FA', 'CA', 'EN'], $this->eventos($solicitudId));
    }

    public function test_refrendo_articulo_14_solo_en_el_pdm(): void
    {
        $this->flujoRefrendo($this->publicCongelado());
        $this->assertSame(0, DB::table('public.datos_refrendo')->count());
    }

    public function test_restablecer_contrasena_en_exclusivo(): void
    {
        $this->registrar('FICT800101HDFRRN06', 'clave@example.test')->assertRedirect();
        $user = User::where('email', 'clave@example.test')->firstOrFail();
        auth()->logout();

        // La recuperación busca por CURP y correo en datos_solicitantes: aquí los lee de la vista (PDM).
        $token = Password::broker()->createToken($user);
        $this->post('/restablecer', [
            'token' => $token, 'email' => 'clave@example.test',
            'password' => 'NuevaContrasenaFicticia2', 'password_confirmation' => 'NuevaContrasenaFicticia2',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NuevaContrasenaFicticia2', $this->pdm('usuario')->where('usua_id_usuario', $user->id)->value('usua_contrasenia')));
        ($this->publicCongelado())('restablecer');
    }

    public function test_no_se_vuelve_a_un_public_desactualizado_sin_exportar(): void
    {
        $this->registrar('FICT800101HDFRRN13', 'sinexportar@example.test')->assertRedirect();

        $this->artisan('anfeca:pdm-modo', ['modo' => 'apagado'])->expectsOutputToContain('anfeca:pdm-exportar-public')->assertExitCode(1);
        config(['anfeca_pdm.modo' => 'espejo']);
        $this->artisan('anfeca:pdm-modo', ['modo' => 'espejo', '--confirmar' => true])->assertExitCode(1);
        config(['anfeca_pdm.modo' => 'principal']);
        $this->artisan('anfeca:pdm-modo', ['modo' => 'principal', '--confirmar' => true])->assertExitCode(1);

        $this->assertSame('exclusivo', ModoPdm::activacion()->ic_modo);
    }

    public function test_exportar_public_y_revertir_sin_perder_informacion(): void
    {
        [$user, , $solicitudId] = $this->flujoInicialCompleto(fn () => null);
        $refrendo = $this->flujoRefrendo(fn () => null);

        $this->artisan('anfeca:pdm-exportar-public', ['--confirmar' => true])->assertExitCode(0);

        // public tiene ahora lo capturado solo en el PDM, incluido el historial documental.
        $this->assertSame('enviado', DB::table('public.solicitudes_certificacion')->where('id', $solicitudId)->value('estado'));
        $this->assertSame('Tlaxcala', DB::table('public.datos_solicitantes')->where('user_id', $user->id)->value('entidad_federativa'));
        $this->assertSame(1, DB::table('public.historial_documentos_academicos')->where('solicitud_id', $solicitudId)->count());
        $this->assertSame(1, DB::table('public.datos_refrendo')->where('solicitud_id', $refrendo)->count());
        $this->assertSame([], $this->conciliacion());
        $this->assertSame([], $this->equivalencia());

        // Exportar de nuevo no cambia nada (idempotente).
        $antes = AnfecaPdmModo::huellaDominio();
        $this->artisan('anfeca:pdm-exportar-public', ['--confirmar' => true])->assertExitCode(0);
        $this->assertSame($antes, AnfecaPdmModo::huellaDominio());

        // Vuelta a principal (verificada) y después a apagado: la aplicación sigue sobre public.
        $this->activar('principal');
        $this->assertSame('principal', ModoPdm::efectivo());
        $this->artisan('anfeca:pdm-modo', ['modo' => 'apagado'])->assertExitCode(0);
        config(['anfeca_pdm.modo' => 'apagado']);
        $this->actingAs($user)->get("/registro/acuse/{$solicitudId}")->assertOk();
    }

    public function test_exportar_no_reescribe_datos_que_nadie_modifico(): void
    {
        // Persona registrada en principal: public conserva lo capturado tal cual («CDMX»).
        $this->activar('principal');
        $this->registrar('FICT800101HDFRRN14', 'original@example.test')->assertRedirect();
        $user = User::where('email', 'original@example.test')->firstOrFail();
        $id = (int) DB::table('public.datos_solicitantes')->where('user_id', $user->id)->value('id');
        $this->assertSame('CDMX', DB::table('public.datos_solicitantes')->where('id', $id)->value('entidad_federativa'));
        $this->activar('exclusivo');

        // En exclusivo solo cambia su teléfono (el formulario reenvía el domicilio igual).
        $this->actingAs($user)->put('/mis-datos', [
            'calle' => 'Calle Ficticia', 'numero_exterior' => '10', 'codigo_postal' => '01000', 'entidad_federativa' => 'CDMX',
            'colonia' => 'Centro', 'municipio' => 'Cuauhtémoc', 'telefono_celular' => '5599999999',
        ])->assertRedirect(route('mis-datos.edit'));
        auth()->logout();
        $this->registrar('FICT800101HDFRRN15', 'nueva@example.test')->assertRedirect();

        $this->artisan('anfeca:pdm-exportar-public', ['--confirmar' => true])->assertExitCode(0);

        $fila = DB::table('public.datos_solicitantes')->where('id', $id)->first();
        $this->assertSame('CDMX', $fila->entidad_federativa, 'lo que nadie modificó no se reescribe con la forma normalizada');
        $this->assertSame('5599999999', $fila->telefono_celular, 'el cambio real sí se exporta');
        $this->assertSame('Ciudad de México', DB::table('public.datos_solicitantes')->where('curp', 'FICT800101HDFRRN15')->value('entidad_federativa'),
            'una persona capturada solo en el PDM se exporta completa (forma oficial)');
        $this->assertSame([], $this->conciliacion());
        $this->assertSame([], $this->equivalencia());
    }

    public function test_exportar_solo_en_exclusivo_y_con_autorizacion_en_la_base_en_uso(): void
    {
        config(['anfeca_modelo.bd_permitida' => DB::connection()->getDatabaseName()]);
        $this->artisan('anfeca:pdm-exportar-public', ['--confirmar' => true])
            ->expectsOutputToContain('ANFECA_PDM_AUTORIZAR_EXPORTACION=si')->assertExitCode(1);

        config(['anfeca_modelo.bd_permitida' => 'otra_base']);
        $this->activar('principal'); // public sin cambios: se permite
        $this->artisan('anfeca:pdm-exportar-public', ['--confirmar' => true])
            ->expectsOutputToContain('solo aplica en modo exclusivo')->assertExitCode(1);
    }

    public function test_migrar_vaciar_y_reiniciar_demo_se_niegan(): void
    {
        $this->artisan('anfeca:pdm-vaciar', ['--confirmar' => true])->assertExitCode(1);
        $this->artisan('anfeca:reiniciar-demo', ['--ejecutar' => true])->assertExitCode(1);
        $this->assertSame(1, $this->pdm('historico_certificado_importacion')->count());
    }
}
