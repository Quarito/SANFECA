<?php

namespace Tests\Feature;

use App\Models\SolicitudCertificacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Flujos completos por HTTP en el modo de producción (public, PDM apagado),
 * con datos FICTICIOS, SQLite en memoria y almacenamiento falso.
 *
 *  A. Certificación inicial.
 *  B. Refrendo ordinario (sin artículo 14).
 *  C. Refrendo por artículo 14.
 *
 * Cada flujo llega al acuse y verifica las reglas de la primera entrega:
 * formación académica obligatoria para todos, documentos probatorios
 * bloqueados tras el primer guardado, compromiso obligatorio y sin acceso
 * cruzado a documentos.
 */
class FlujoCompletoRegistroTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refreshDatabaseLaravel;
    }

    private const TC = 'Profesoras y profesores de Tiempo Completo';

    public function refreshDatabase(): void
    {
        if (
            !app()->environment('testing')
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
        ) {
            throw new RuntimeException('SEGURIDAD: los flujos completos requieren SQLite en memoria.');
        }

        $this->refreshDatabaseLaravel();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutVite();
        config(['anfeca_pdm.modo' => 'apagado']);

        DB::table('instituciones_afiliadas')->insert([
            'id' => 1, 'numero_afiliacion' => 'F01', 'nombre' => 'Institución Ficticia',
            'campus' => 'Campus Ficticio', 'escuela_facultad' => null,
        ]);
    }

    private function pdf(string $nombre): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, 20, 'application/pdf');
    }

    private function historico(string $curp, string $numero): void
    {
        DB::table('certificados_historicos')->insert([
            'numero_certificado' => $numero, 'primera_vez' => '2019', 'curp_historica' => $curp,
            'certificacion' => 'ANFECA', 'lote_importacion' => 'L-PRUEBA', 'fila_origen' => 1,
            'estado_vinculacion' => 'pendiente',
        ]);
    }

    /** Registro → datos personales (edición previa) → autorización. */
    private function registroYDatos(string $curp, string $correo): User
    {
        if (auth()->check()) {
            auth()->logout(); // el registro es solo para invitados
        }

        $this->post('/registro/datos-personales', [
            'curp' => $curp, 'rfc' => substr($curp, 0, 10).substr($curp, -3), 'nombres' => 'Persona',
            'apellido_paterno' => 'Ficticia', 'genero' => 'Femenino', 'calle' => 'Calle Ficticia',
            'numero_exterior' => '10', 'codigo_postal' => '01000', 'entidad_federativa' => 'Ciudad de México',
            'colonia' => 'Centro', 'municipio' => 'Cuauhtémoc', 'correo_electronico' => $correo,
            'telefono_celular' => '5511111111',
            'password' => 'ContrasenaFicticia1', 'password_confirmation' => 'ContrasenaFicticia1',
        ])->assertRedirect(route('registro.certificacion'));

        $user = User::where('email', $correo)->firstOrFail();

        // Sin autorizar, las etapas siguientes no están disponibles.
        $this->actingAs($user)->get('/registro/certificacion')->assertRedirect(route('mis-datos.edit'));

        $this->actingAs($user)->put('/mis-datos', [
            'calle' => 'Calle Nueva', 'numero_exterior' => '20', 'codigo_postal' => '02000',
            'entidad_federativa' => 'Ciudad de México', 'colonia' => 'Nueva', 'municipio' => 'Nuevo',
            'telefono_celular' => '5522222222',
        ])->assertRedirect(route('mis-datos.edit'));

        $this->actingAs($user)->post('/mis-datos/autorizar', ['confirmacion_datos' => '1'])
            ->assertRedirect(route('registro.certificacion'));

        // Restricción vigente: tras autorizar no se modifican los datos.
        $this->actingAs($user)->put('/mis-datos', ['calle' => 'Otra calle']);
        $this->assertSame('Calle Nueva', DB::table('datos_solicitantes')->where('user_id', $user->id)->value('calle'));

        return $user;
    }

    /** Formación académica obligatoria (con sus 4 documentos) y autorización. */
    private function formacion(User $user): void
    {
        // Sin formación no se llega a documentos probatorios.
        $this->actingAs($user)->get('/registro/documentos-probatorios')->assertRedirect(route('registro.formacion'));

        $this->actingAs($user)->get('/registro/formacion-academica')->assertOk();
        $this->actingAs($user)->post('/registro/formacion-academica', [
            'nivel_origen' => 'Licenciatura', 'titulo_origen' => 'Administración',
            'nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro',
            'titulo_ultimo_grado_otro' => 'Finanzas corporativas', 'institucion_afiliada_id' => 1,
            'titulo_origen_archivo' => $this->pdf('to.pdf'), 'cedula_origen_archivo' => $this->pdf('co.pdf'),
            'titulo_ultimo_grado_archivo' => $this->pdf('tu.pdf'), 'cedula_ultimo_grado_archivo' => $this->pdf('cu.pdf'),
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post('/registro/formacion-academica/autorizar', ['confirmacion_formacion' => '1'])
            ->assertRedirect(route('registro.formacion'));
        $this->actingAs($user)->get('/registro/formacion-academica')->assertOk()
            ->assertSee('han sido autorizados')
            ->assertSee(route('registro.documentos'), false);
    }

    /** Documentos probatorios: primer guardado, bloqueo, compromiso, envío y acuse. */
    private function documentosHastaAcuse(User $user, array $documentos): SolicitudCertificacion
    {
        $this->actingAs($user)->get('/registro/documentos-probatorios')->assertOk()
            ->assertSee('data-documentos-bloqueados="0"', false);

        $this->actingAs($user)->post('/registro/documentos-probatorios', $documentos)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('registro.compromiso'));

        $solicitud = SolicitudCertificacion::where('user_id', $user->id)->latest('id')->firstOrFail();
        $guardados = $solicitud->documentosProbatorios()->first()->getAttributes();

        // Bloqueo posterior: la sustitución manipulada se rechaza sin efectos.
        $this->actingAs($user)->post('/registro/documentos-probatorios', [
            'constancia_antiguedad_archivo' => $this->pdf('otra.pdf'), 'link' => 'https://example.test/otro',
        ])->assertRedirect(route('registro.documentos'))->assertSessionHas('error');
        $this->assertEquals($guardados, $solicitud->documentosProbatorios()->first()->getAttributes());
        $this->actingAs($user)->get('/registro/documentos-probatorios')->assertOk()
            ->assertSee('data-documentos-bloqueados="1"', false)->assertSee('Siguiente');

        // Finalización exige el compromiso; la casilla es obligatoria en el servidor.
        $this->actingAs($user)->get('/registro/completado')->assertRedirect(route('registro.compromiso'));
        $this->actingAs($user)->post('/registro/completado/confirmar')->assertRedirect(route('registro.compromiso'));
        $this->actingAs($user)->get('/registro/compromiso')->assertOk();
        $this->actingAs($user)->post('/registro/compromiso/guardar', [])->assertSessionHasErrors('compromiso_aceptado');
        $this->actingAs($user)->post('/registro/compromiso/guardar', ['compromiso_aceptado' => '1'])
            ->assertRedirect(route('registro.completado'));

        $this->actingAs($user)->get('/registro/completado')->assertOk()->assertSee('Confirmar envío de solicitud');
        $this->actingAs($user)->post('/registro/completado/confirmar')
            ->assertSessionHas('success', 'Solicitud enviada correctamente.');

        $solicitud->refresh();
        $this->assertSame('enviado', $solicitud->estado);
        $this->assertTrue($solicitud->compromiso_aceptado);

        $this->actingAs($user)->get("/registro/acuse/{$solicitud->id}")
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        return $solicitud;
    }

    private function sinAccesoCruzado(User $duenio, SolicitudCertificacion $solicitud): void
    {
        $intruso = $this->registroYDatos('INTR800101HDFRRN09', 'intruso'.$solicitud->id.'@example.test');
        $this->actingAs($intruso)->get("/registro/acuse/{$solicitud->id}")->assertNotFound();
        $respuesta = $this->actingAs($intruso)->get('/registro/documentos-probatorios/ver/constancia_antiguedad_archivo');
        $this->assertNotSame(200, $respuesta->getStatusCode());
        $this->assertNull($respuesta->headers->get('content-disposition'));
    }

    public function test_escenario_a_certificacion_inicial_hasta_acuse(): void
    {
        $user = $this->registroYDatos('FICA800101HDFRRN01', 'inicial@example.test');

        $this->actingAs($user)->get('/registro/certificacion')->assertOk()
            ->assertSee('data-numero-certificado-anterior=""', false);
        $this->actingAs($user)->post('/registro/certificacion', [
            'certificacion' => 'ANFECA', 'tipo_personal' => self::TC, 'area_certificacion' => 'Administración',
        ])->assertRedirect(route('registro.formacion'));

        $this->formacion($user);

        $solicitud = $this->documentosHastaAcuse($user, [
            'link' => 'https://example.test/evidencias',
            'constancia_antiguedad_archivo' => $this->pdf('c.pdf'), 'comprobante_pago_archivo' => $this->pdf('p.pdf'),
        ]);

        $this->assertSame('inicial', $solicitud->tipo_tramite);
        $this->sinAccesoCruzado($user, $solicitud);
    }

    public function test_escenario_b_refrendo_ordinario_hasta_acuse(): void
    {
        $this->historico('FIRB800101HDFRRN02', '1000031');
        $user = $this->registroYDatos('FIRB800101HDFRRN02', 'refrendo.b@example.test');

        // Refrendo nuevo: la certificación inicia en blanco.
        $this->actingAs($user)->get('/registro/certificacion')->assertOk()
            ->assertSee('data-numero-certificado-anterior=""', false);

        $this->actingAs($user)->post('/registro/certificacion', [
            'certificacion' => 'ANFECA', 'tipo_personal' => self::TC, 'area_certificacion' => 'Administración',
            'numero_certificado_anterior' => '1000031',
        ])->assertRedirect(route('registro.refrendo'));

        // Al regresar durante el mismo refrendo se conserva el certificado validado.
        $this->actingAs($user)->get('/registro/certificacion')->assertOk()
            ->assertSee('data-numero-certificado-anterior="1000031"', false);

        $this->actingAs($user)->post('/registro/refrendo', ['aplica_articulo_14' => '0'])
            ->assertRedirect(route('registro.formacion'));

        $this->formacion($user);

        $solicitud = $this->documentosHastaAcuse($user, [
            'link' => 'https://example.test/evidencias',
            'constancia_antiguedad_archivo' => $this->pdf('c.pdf'), 'comprobante_pago_archivo' => $this->pdf('p.pdf'),
        ]);

        $this->assertSame('refrendo', $solicitud->tipo_tramite);
        $this->assertNotNull($solicitud->certificado_historico_id);
        $this->assertSame(0, (int) $solicitud->datosRefrendo->aplica_articulo_14);
        $this->sinAccesoCruzado($user, $solicitud);
    }

    public function test_escenario_c_refrendo_articulo_14_hasta_acuse(): void
    {
        $this->historico('FIRC800101HDFRRN03', '1000032');
        $user = $this->registroYDatos('FIRC800101HDFRRN03', 'refrendo.c@example.test');

        $this->actingAs($user)->post('/registro/certificacion', [
            'certificacion' => 'ANFECA', 'tipo_personal' => self::TC, 'area_certificacion' => 'Administración',
            'numero_certificado_anterior' => '1000032',
        ])->assertRedirect(route('registro.refrendo'));

        $this->actingAs($user)->post('/registro/refrendo', ['aplica_articulo_14' => '1', 'inciso_articulo_14' => '14.1'])
            ->assertRedirect(route('registro.formacion'));

        // Primera entrega: el art. 14 también completa Formación académica.
        $this->formacion($user);

        $solicitud = $this->documentosHastaAcuse($user, [
            'constancia_antiguedad_archivo' => $this->pdf('c.pdf'), 'comprobante_pago_archivo' => $this->pdf('p.pdf'),
            'solicitud_articulo_14_archivo' => $this->pdf('s14.pdf'), 'constancia_articulo_14_archivo' => $this->pdf('c14.pdf'),
        ]);

        $this->assertSame('refrendo', $solicitud->tipo_tramite);
        $this->assertSame('14.1', $solicitud->datosRefrendo->inciso_articulo_14);
        $this->assertNotNull($solicitud->formacionAcademica?->formacion_completada_at);
        $this->sinAccesoCruzado($user, $solicitud);
    }

    public function test_preview_no_disponible_en_produccion(): void
    {
        $this->assertFalse(app()->environment('local'));
        $this->get('/preview/datos')->assertNotFound();
        $this->get('/preview/compromiso')->assertNotFound();
        $this->get('/login')->assertOk();
    }
}
