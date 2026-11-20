<?php

namespace Tests\Pdm;

use App\Models\User;
use App\Services\Pdm\Migracion\ConciliadorPdm;
use App\Services\Pdm\Migracion\MigradorPdm;
use App\Services\Pdm\Migracion\PlanMigracionPdm;
use App\Services\Pdm\ModoPdm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;

/**
 * F3 · Escritura en espejo: los flujos reales de ANFECA (HTTP) escriben en public y en
 * anfeca_modelo en la misma transacción; la conciliación se mantiene en 0 diferencias.
 * Usuarios y archivos FICTICIOS; PostgreSQL aislado.
 */
class EspejoPdmTest extends PdmTestCase
{
    private const TC = 'Profesoras y profesores de Tiempo Completo';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutVite();
        // Decisión FICTICIA de D-11 para la institución del escenario (sin ella la activación se niega).
        config(['anfeca_pdm_decisiones' => [
            'instituciones' => [1 => ['numero_afiliacion' => 'F01', 'facultad' => 'Facultad Ficticia', 'fuente' => 'Prueba']],
            'domicilios' => [], 'certificados' => [],
        ]]);

        // Estado de partida = F2 aplicada: catálogo de institución y un antecedente histórico ficticio.
        DB::table('public.zonas')->insert(['id' => 1, 'siglas' => 'ZF', 'descripcion' => 'Zona ficticia']);
        DB::table('public.instituciones_afiliadas')->insert(['id' => 1, 'numero_afiliacion' => 'F01', 'nombre' => 'Institución Ficticia', 'campus' => 'Campus', 'zona_id' => 1]);
        DB::table('public.certificados_historicos')->insert([
            'id' => 1, 'numero_certificado' => '1000022', 'primera_vez' => '2019', 'curp_historica' => 'FIRE800101HDFRRN05',
            'certificacion' => 'ANFECA', 'lote_importacion' => 'L-F3', 'fila_origen' => 1, 'estado_vinculacion' => 'pendiente',
        ]);
        DB::transaction(fn () => (new MigradorPdm())->escribir(PlanMigracionPdm::construir()));

