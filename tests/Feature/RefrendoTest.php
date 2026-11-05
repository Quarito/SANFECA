<?php

namespace Tests\Feature;

use App\Http\Controllers\RefrendoController;
use App\Models\CertificadoHistorico;
use App\Models\DatosSolicitante;
use App\Models\InstitucionAfiliada;
use App\Models\SeleccionCertificacion;
use App\Models\SolicitudRefrendo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RefrendoTest extends TestCase
{
    use RefreshDatabase;

    private function preparar(): array
    {
        $user = User::factory()->create();
        DatosSolicitante::create(['user_id' => $user->id, 'nombres' => 'Diego', 'apellido_paterno' => 'Sánchez',
            'apellido_materno' => 'Martínez', 'curp' => 'TEST990101HDFRXX00', 'rfc' => 'TEST990101XXX',
            'cedula_profesional' => '123', 'calle' => 'Universidad', 'numero_exterior' => '1', 'codigo_postal' => '04510',
            'entidad_federativa' => 'CDMX', 'colonia' => 'CU', 'municipio' => 'Coyoacán',
            'correo_electronico' => $user->email, 'telefono_celular' => '5512345678', 'datos_autorizados' => true]);
        SeleccionCertificacion::create(['user_id' => $user->id, 'certificacion' => 'ANFECA', 'tipo_personal' => 'Tiempo Completo', 'area_certificacion' => 'Informática']);
        $historico = new CertificadoHistorico;
        $historico->forceFill(['user_id' => $user->id, 'certificacion' => 'ANFECA', 'numero_certificado' => '5000',
            'estado_vinculacion' => 'verificado', 'primera_vez' => '2020', 'refrendo_1' => '2023'])->save();
        $this->actingAs($user);

        return [$user, $historico];
    }

    public function test_ordinario_completo_y_documentos_privados(): void
    {
        Storage::fake('local');
        [$user, $historico] = $this->preparar();
        $this->get('/refrendos')->assertOk()->assertSee('Diego');
        $this->get('/refrendos/certificacion')->assertOk();
        $this->get('/refrendos/detalle')->assertOk()->assertSee('5000');
        $this->post('/refrendos/detalle', ['certificado_historico_id' => $historico->id, 'articulo_14' => '0', 'numero_refrendo' => 99])->assertRedirect();
        $s = SolicitudRefrendo::firstOrFail();
        $this->assertSame(2, $s->numero_refrendo);
        $this->get('/refrendos/'.$s->id.'/documentos')->assertRedirect(route('refrendos.formacion', $s->id));
        $this->get('/refrendos/'.$s->id.'/comprobante')->assertStatus(409);
        $institucion = InstitucionAfiliada::create(['numero_afiliacion' => '1', 'nombre' => 'UNAM']);
        $this->post('/refrendos/'.$s->id.'/formacion', ['nivel_origen' => 'Licenciatura', 'titulo_origen' => 'Informática',
            'nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Sistemas', 'institucion_afiliada_id' => $institucion->id])->assertRedirect();
        $this->get('/refrendos/'.$s->id.'/formacion')->assertOk();
        $this->get('/refrendos/'.$s->id.'/documentos')->assertOk()->assertDontSee('Carta Solicitud');
        $this->post('/refrendos/'.$s->id.'/documentos', [])->assertSessionHasErrors('constancia_antiguedad');
        $archivos = [];
        foreach (['constancia_antiguedad', 'comprobante_pago', 'cedula_ultimo_grado', 'titulo_ultimo_grado'] as $campo) {
            $archivos[$campo] = UploadedFile::fake()->create($campo.'.pdf', 10, 'application/pdf');
        }
        $this->post('/refrendos/'.$s->id.'/documentos', $archivos)->assertRedirect(route('refrendos.completado', $s->id));
        $this->get('/refrendos/'.$s->id.'/completado')->assertOk();
        $this->get('/refrendos/'.$s->id.'/comprobante')->assertOk()->assertDownload('comprobante-refrendo-'.$s->id.'.html');
        $this->get('/refrendos/'.$s->id.'/documentos/comprobante_pago')->assertOk();
        $this->post('/refrendos/'.$s->id.'/documentos', $archivos)->assertStatus(409);
        $this->assertDatabaseCount('formaciones_academicas', 0);
        $this->assertSame('2023', $historico->fresh()->refrendo_1);
        $otro = User::factory()->create();
        DatosSolicitante::where('user_id', $user->id)->update(['user_id' => $otro->id]);
        $this->actingAs($otro)->get('/refrendos/'.$s->id.'/documentos/comprobante_pago')->assertNotFound();
        $this->get('/refrendos/'.$s->id.'/comprobante')->assertNotFound();
    }

    public function test_articulo_14_exige_inciso_y_documentos_adicionales(): void
    {
        [$user, $historico] = $this->preparar();
        $this->post('/refrendos/detalle', ['certificado_historico_id' => $historico->id, 'articulo_14' => 1])->assertSessionHasErrors('inciso');
        $this->post('/refrendos/detalle', ['certificado_historico_id' => $historico->id, 'articulo_14' => 1, 'inciso' => '14.4'])->assertSessionHasErrors('inciso');
        $this->post('/refrendos/detalle', ['certificado_historico_id' => $historico->id, 'articulo_14' => 1, 'inciso' => '14.2'])->assertRedirect();
        $s = SolicitudRefrendo::firstOrFail();
        $s->update(['formacion' => ['nivel_origen' => 'Licenciatura']]);
        $this->get('/refrendos/'.$s->id.'/documentos')->assertOk()->assertSee('Carta Solicitud');
        $this->post('/refrendos/'.$s->id.'/documentos', [])->assertSessionHasErrors(['carta_solicitud', 'constancia_articulo_14']);
        $this->post('/refrendos/detalle', ['certificado_historico_id' => $historico->id, 'articulo_14' => 0])->assertRedirect();
        $this->assertDatabaseCount('solicitudes_refrendo', 1);
        $this->assertNull($s->fresh()->inciso);
    }

    public function test_articulo_14_se_envia_con_seis_archivos_y_rechaza_ejecutables(): void
    {
        Storage::fake('local');
        [$user, $historico] = $this->preparar();
        $this->post('/refrendos/detalle', ['certificado_historico_id' => $historico->id, 'articulo_14' => 1, 'inciso' => '14.3'])->assertRedirect();
        $s = SolicitudRefrendo::firstOrFail();
        $s->update(['formacion' => ['institucion' => 'UNAM']]);
        $this->post('/refrendos/'.$s->id.'/documentos', ['constancia_antiguedad' => UploadedFile::fake()->create('archivo.php', 1, 'text/x-php')])->assertSessionHasErrors('constancia_antiguedad');
        $archivos = [];
        foreach (array_keys(RefrendoController::campos(true)) as $campo) {
            $archivos[$campo] = UploadedFile::fake()->create($campo.'.pdf', 10, 'application/pdf');
        }
        $this->post('/refrendos/'.$s->id.'/documentos', $archivos)->assertRedirect(route('refrendos.completado', $s->id));
        $this->assertCount(6, $s->fresh()->documentos);
        foreach ($s->fresh()->documentos as $ruta) {
            Storage::disk('local')->assertExists($ruta);
        }
        $this->get('/refrendos/'.$s->id.'/comprobante')->assertOk()->assertSee('14.3');
        $this->post('/refrendos/detalle', ['certificado_historico_id' => $historico->id, 'articulo_14' => 0])->assertRedirect(route('refrendos.completado', $s->id));
        $this->assertTrue($s->fresh()->articulo_14);
    }

    public function test_antecedente_no_verificado_o_ajeno_no_permite_refrendo(): void
    {
        [$user, $historico] = $this->preparar();
        $historico->estado_vinculacion = 'pendiente';
        $historico->save();
        $this->get('/refrendos/detalle')->assertOk()->assertSee('Antecedente pendiente');
        $this->post('/refrendos/detalle', ['certificado_historico_id' => $historico->id, 'articulo_14' => 0])->assertForbidden();
        $historico->forceFill(['estado_vinculacion' => 'verificado', 'user_id' => User::factory()->create()->id])->save();
        $this->post('/refrendos/detalle', ['certificado_historico_id' => $historico->id, 'articulo_14' => 0])->assertForbidden();
        DatosSolicitante::where('user_id', $user->id)->update(['datos_autorizados' => false]);
        $this->get('/refrendos')->assertRedirect(route('mis-datos.edit'));
        $this->post('/logout');
        $this->get('/refrendos')->assertRedirect(route('login'));
    }
}
