<?php

namespace Tests\Feature;

use App\Models\DatosSolicitante;
use App\Models\DocumentoProbatorioSolicitud;
use App\Models\FormacionAcademicaSolicitud;
use App\Models\InstitucionAfiliada;
use App\Models\SeleccionCertificacion;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Regresión de los flujos validados de ANFECA_PRUEBAS con usuarios FICTICIOS.
 *
 * - Solo SQLite en memoria (misma protección que MigracionesAnfecaTest).
 * - Storage::fake: ningún archivo real se lee ni se escribe.
 * - Documenta el comportamiento vigente de desarrollo/anfeca-pruebas (6c873c2);
 *   no modifica controladores, rutas ni vistas.
 */
class RegresionAnfecaTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refreshDatabaseLaravel;
    }

    /**
     * Comprueba que se use SQLite en memoria y después delega en el método
     * original del trait (que restaura la conexión en memoria entre pruebas).
     */
    public function refreshDatabase(): void
    {
        if (
            !app()->environment('testing') ||
            config('database.default') !== 'sqlite' ||
            config('database.connections.sqlite.database') !== ':memory:' ||
            DB::connection()->getDriverName() !== 'sqlite'
        ) {
            throw new RuntimeException('SEGURIDAD: las pruebas de regresión requieren SQLite en memoria.');
        }

        $this->refreshDatabaseLaravel();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    // ------------------------------------------------------------------
    // Fábricas mínimas de datos ficticios
    // ------------------------------------------------------------------

    private int $consecutivo = 0;

    private function solicitante(bool $autorizado = true, array $extra = []): User
    {
        $n = ++$this->consecutivo;

        $user = User::create([
            'name' => "Persona Ficticia {$n}",
            'email' => "ficticio{$n}@example.test",
            'password' => Hash::make('ContrasenaFicticia1'),
        ]);

        DatosSolicitante::create(array_merge([
            'user_id' => $user->id,
            'curp' => sprintf('FICT8001%02dHDFRRN0%d', $n, $n % 10),
            'rfc' => sprintf('FICT8001%02dAB%d', $n, $n % 10),
            'nombres' => 'Persona',
            'apellido_paterno' => 'Ficticia',
            'apellido_materno' => (string) $n,
            'genero' => 'Femenino',
            'calle' => 'Calle Ficticia',
            'numero_exterior' => '10',
            'codigo_postal' => '01000',
            'entidad_federativa' => 'Ciudad de México',
            'colonia' => 'Colonia Ficticia',
            'municipio' => 'Alcaldía Ficticia',
            'correo_electronico' => "ficticio{$n}@example.test",
            'telefono_celular' => '5500000000',
            'datos_autorizados' => $autorizado,
            'fecha_autorizacion' => $autorizado ? now() : null,
        ], $extra));

        return $user;
    }

    private function borradorInicial(
        User $user,
        bool $completo = true,
        bool $compromiso = false,
        bool $formacionAutorizada = true
    ): SolicitudCertificacion
    {
        SeleccionCertificacion::create([
            'user_id' => $user->id,
            'certificacion' => 'ANFECA',
            'tipo_personal' => 'Profesoras y profesores de Tiempo Completo',
            'area_certificacion' => 'Administración',
        ]);

        $solicitud = SolicitudCertificacion::create([
            'user_id' => $user->id,
            'tipo_tramite' => 'inicial',
            'certificacion' => 'ANFECA',
            'estado' => 'borrador',
            'compromiso_aceptado' => $compromiso,
            'compromiso_aceptado_en' => $compromiso ? now() : null,
        ]);

        if ($completo) {
            $institucion = InstitucionAfiliada::create([
                'numero_afiliacion' => 'F'.$user->id,
                'nombre' => 'Institución Ficticia',
            ]);

            $ruta = fn (string $campo) => tap(
                "anfeca/documentos/solicitudes/{$solicitud->id}/{$campo}.pdf",
                fn ($r) => Storage::disk('local')->put($r, '%PDF-1.4 ficticio')
            );

            FormacionAcademicaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'nivel_origen' => 'Licenciatura',
                'titulo_origen' => 'Economía',
                'nivel_ultimo_grado' => 'Maestría',
                'titulo_ultimo_grado' => 'Finanzas corporativas',
                'institucion_afiliada_id' => $institucion->id,
                'titulo_origen_archivo' => $ruta('titulo_origen'),
                'cedula_origen_archivo' => $ruta('cedula_origen'),
                'titulo_ultimo_grado_archivo' => $ruta('titulo_ultimo_grado'),
                'cedula_ultimo_grado_archivo' => $ruta('cedula_ultimo_grado'),
                'formacion_completada_at' => $formacionAutorizada ? now() : null,
            ]);

            DocumentoProbatorioSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'constancia_antiguedad_archivo' => $ruta('constancia'),
                'comprobante_pago_archivo' => $ruta('pago'),
            ]);
        }

        return $solicitud;
    }

    // ------------------------------------------------------------------
    // Registro y datos personales
    // ------------------------------------------------------------------

    public function test_registro_nuevo_crea_usuario_y_datos(): void
    {
        $respuesta = $this->post('/registro/datos-personales', [
            'curp' => 'FICT800101HDFRRN01',
            'rfc' => 'FICT800101AB1',
            'nombres' => 'Nueva',
            'apellido_paterno' => 'Ficticia',
            'genero' => 'Femenino',
            'calle' => 'Calle', 'numero_exterior' => '1', 'codigo_postal' => '01000',
            'entidad_federativa' => 'Ciudad de México', 'colonia' => 'Centro', 'municipio' => 'Cuauhtémoc',
            'correo_electronico' => 'nueva@example.test',
            'telefono_celular' => '5511111111',
            'password' => 'ContrasenaFicticia1',
            'password_confirmation' => 'ContrasenaFicticia1',
        ]);

        $respuesta->assertRedirect(route('registro.certificacion'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('datos_solicitantes', ['curp' => 'FICT800101HDFRRN01', 'datos_autorizados' => false]);
    }

    public function test_registro_exige_al_menos_un_telefono(): void
    {
        $this->post('/registro/datos-personales', [
            'curp' => 'FICT800101HDFRRN02', 'rfc' => 'FICT800101AB2', 'nombres' => 'Sin', 'apellido_paterno' => 'Telefono',
            'genero' => 'Masculino', 'calle' => 'C', 'numero_exterior' => '1', 'codigo_postal' => '01000',
            'entidad_federativa' => 'X', 'colonia' => 'X', 'municipio' => 'X', 'correo_electronico' => 'sintel@example.test',
            'password' => 'ContrasenaFicticia1', 'password_confirmation' => 'ContrasenaFicticia1',
        ])->assertSessionHasErrors(['telefono_particular', 'telefono_celular']);

        $this->assertDatabaseMissing('users', ['email' => 'sintel@example.test']);
    }

    public function test_antes_de_autorizar_solo_se_actualiza_domicilio_y_telefonos(): void
    {
        $user = $this->solicitante(autorizado: false);
        $curpOriginal = DatosSolicitante::where('user_id', $user->id)->value('curp');

        $this->actingAs($user)->put('/mis-datos', [
            'curp' => 'MANI800101HDFRRN09',        // intento manipulado
            'nombres' => 'Nombre Manipulado',       // intento manipulado
            'correo_electronico' => 'otro@example.test',
            'calle' => 'Calle Nueva', 'numero_exterior' => '20', 'codigo_postal' => '02000',
            'entidad_federativa' => 'Ciudad de México', 'colonia' => 'Nueva', 'municipio' => 'Nuevo',
            'telefono_celular' => '5522222222',
        ])->assertRedirect(route('mis-datos.edit'));

        $datos = DatosSolicitante::where('user_id', $user->id)->first();
        $this->assertSame('Calle Nueva', $datos->calle);
        $this->assertSame($curpOriginal, $datos->curp);
        $this->assertSame('Persona', $datos->nombres);
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_despues_de_autorizar_no_se_permite_modificar(): void
    {
        $user = $this->solicitante(autorizado: true);

        $this->actingAs($user)->put('/mis-datos', [
            'calle' => 'Calle Manipulada', 'numero_exterior' => '99', 'codigo_postal' => '09000',
            'entidad_federativa' => 'X', 'colonia' => 'X', 'municipio' => 'X', 'telefono_celular' => '5533333333',
        ])->assertRedirect(route('mis-datos.edit'))->assertSessionHas('error');

        $this->assertSame('Calle Ficticia', DatosSolicitante::where('user_id', $user->id)->value('calle'));
    }

    public function test_autorizar_exige_confirmacion_y_registra_fecha(): void
    {
        $user = $this->solicitante(autorizado: false);

        $this->actingAs($user)->post('/mis-datos/autorizar', [])
            ->assertSessionHasErrors('confirmacion_datos');
        $this->assertFalse((bool) DatosSolicitante::where('user_id', $user->id)->value('datos_autorizados'));

        $this->actingAs($user)->post('/mis-datos/autorizar', ['confirmacion_datos' => '1'])
            ->assertRedirect(route('registro.certificacion'));

        $datos = DatosSolicitante::where('user_id', $user->id)->first();
        $this->assertTrue($datos->datos_autorizados);
        $this->assertNotNull($datos->fecha_autorizacion);
    }

    public function test_etapas_requieren_datos_autorizados(): void
    {
        $user = $this->solicitante(autorizado: false);

        foreach (['/registro/certificacion', '/registro/formacion-academica', '/registro/documentos-probatorios', '/registro/compromiso', '/registro/completado'] as $url) {
            $this->actingAs($user)->get($url)->assertRedirect(route('mis-datos.edit'));
        }
    }

    public function test_etapas_requieren_sesion(): void
    {
        foreach (['/mis-datos', '/registro/certificacion', '/registro/completado'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    // ------------------------------------------------------------------
    // Aceptación y compromiso
    // ------------------------------------------------------------------

    public function test_finalizacion_sin_compromiso_redirige_a_aceptacion(): void
    {
        $user = $this->solicitante();
        $this->borradorInicial($user, compromiso: false);

        $this->actingAs($user)->get('/registro/completado')
            ->assertRedirect(route('registro.compromiso'));
    }

    public function test_compromiso_sin_casilla_es_rechazado_y_con_casilla_se_registra(): void
    {
        $user = $this->solicitante();
        $solicitud = $this->borradorInicial($user);

        $this->actingAs($user)->post('/registro/compromiso/guardar', [])
            ->assertSessionHasErrors('compromiso_aceptado');
        $this->assertFalse($solicitud->fresh()->compromiso_aceptado);

        $this->actingAs($user)->post('/registro/compromiso/guardar', ['compromiso_aceptado' => '1'])
            ->assertRedirect(route('registro.completado'));

        $solicitud->refresh();
        $this->assertTrue($solicitud->compromiso_aceptado);
        $this->assertNotNull($solicitud->compromiso_aceptado_en);
        $this->assertSame('borrador', $solicitud->estado, 'Aceptar no debe enviar la solicitud.');
    }

    public function test_pantalla_de_aceptacion_es_componente_vue_con_texto_definitivo(): void
    {
        $user = $this->solicitante();
        $this->borradorInicial($user);

        $this->withoutVite();

        $this->actingAs($user)->get('/registro/compromiso')
            ->assertOk()
            ->assertSee('vue-aceptacion-compromiso', false);

        // Texto definitivo (literal, sin importar saltos de línea del template).
        $vue = file_get_contents(resource_path('js/components/AceptacionCompromiso.vue'));
        $texto = preg_replace('/\s+/u', ' ', strip_tags($vue));

        $this->assertStringContainsString(
            'Para completar su solicitud, deberá entregar las fotografías indicadas en la convocatoria y el comprobante de pago original.',
            $texto
        );
        $this->assertStringContainsString('Anterior', $texto);
        $this->assertStringContainsString('Aceptar y continuar', $texto);

        // «Anterior» regresa a Documentos probatorios.
        $blade = file_get_contents(resource_path('views/registro/aceptacion-compromiso.blade.php'));
        $this->assertStringContainsString("data-url-regresar=\"{{ route('registro.documentos') }}\"", $blade);
    }

    // ------------------------------------------------------------------
    // Envío
    // ------------------------------------------------------------------

    public function test_envio_rechazado_si_faltan_requisitos(): void
    {
        $user = $this->solicitante();
        $solicitud = $this->borradorInicial($user, completo: false, compromiso: true);

        $this->actingAs($user)->post('/registro/completado/confirmar')
            ->assertRedirect(route('registro.completado'))
            ->assertSessionHas('error');

        $this->assertSame('borrador', $solicitud->fresh()->estado);
        $this->assertNull($solicitud->fresh()->fecha_envio);
    }

    public function test_envio_rechazado_sin_compromiso_aunque_este_completo(): void
    {
        $user = $this->solicitante();
        $solicitud = $this->borradorInicial($user, completo: true, compromiso: false);

        $this->actingAs($user)->post('/registro/completado/confirmar')
            ->assertRedirect(route('registro.compromiso'));

        $this->assertSame('borrador', $solicitud->fresh()->estado);
    }

    public function test_envio_completo_cambia_solo_la_solicitud_correcta_y_no_se_duplica(): void
    {
        $otro = $this->solicitante();
        $solicitudOtro = $this->borradorInicial($otro, compromiso: true);

        $user = $this->solicitante();
        $solicitud = $this->borradorInicial($user, compromiso: true);

        $this->actingAs($user)->post('/registro/completado/confirmar')
            ->assertRedirect(route('registro.completado'))
            ->assertSessionHas('success', 'Solicitud enviada correctamente.');

        $solicitud->refresh();
        $this->assertSame('enviado', $solicitud->estado);
        $this->assertNotNull($solicitud->fecha_envio);
        $this->assertSame('Administración', $solicitud->area_certificacion);

        $fecha = $solicitud->fecha_envio->toDateTimeString();
        $this->travel(5)->minutes();
        $this->actingAs($user)->post('/registro/completado/confirmar')
            ->assertSessionHas('success', 'La solicitud ya fue enviada.');
        $this->assertSame($fecha, $solicitud->fresh()->fecha_envio->toDateTimeString());

        $this->assertSame('borrador', $solicitudOtro->fresh()->estado, 'La solicitud de otro usuario no debe cambiar.');
    }

    // ------------------------------------------------------------------
    // Acuse y documentos: aislamiento entre usuarios
    // ------------------------------------------------------------------

    public function test_acuse_no_se_entrega_para_borrador_ni_a_otro_usuario(): void
    {
        $user = $this->solicitante();
        $solicitud = $this->borradorInicial($user, compromiso: true);

        $this->actingAs($user)->get("/registro/acuse/{$solicitud->id}")->assertNotFound();

        $solicitud->update(['estado' => 'enviado', 'fecha_envio' => now()]);

        $intruso = $this->solicitante();
        $this->actingAs($intruso)->get("/registro/acuse/{$solicitud->id}")->assertNotFound();
    }

    public function test_documentos_exigen_formacion_academica_autorizada_etapa_1(): void
    {
        $user = $this->solicitante();
        $this->borradorInicial($user, formacionAutorizada: false);

        $this->actingAs($user)->get('/registro/documentos-probatorios')
            ->assertRedirect(route('registro.formacion'));
        $this->actingAs($user)->get('/registro/documentos-probatorios/ver/constancia_antiguedad_archivo')
            ->assertRedirect(route('registro.formacion'));
    }

    public function test_descarga_de_documento_propio_y_campo_no_permitido(): void
    {
        $user = $this->solicitante();
        $this->borradorInicial($user);

        $this->actingAs($user)->get('/registro/documentos-probatorios/ver/constancia_antiguedad_archivo')
            ->assertOk()
            ->assertDownload();
        $this->actingAs($user)->get('/registro/documentos-probatorios/ver/password')->assertNotFound();
    }

    public function test_descarga_de_documento_no_expone_archivos_de_otro_usuario(): void
    {
        $duenio = $this->solicitante();
        $this->borradorInicial($duenio);

        // El intruso no tiene solicitud: no debe recibir el archivo del dueño.
        $intruso = $this->solicitante();
        $respuesta = $this->actingAs($intruso)->get('/registro/documentos-probatorios/ver/constancia_antiguedad_archivo');

        $this->assertNotSame(200, $respuesta->getStatusCode());
        $this->assertNull($respuesta->headers->get('content-disposition'));
    }

    public function test_documentos_reemplazados_no_se_eliminan_del_almacenamiento(): void
    {
        // Requisito: conservar el archivo previo al sustituir un documento.
        $user = $this->solicitante();
        $solicitud = $this->borradorInicial($user);
        $anterior = $solicitud->documentosProbatorios->constancia_antiguedad_archivo;

        $this->actingAs($user)->post('/registro/documentos-probatorios', [
            'link' => 'https://example.test/evidencia-ficticia',
            'constancia_antiguedad_archivo' => \Illuminate\Http\UploadedFile::fake()->create('constancia_nueva.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $nueva = $solicitud->documentosProbatorios()->first()->constancia_antiguedad_archivo;
        $this->assertNotSame($anterior, $nueva, 'El reemplazo debe registrar el archivo nuevo.');
        Storage::disk('local')->assertExists($nueva);
        Storage::disk('local')->assertExists($anterior);
    }

}