        // Activación protegida (dos llaves).
        config(['anfeca_pdm.modo' => 'espejo']);
        $this->artisan('anfeca:pdm-modo', ['modo' => 'espejo', '--confirmar' => true])->assertExitCode(0);
        $this->assertSame('espejo', ModoPdm::efectivo());
    }

    public function test_la_activacion_se_niega_con_bloqueos_pendientes(): void
    {
        $this->artisan('anfeca:pdm-modo', ['modo' => 'apagado'])->assertExitCode(0);
        config(['anfeca_pdm_decisiones' => ['instituciones' => [], 'domicilios' => [], 'certificados' => []]]);

        $this->artisan('anfeca:pdm-modo', ['modo' => 'espejo', '--confirmar' => true])
            ->expectsOutputToContain('bloqueante')
            ->assertExitCode(1);
        $this->assertSame('apagado', ModoPdm::efectivo());
    }

    private function conciliar(): void
    {
        $diferencias = (new ConciliadorPdm())->conciliar(PlanMigracionPdm::construir());
        $this->assertSame([], $diferencias, 'La conciliación debe quedar en 0 diferencias.');
    }

    private function registrar(string $curp, string $correo, array $cambios = []): \Illuminate\Testing\TestResponse
    {
        return $this->post('/registro/datos-personales', array_merge([
            'curp' => $curp, 'rfc' => substr($curp, 0, 10).substr($curp, -3), 'nombres' => 'Persona', 'apellido_paterno' => 'Ficticia',
            'genero' => 'Femenino', 'calle' => 'Calle Ficticia', 'numero_exterior' => '10', 'codigo_postal' => '01000',
            'entidad_federativa' => 'CDMX', 'colonia' => 'Centro', 'municipio' => 'Cuauhtémoc',
            'correo_electronico' => $correo, 'telefono_celular' => '5511111111',
            'password' => 'ContrasenaFicticia1', 'password_confirmation' => 'ContrasenaFicticia1',
        ], $cambios));
    }

    private function pdf(string $nombre): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, 20, 'application/pdf');
    }

    private function pdm(string $tabla): \Illuminate\Database\Query\Builder
    {
        return DB::table("anfeca_modelo.{$tabla}");
    }

    public function test_flujo_inicial_completo_en_espejo(): void
    {
        // 1. Registro → PERSONA, USUARIO, DOMICILIO, COMUNICACION.
        $this->registrar('FICT800101HDFRRN01', 'espejo1@example.test')->assertRedirect(route('registro.certificacion'));
        $user = User::where('email', 'espejo1@example.test')->firstOrFail();
        $personaId = DB::table('public.datos_solicitantes')->where('user_id', $user->id)->value('id');

        $this->assertSame(1, $this->pdm('persona')->where('pers_id_persona', $personaId)->count());
        $this->assertSame($user->password, $this->pdm('usuario')->where('usua_id_usuario', $user->id)->value('usua_contrasenia'));
        $this->assertSame(9, $this->pdm('domicilio')->where('domi_id_persona', $personaId)->value('domi_id_entidad_federativa'), 'CDMX → clave 09');
        $this->assertSame(2, $this->pdm('comunicacion')->where('cumu_id_persona', $personaId)->count());
        $this->conciliar();

        // 2. Edición de domicilio antes de autorizar.
        $this->actingAs($user)->put('/mis-datos', [
            'calle' => 'Calle Nueva', 'numero_exterior' => '20', 'codigo_postal' => '02000', 'entidad_federativa' => 'tlaxcala',
            'colonia' => 'Nueva', 'municipio' => 'Nuevo', 'telefono_celular' => '5522222222', 'telefono_particular' => '5533333333',
        ])->assertRedirect(route('mis-datos.edit'));
        $this->assertSame('Calle Nueva', $this->pdm('domicilio')->where('domi_id_persona', $personaId)->value('domi_calle'));
        $this->assertSame(29, $this->pdm('domicilio')->where('domi_id_persona', $personaId)->value('domi_id_entidad_federativa'));
        $this->assertSame(3, $this->pdm('comunicacion')->where('cumu_id_persona', $personaId)->count(), 'se agrega el teléfono particular');
        $this->conciliar();

        // 3. Autorización de datos.
        $this->actingAs($user)->post('/mis-datos/autorizar', ['confirmacion_datos' => '1'])->assertRedirect(route('registro.certificacion'));
        $this->assertTrue($this->pdm('persona')->where('pers_id_persona', $personaId)->value('pers_datos_autorizados'));
        $this->conciliar();

        // 4. Certificación inicial → SOLICITUD + evento BO.
        $this->actingAs($user)->post('/registro/certificacion', [
            'certificacion' => 'ANFECA', 'tipo_personal' => self::TC, 'area_certificacion' => 'Administración',
        ])->assertRedirect(route('registro.formacion'));
        $solicitudId = (int) DB::table('public.solicitudes_certificacion')->where('user_id', $user->id)->value('id');
        $this->assertSame($solicitudId, $this->pdm('solicitud')->where('soli_id_solicitud', $solicitudId)->value('soli_folio_solicitud'));
        $this->assertSame(['BO'], $this->pdm('estado_solicitud')->where('esso_id_solicitud', $solicitudId)->pluck('esso_siglas')->all());
        $this->conciliar();

        // 5. Formación académica con «Otro» y los 4 documentos.
        $this->actingAs($user)->post('/registro/formacion-academica', [
            'nivel_origen' => 'Licenciatura', 'titulo_origen' => 'Administración',
            'nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro', 'titulo_ultimo_grado_otro' => 'Finanzas corporativas',
            'institucion_afiliada_id' => 1,
            'titulo_origen_archivo' => $this->pdf('to.pdf'), 'cedula_origen_archivo' => $this->pdf('co.pdf'),
            'titulo_ultimo_grado_archivo' => $this->pdf('tu1.pdf'), 'cedula_ultimo_grado_archivo' => $this->pdf('cu.pdf'),
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $this->pdm('persona_estudio_profesional')->where('pees_id_solicitud', $solicitudId)->count());
        $this->assertSame('REV', DB::table('anfeca_modelo.persona_estudio_profesional as p')
            ->join('anfeca_modelo.estudio_profesional as e', 'e.espr_id_estudio_profesional', '=', 'p.pees_id_estuido_profesional')
            ->where('p.pees_id_solicitud', $solicitudId)->where('p.pees_tipo', 'ULTIMO')->value('e.espr_estado'));
        $this->assertSame(4, $this->pdm('solicitud_documento')->where('socu_id_solicitud', $solicitudId)->count());
        $this->conciliar();

        // 6. Sustitución del título del último grado → historial conservado en ambos esquemas.
        $anterior = DB::table('public.formaciones_academicas_solicitud')->where('solicitud_id', $solicitudId)->value('titulo_ultimo_grado_archivo');
        $this->actingAs($user)->post('/registro/formacion-academica', [
            'nivel_origen' => 'Licenciatura', 'titulo_origen' => 'Administración',
            'nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro', 'titulo_ultimo_grado_otro' => 'Finanzas corporativas',
            'institucion_afiliada_id' => 1, 'titulo_ultimo_grado_archivo' => $this->pdf('tu2.pdf'),
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('public.historial_documentos_academicos')->where('solicitud_id', $solicitudId)->count());
        $estados = DB::table('anfeca_modelo.solicitud_documento as d')
            ->join('anfeca_modelo.estado_solicitud_documento as e', 'e.esdo_id_documentacion', '=', 'd.socu_id_documentacion')
            ->join('anfeca_modelo.estado_documento as x', 'x.esdo_id_estado_documento', '=', 'e.esdo_id_estado_documento')
            ->where('d.socu_url_archivo', $anterior)->pluck('x.esdo_estado_documento')->all();
        $this->assertSame(['Sustituido'], $estados, 'la versión anterior se conserva como Sustituido');
        $this->conciliar();

        // 7. Autorización de la formación → evento FA.
        $this->actingAs($user)->post('/registro/formacion-academica/autorizar', ['confirmacion_formacion' => '1'])
            ->assertRedirect(route('registro.documentos'));
        $this->assertContains('FA', $this->pdm('estado_solicitud')->where('esso_id_solicitud', $solicitudId)->pluck('esso_siglas')->all());
        $this->conciliar();

        // 8. Documentos probatorios, enlace y pago.
        $this->actingAs($user)->post('/registro/documentos-probatorios', [
            'link' => 'https://example.test/evidencias',
            'constancia_antiguedad_archivo' => $this->pdf('c.pdf'), 'comprobante_pago_archivo' => $this->pdf('p.pdf'),
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->pdm('pago')->where('pago_id_solicitud', $solicitudId)->count());
        $this->assertSame(1, $this->pdm('solicitud_documento')->where('socu_id_solicitud', $solicitudId)->where('socu_url_archivo', 'https://example.test/evidencias')->count());
        $this->conciliar();

        // 9. Compromiso → evento CA.
        $this->actingAs($user)->post('/registro/compromiso/guardar', ['compromiso_aceptado' => '1'])->assertRedirect(route('registro.completado'));
        $this->assertContains('CA', $this->pdm('estado_solicitud')->where('esso_id_solicitud', $solicitudId)->pluck('esso_siglas')->all());
        $this->conciliar();

        // 10. Envío → evento EN, área y tipo de personal confirmados.
        $this->actingAs($user)->post('/registro/completado/confirmar')->assertSessionHas('success', 'Solicitud enviada correctamente.');
        $this->assertSame(['BO', 'FA', 'CA', 'EN'], $this->pdm('estado_solicitud')->where('esso_id_solicitud', $solicitudId)
            ->orderBy('esso_id__estado_solicitud')->pluck('esso_siglas')->all());
        $this->assertSame('enviado', DB::table('public.solicitudes_certificacion')->where('id', $solicitudId)->value('estado'));
        $this->conciliar();

        // Acuse: sigue funcionando y protegido (lee de public).
        $this->actingAs($user)->get("/registro/acuse/{$solicitudId}")->assertOk();
    }

    public function test_refrendo_articulo_14_en_espejo(): void
    {
        $this->registrar('FIRE800101HDFRRN05', 'refrendo@example.test')->assertRedirect();
        $user = User::where('email', 'refrendo@example.test')->firstOrFail();
        $this->actingAs($user)->post('/mis-datos/autorizar', ['confirmacion_datos' => '1']);

        // Validación del certificado histórico (sin cambios: lee de public).
        $this->actingAs($user)->post('/registro/certificacion', [
            'certificacion' => 'ANFECA', 'tipo_personal' => self::TC, 'area_certificacion' => 'Administración',
            'numero_certificado_anterior' => '1000022',
        ])->assertRedirect(route('registro.refrendo'));

        $solicitudId = (int) DB::table('public.solicitudes_certificacion')->where('user_id', $user->id)->value('id');
        $s = $this->pdm('solicitud')->where('soli_id_solicitud', $solicitudId)->first();
        $this->assertSame('refrendo', $s->soli_tipo_tramite);
        $this->assertSame(1, (int) $s->soli_id_historico_importacion);
        $this->assertSame('1000022', $s->soli_numero_certificado);

        $this->actingAs($user)->post('/registro/refrendo', ['aplica_articulo_14' => '1', 'inciso_articulo_14' => '14.1'])
            ->assertRedirect(route('registro.formacion'));
        $s = $this->pdm('solicitud')->where('soli_id_solicitud', $solicitudId)->first();
        $this->assertTrue($s->soli_art14);
        $this->assertFalse($s->solo_art13);
        $this->assertSame('14.1', $this->pdm('articulo_subindice')->where('arce_id_articulo', $s->soli_id_articulo)->value('arce_subindice'));

        // Etapa 1: también el art. 14 exige formación académica antes de documentos.
        $this->actingAs($user)->get('/registro/documentos-probatorios')->assertRedirect(route('registro.formacion'));
        $this->conciliar();
    }

    public function test_dato_no_registrable_revierte_todo_con_error_controlado(): void
    {
        $this->registrar('FICT800101HDFRRN02', 'monterrey@example.test', ['entidad_federativa' => 'Monterrey'])
            ->assertSessionHasErrors('entidad_federativa');

        $this->assertDatabaseMissing('users', ['email' => 'monterrey@example.test']);
        $this->assertSame(0, $this->pdm('persona')->where('pers_curp', 'FICT800101HDFRRN02')->count());

        $this->registrar('FICT800101HDFRRN03', 'largo@example.test', ['numero_exterior' => 'Manzana 12'])
            ->assertSessionHasErrors('numero_exterior');
        $this->assertDatabaseMissing('users', ['email' => 'largo@example.test']);
        $this->conciliar();
    }

    public function test_falla_inesperada_del_pdm_revierte_tambien_public(): void
    {
        // Conflicto deliberado en el PDM: ya existe una persona con la misma CURP.
        $this->pdm('persona')->insert(['pers_id_persona' => 900, 'pers_nombre' => 'Conflicto', 'pers_curp' => 'FICT800101HDFRRN04', 'pers_rfc' => 'FICT800101AB4']);

        $this->registrar('FICT800101HDFRRN04', 'conflicto@example.test')->assertSessionHasErrors('pdm');

        $this->assertDatabaseMissing('users', ['email' => 'conflicto@example.test']);
        $this->assertDatabaseMissing('datos_solicitantes', ['curp' => 'FICT800101HDFRRN04']);
        $this->assertSame(1, $this->pdm('persona')->where('pers_curp', 'FICT800101HDFRRN04')->count());
    }

    public function test_el_error_del_espejo_se_muestra_en_finalizacion(): void
    {
        // La vista de finalización debe imprimir los errores de validación (antes solo mostraba session('error')).
        $html = view('registro.completado', ['solicitud' => null, 'requisitos' => [], 'registroCompleto' => false, 'enviada' => false,
            'errors' => (new \Illuminate\Support\ViewErrorBag())->put('default', new \Illuminate\Support\MessageBag(['pdm' => 'Mensaje controlado de prueba']))])->render();
        $this->assertStringContainsString('Mensaje controlado de prueba', $html);
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
        $this->conciliar();
    }

    public function test_modo_apagado_no_escribe_en_el_pdm(): void
    {
        $this->artisan('anfeca:pdm-modo', ['modo' => 'apagado'])->assertExitCode(0);
        $this->assertSame('apagado', ModoPdm::efectivo());

        $this->registrar('FICT800101HDFRRN07', 'apagado@example.test')->assertRedirect(route('registro.certificacion'));

        $this->assertDatabaseHas('users', ['email' => 'apagado@example.test']);
        $this->assertSame(0, $this->pdm('persona')->where('pers_curp', 'FICT800101HDFRRN07')->count());

        // Sin la primera llave (.env) tampoco hay espejo, aunque exista la activación.
        $this->artisan('anfeca:pdm-modo', ['modo' => 'espejo', '--confirmar' => true])->assertExitCode(1); // public difiere del PDM
        config(['anfeca_pdm.modo' => 'apagado']);
        $this->assertSame('apagado', ModoPdm::efectivo());
    }

    public function test_la_activacion_exige_datos_migrados_y_conciliacion_sin_diferencias(): void
    {
        $this->artisan('anfeca:pdm-modo', ['modo' => 'apagado'])->assertExitCode(0);

        // a) public con datos personales y PDM sin personas: falta la migración F2.
        $this->registrar('FICT800101HDFRRN08', 'desfase@example.test')->assertRedirect();
        $this->artisan('anfeca:pdm-modo', ['modo' => 'espejo', '--confirmar' => true])
            ->expectsOutputToContain('no tiene datos migrados')
            ->assertExitCode(1);

        // b) PDM migrado, pero un registro posterior con el espejo apagado lo deja desfasado.
        $alcance = ['usuarios' => [User::where('email', 'desfase@example.test')->value('id')]];
        DB::transaction(fn () => (new \App\Services\Pdm\Espejo\AplicadorPdm())->aplicar(PlanMigracionPdm::construir(null, $alcance), $alcance));
        auth()->logout();
        $this->registrar('FICT800101HDFRRN09', 'desfase2@example.test')->assertRedirect(route('registro.certificacion'));
        $this->assertDatabaseHas('users', ['email' => 'desfase2@example.test']);
        $this->artisan('anfeca:pdm-modo', ['modo' => 'espejo', '--confirmar' => true])
            ->expectsOutputToContain('diferencia(s)')
            ->assertExitCode(1);

        $this->assertSame('apagado', ModoPdm::efectivo());
    }

    public function test_activacion_requiere_la_primera_llave_y_autorizacion_en_la_base_en_uso(): void
    {
        $this->artisan('anfeca:pdm-modo', ['modo' => 'apagado'])->assertExitCode(0);

        config(['anfeca_pdm.modo' => 'apagado']);
        $this->artisan('anfeca:pdm-modo', ['modo' => 'espejo', '--confirmar' => true])
            ->expectsOutputToContain('ANFECA_PDM_MODO=espejo')->assertExitCode(1);

        config(['anfeca_pdm.modo' => 'espejo', 'anfeca_modelo.bd_permitida' => DB::connection()->getDatabaseName()]);
        $this->artisan('anfeca:pdm-modo', ['modo' => 'espejo', '--confirmar' => true])
            ->expectsOutputToContain('ANFECA_PDM_AUTORIZAR_ESPEJO=si')->assertExitCode(1);
        $this->assertSame('apagado', ModoPdm::efectivo());
    }

    public function test_reiniciar_demo_y_vaciar_se_niegan_en_espejo(): void
    {
        $this->artisan('anfeca:reiniciar-demo', ['--ejecutar' => true])->expectsOutputToContain('modo PDM apagado')->assertExitCode(1);
        $this->artisan('anfeca:pdm-vaciar', ['--confirmar' => true])->assertExitCode(1);
        $this->assertSame(1, $this->pdm('historico_certificado_importacion')->count());
    }
}
