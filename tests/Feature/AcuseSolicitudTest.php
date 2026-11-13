<?php

namespace Tests\Feature;

use App\Models\DatosSolicitante;
use App\Models\InstitucionAfiliada;
use App\Models\SeleccionCertificacion;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use App\Services\AcusePdfService;
use App\Services\AcuseSolicitudService;
use App\Services\ConsultaAntecedentesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class AcuseSolicitudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function expediente(string $tipo = 'inicial', bool $articulo14 = false): SolicitudCertificacion
    {
        $user = User::factory()->create();
        DatosSolicitante::create([
            'user_id' => $user->id, 'nombres' => 'María de los Ángeles',
            'apellido_paterno' => 'Muñoz', 'apellido_materno' => 'Sánchez',
            'curp' => fake()->unique()->bothify('AAAA######HAAAAA##'),
            'rfc' => fake()->unique()->bothify('AAAA######AAA'),
            'calle' => 'Universidad', 'numero_exterior' => '1', 'codigo_postal' => '04510',
            'entidad_federativa' => 'Ciudad de México', 'colonia' => 'Universidad',
            'municipio' => 'Coyoacán', 'correo_electronico' => $user->email,
            'telefono_celular' => '5512345678', 'datos_autorizados' => true,
        ]);
        SeleccionCertificacion::create([
            'user_id' => $user->id, 'certificacion' => 'ANFECA',
            'tipo_personal' => 'Tiempo Completo', 'area_certificacion' => 'Informática Administrativa',
        ]);
        $solicitud = SolicitudCertificacion::create([
            'user_id' => $user->id, 'tipo_tramite' => $tipo,
            'certificacion' => 'ANFECA', 'estado' => 'borrador',
        ]);
        if ($tipo === 'refrendo') {
            $solicitud->datosRefrendo()->create([
                'refrendo_actual' => 2, 'aplica_articulo_14' => $articulo14,
                'inciso_articulo_14' => $articulo14 ? '14.1' : null,
            ]);
        }
        if (! $articulo14) {
            $institucion = InstitucionAfiliada::create([
                'numero_afiliacion' => 'TEST-'.$user->id,
                'nombre' => 'Universidad Nacional Autónoma de México',
                'campus' => 'Ciudad Universitaria', 'unidad' => 'Unidad de Posgrado',
                'sede' => 'Facultad de Contaduría y Administración', 'zona_anfeca' => 'Zona 7',
            ]);
            $archivos = [];
            foreach (['titulo_origen_archivo', 'cedula_origen_archivo', 'titulo_ultimo_grado_archivo', 'cedula_ultimo_grado_archivo'] as $campo) {
                $archivos[$campo] = $solicitud->id.'/'.$campo.'.pdf';
                Storage::disk('local')->put($archivos[$campo], 'documento de prueba');
            }
            $solicitud->formacionAcademica()->create($archivos + [
                'nivel_origen' => 'Licenciatura', 'titulo_origen' => 'Informática',
                'nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Administración',
                'institucion_afiliada_id' => $institucion->id,
            ]);
        }
        $archivos = [];
        foreach (['constancia_antiguedad_archivo', 'comprobante_pago_archivo'] as $campo) {
            $archivos[$campo] = $solicitud->id.'/'.$campo.'.pdf';
            Storage::disk('local')->put($archivos[$campo], 'documento de prueba');
        }
        $solicitud->documentosProbatorios()->create($archivos);
        $this->mock(ConsultaAntecedentesService::class, function (MockInterface $mock) use ($tipo): void {
            $mock->shouldReceive('determinarTipoTramite')->andReturn($tipo);
        });
        $this->actingAs($user);

        return $solicitud;
    }

    private function enviar(SolicitudCertificacion $solicitud): void
    {
        $this->post(route('registro.completado.confirmar'), ['solicitud_id' => $solicitud->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('registro.completado', ['solicitud' => $solicitud->id]));
    }

    public function test_envio_inicial_congela_datos_y_no_duplica_el_acuse(): void
    {
        $solicitud = $this->expediente();
        $this->freezeTime();
        $this->enviar($solicitud);
        $acuse = $solicitud->fresh()->acuse;
        $this->assertNotNull($acuse);
        $this->assertSame('enviado', $solicitud->fresh()->estado);
        $this->assertSame('María de los Ángeles', $acuse->datos['persona']['nombres']);
        $this->assertSame('Universidad Nacional Autónoma de México', $acuse->datos['institucion']['nombre']);
        $this->assertSame('Ciudad Universitaria', $acuse->datos['institucion']['campus']);
        $this->assertSame('Unidad de Posgrado', $acuse->datos['institucion']['unidad']);
        $this->assertCount(6, $acuse->datos['documentos']);
        foreach ($acuse->datos['documentos'] as $documento) {
            $this->assertTrue($documento['adjuntado']);
            $this->assertTrue($documento['requerido']);
        }
        $fecha = $solicitud->fresh()->fecha_envio->toISOString();
        $datos = $acuse->datos;
        DatosSolicitante::where('user_id', $solicitud->user_id)->update(['nombres' => 'Otro nombre']);
        SeleccionCertificacion::where('user_id', $solicitud->user_id)->update(['area_certificacion' => 'Otra área']);
        InstitucionAfiliada::query()->update(['nombre' => 'Otra institución', 'campus' => 'Otro campus', 'unidad' => 'Otra unidad']);
        Storage::disk('local')->delete($solicitud->formacionAcademica->titulo_origen_archivo);
        $this->travel(1)->days();
        $this->enviar($solicitud);
        $this->assertDatabaseCount('acuses_solicitud', 1);
        $this->assertSame($datos, $acuse->fresh()->datos);
        $this->assertSame($fecha, $solicitud->fresh()->fecha_envio->toISOString());
        $this->get(route('registro.completado', ['solicitud' => $solicitud->id]))
            ->assertOk()->assertSee('Descargar acuse en PDF');
        $this->get(route('panel'))->assertOk()->assertSee(route('solicitudes.acuse', $solicitud->id), false);
    }

    public function test_refrendo_ordinario_y_articulo_14_generan_acuse(): void
    {
        foreach ([false, true] as $articulo14) {
            $solicitud = $this->expediente('refrendo', $articulo14);
            $this->enviar($solicitud);
            $acuse = $solicitud->fresh()->acuse;
            $this->assertSame('refrendo', $acuse->datos['tipo_tramite']);
            $this->assertSame($articulo14, $acuse->datos['omite_formacion']);
            $html = view('acuses.v1', ['datos' => $acuse->datos])->render();
            if ($articulo14) {
                $this->assertNull($acuse->datos['formacion']);
                $this->assertFalse($acuse->datos['documentos']['titulo_origen_archivo']['adjuntado']);
                $this->assertFalse($acuse->datos['documentos']['titulo_origen_archivo']['requerido']);
                $this->assertTrue($acuse->datos['documentos']['comprobante_pago_archivo']['adjuntado']);
                $this->assertStringContainsString('No se requiere volver a presentar', $html);
            }
            $this->get(route('solicitudes.acuse', $solicitud->id))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_un_borrador_no_tiene_descarga_y_un_expediente_incompleto_no_se_envia(): void
    {
        $solicitud = $this->expediente();
        $this->get(route('solicitudes.acuse', $solicitud->id))->assertNotFound();
        Storage::disk('local')->delete($solicitud->documentosProbatorios->comprobante_pago_archivo);
        $this->post(route('registro.completado.confirmar'), ['solicitud_id' => $solicitud->id])->assertSessionHas('error');
        $this->assertSame('borrador', $solicitud->fresh()->estado);
        $this->assertDatabaseCount('acuses_solicitud', 0);
    }

    public function test_descarga_privada_y_pdf_real(): void
    {
        $solicitud = $this->expediente();
        $this->enviar($solicitud);
        $url = route('solicitudes.acuse', $solicitud->id);
        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="acuse-solicitud-'.$solicitud->id.'.pdf"');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        Storage::disk('local')->put('acuse-prueba.pdf', $response->getContent());
        $this->actingAs(User::factory()->create());
        $this->get($url)->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_un_fallo_del_pdf_no_revierte_el_envio_y_permite_reintentar(): void
    {
        $solicitud = $this->expediente();
        $this->enviar($solicitud);
        $this->mock(AcusePdfService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generar')->once()->andThrow(new RuntimeException('Fallo simulado'));
        });
        $this->get(route('solicitudes.acuse', $solicitud->id))->assertRedirect(route('panel'))->assertSessionHas('error');
        $this->assertSame('enviado', $solicitud->fresh()->estado);
        $this->assertDatabaseCount('acuses_solicitud', 1);
        $this->app->forgetInstance(AcusePdfService::class);
        $this->get(route('solicitudes.acuse', $solicitud->id))->assertOk();
    }

    public function test_si_falla_el_snapshot_no_se_confirma_la_solicitud(): void
    {
        $solicitud = $this->expediente();
        $this->mock(AcuseSolicitudService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('registrar')->once()->andThrow(new RuntimeException('Fallo simulado'));
        });
        $this->post(route('registro.completado.confirmar'), ['solicitud_id' => $solicitud->id])->assertStatus(500);
        $this->assertSame('borrador', $solicitud->fresh()->estado);
        $this->assertNull($solicitud->fresh()->fecha_envio);
        $this->assertDatabaseCount('acuses_solicitud', 0);
    }

    public function test_solicitud_antigua_no_se_reconstruye_con_datos_actuales(): void
    {
        $solicitud = $this->expediente();
        $solicitud->update(['estado' => 'enviado', 'fecha_envio' => now()->subYear()]);
        $this->get(route('solicitudes.acuse', $solicitud->id))->assertRedirect(route('panel'))->assertSessionHas('error');
        $this->enviar($solicitud);
        $this->assertDatabaseCount('acuses_solicitud', 0);
        $this->get(route('panel'))->assertSee('Acuse no disponible');
    }

    public function test_confirmar_un_folio_ajeno_no_modifica_solicitudes(): void
    {
        $ajena = $this->expediente();
        $propia = $this->expediente();
        $this->post(route('registro.completado.confirmar'), ['solicitud_id' => $ajena->id])->assertNotFound();
        $this->assertSame('borrador', $ajena->fresh()->estado);
        $this->assertSame('borrador', $propia->fresh()->estado);
    }

    public function test_repetir_un_envio_no_envia_otro_borrador(): void
    {
        $solicitud = $this->expediente();
        $this->enviar($solicitud);
        $otro = SolicitudCertificacion::create([
            'user_id' => $solicitud->user_id, 'certificacion' => 'ANFECA',
            'tipo_tramite' => 'inicial', 'estado' => 'borrador',
        ]);
        $this->enviar($solicitud);
        $this->assertSame('borrador', $otro->fresh()->estado);
        $this->assertDatabaseCount('acuses_solicitud', 1);
    }

    public function test_la_plantilla_escapa_texto_del_usuario(): void
    {
        $solicitud = $this->expediente();
        DatosSolicitante::where('user_id', $solicitud->user_id)->update(['nombres' => '<script>alert(1)</script>']);
        $this->enviar($solicitud);
        $html = view('acuses.v1', ['datos' => $solicitud->fresh()->acuse->datos])->render();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_inventario_distingue_archivos_ausentes_y_no_se_reconstruye(): void
    {
        $solicitud = $this->expediente('refrendo');
        $solicitud->datosRefrendo->update(['aplica_articulo_14' => true, 'inciso_articulo_14' => '14.1']);
        $solicitud->formacionAcademica->update(['titulo_ultimo_grado_archivo' => null]);
        Storage::disk('local')->delete($solicitud->formacionAcademica->cedula_ultimo_grado_archivo);
        $this->enviar($solicitud);
        $datos = $solicitud->fresh()->acuse->datos;
        $this->assertTrue($datos['documentos']['titulo_origen_archivo']['adjuntado']);
        $this->assertTrue($datos['documentos']['cedula_origen_archivo']['adjuntado']);
        $this->assertFalse($datos['documentos']['titulo_ultimo_grado_archivo']['adjuntado']);
        $this->assertFalse($datos['documentos']['cedula_ultimo_grado_archivo']['adjuntado']);
        $this->get(route('solicitudes.acuse', $solicitud->id))->assertOk();
    }

    public function test_plantilla_admite_acuses_previos_sin_inventario_ni_campus(): void
    {
        $solicitud = $this->expediente();
        $this->enviar($solicitud);
        $datos = $solicitud->fresh()->acuse->datos;
        unset($datos['documentos'], $datos['institucion']['campus'], $datos['institucion']['unidad']);
        $html = view('acuses.v1', ['datos' => $datos])->render();
        $this->assertStringContainsString('Institución de procedencia', $html);
        $this->assertStringContainsString('El inventario de documentos no se conservó', $html);
        $this->assertStringContainsString('No registrado', $html);
        $this->assertStringNotContainsString('Campus / Unidad / Sede', $html);
    }
}
