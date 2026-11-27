<?php

namespace Tests\Pdm;

use App\Models\User;
use App\Services\Pdm\Compat\EquivalenciaPdm;
use App\Services\Pdm\Migracion\ConciliadorPdm;
use App\Services\Pdm\Migracion\MigradorPdm;
use App\Services\Pdm\Migracion\PlanMigracionPdm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * Flujos HTTP reales de ANFECA (registro inicial, refrendo, formación con sustitución de
 * documentos, documentos probatorios, compromiso, envío y acuse) con datos FICTICIOS.
 * Cada paso llama a $verificar() para que cada modo compruebe lo suyo.
 */
trait FlujosAnfeca
{
    protected const TC = 'Profesoras y profesores de Tiempo Completo';

    /** Estado de partida = F2 aplicada sobre datos ficticios mínimos (institución y antecedente histórico). */
    protected function escenarioMigrado(): void
    {
        Storage::fake('local');
        $this->withoutVite();
        config(['anfeca_pdm_decisiones' => [
            'base_de_datos' => DB::connection()->getDatabaseName(),
            'instituciones' => [1 => ['numero_afiliacion' => 'F01', 'facultad' => 'Facultad Ficticia', 'fuente' => 'Prueba']],
            'domicilios' => [], 'certificados' => [],
        ]]);

        DB::table('public.zonas')->insert(['id' => 1, 'siglas' => 'ZF', 'descripcion' => 'Zona ficticia']);
        DB::table('public.instituciones_afiliadas')->insert(['id' => 1, 'numero_afiliacion' => 'F01', 'nombre' => 'Institución Ficticia', 'campus' => 'Campus', 'zona_id' => 1]);
        DB::table('public.certificados_historicos')->insert([
            'id' => 1, 'numero_certificado' => '1000022', 'primera_vez' => '2019', 'curp_historica' => 'FIRE800101HDFRRN05',
            'certificacion' => 'ANFECA', 'lote_importacion' => 'L-F4', 'fila_origen' => 1, 'estado_vinculacion' => 'pendiente',
        ]);
        DB::transaction(fn () => (new MigradorPdm())->escribir(PlanMigracionPdm::construir()));
    }

    /** Activa un modo con las dos llaves (config + comando protegido). */
    protected function activar(string $modo): void
    {
        config(['anfeca_pdm.modo' => $modo]);
        $this->artisan('anfeca:pdm-modo', ['modo' => $modo, '--confirmar' => true])->assertExitCode(0);
        // Cada prueba simula peticiones nuevas: se restablece la ruta de búsqueda de la conexión.
        DB::statement('set search_path to public');
    }

    protected function conciliacion(): array
    {
        return (new ConciliadorPdm())->conciliar(PlanMigracionPdm::construir());
    }

    protected function equivalencia(): array
    {
        return (new EquivalenciaPdm())->verificar();
    }

    protected function registrar(string $curp, string $correo, array $cambios = []): TestResponse
    {
        return $this->post('/registro/datos-personales', array_merge([
            'curp' => $curp, 'rfc' => substr($curp, 0, 10).substr($curp, -3), 'nombres' => 'Persona', 'apellido_paterno' => 'Ficticia',
            'genero' => 'Femenino', 'calle' => 'Calle Ficticia', 'numero_exterior' => '10', 'codigo_postal' => '01000',
            'entidad_federativa' => 'CDMX', 'colonia' => 'Centro', 'municipio' => 'Cuauhtémoc',
            'correo_electronico' => $correo, 'telefono_celular' => '5511111111',
            'password' => 'ContrasenaFicticia1', 'password_confirmation' => 'ContrasenaFicticia1',
        ], $cambios));
    }

    protected function pdf(string $nombre): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, 20, 'application/pdf');
    }

    protected function pdm(string $tabla): \Illuminate\Database\Query\Builder
    {
        return DB::table("anfeca_modelo.{$tabla}");
    }

    protected function eventos(int $solicitudId): array
    {
        return $this->pdm('estado_solicitud')->where('esso_id_solicitud', $solicitudId)
            ->orderBy('esso_id__estado_solicitud')->pluck('esso_siglas')->all();
    }

    protected function estadoDocumento(string $ruta): array
    {
        return DB::table('anfeca_modelo.solicitud_documento as d')
            ->join('anfeca_modelo.estado_solicitud_documento as e', 'e.esdo_id_documentacion', '=', 'd.socu_id_documentacion')
            ->join('anfeca_modelo.estado_documento as x', 'x.esdo_id_estado_documento', '=', 'e.esdo_id_estado_documento')
            ->where('d.socu_url_archivo', $ruta)->pluck('x.esdo_estado_documento')->all();
    }

    /**
     * Registro inicial completo, de datos personales a acuse.
     *
     * @return array{0: User, 1: int, 2: int} usuario, persona, solicitud
     */
    protected function flujoInicialCompleto(callable $verificar, string $curp = 'FICT800101HDFRRN01', string $correo = 'flujo1@example.test'): array
    {
        // 1. Registro → PERSONA, USUARIO técnico, DOMICILIO, COMUNICACION.
        $this->registrar($curp, $correo)->assertRedirect(route('registro.certificacion'));
        $user = User::where('email', $correo)->firstOrFail();
        $personaId = (int) $this->pdm('usuario')->where('usua_id_usuario', $user->id)->value('usua_id_persona');
        $this->assertGreaterThan(0, $personaId, 'el registro crea el USUARIO técnico con el mismo ID de users');
        $this->assertSame($user->password, $this->pdm('usuario')->where('usua_id_usuario', $user->id)->value('usua_contrasenia'));
        $this->assertSame(9, $this->pdm('domicilio')->where('domi_id_persona', $personaId)->value('domi_id_entidad_federativa'), 'CDMX → 09');
        $this->assertSame(2, $this->pdm('comunicacion')->where('comu_id_persona', $personaId)->count());
        $verificar('registro');

        // 2. Edición de domicilio y teléfonos antes de autorizar.
        $this->actingAs($user)->put('/mis-datos', [
            'calle' => 'Calle Nueva', 'numero_exterior' => '20', 'codigo_postal' => '02000', 'entidad_federativa' => 'tlaxcala',
            'colonia' => 'Nueva', 'municipio' => 'Nuevo', 'telefono_celular' => '5522222222', 'telefono_particular' => '5533333333',
        ])->assertRedirect(route('mis-datos.edit'));
        $this->assertSame('Calle Nueva', $this->pdm('domicilio')->where('domi_id_persona', $personaId)->value('domi_calle'));
        $this->assertSame(29, $this->pdm('domicilio')->where('domi_id_persona', $personaId)->value('domi_id_entidad_federativa'));
        $this->assertSame(3, $this->pdm('comunicacion')->where('comu_id_persona', $personaId)->count());
        $verificar('edicion');

        // 3. Autorización de datos personales.
        $this->actingAs($user)->post('/mis-datos/autorizar', ['confirmacion_datos' => '1'])->assertRedirect(route('registro.certificacion'));
        $this->assertTrue($this->pdm('persona')->where('pers_id_persona', $personaId)->value('pers_datos_autorizados'));
        $verificar('autorizacion');

        // 4. Selección de certificación → SELECCION_CERTIFICACION, SOLICITUD y evento BO.
        $this->actingAs($user)->post('/registro/certificacion', [
            'certificacion' => 'ANFECA', 'tipo_personal' => self::TC, 'area_certificacion' => 'Administración',
        ])->assertRedirect(route('registro.formacion'));
        $solicitudId = (int) $this->pdm('solicitud')->where('soli_id_persona', $personaId)->value('soli_id_solicitud');
        $this->assertGreaterThan(0, $solicitudId);
        $this->assertSame(1, $this->pdm('seleccion_certificacion')->where('secc_id_usuario', $user->id)->count());
        $this->assertSame(['BO'], $this->eventos($solicitudId));
        $verificar('certificacion');

        // 5. Formación académica con «Otro» y los 4 documentos.
        $this->actingAs($user)->post('/registro/formacion-academica', [
            'nivel_origen' => 'Licenciatura', 'titulo_origen' => 'Administración',
            'nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro', 'titulo_ultimo_grado_otro' => 'Finanzas corporativas',
            'institucion_afiliada_id' => 1,
            'titulo_origen_archivo' => $this->pdf('to.pdf'), 'cedula_origen_archivo' => $this->pdf('co.pdf'),
            'titulo_ultimo_grado_archivo' => $this->pdf('tu1.pdf'), 'cedula_ultimo_grado_archivo' => $this->pdf('cu.pdf'),
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $this->pdm('persona_estudio_profesional')->where('pees_id_solicitud', $solicitudId)->count());
        $this->assertSame(4, $this->pdm('solicitud_documento')->where('socu_id_solicitud', $solicitudId)->count());
        $this->assertSame(1, (int) $this->pdm('persona')->where('pers_id_persona', $personaId)->value('pers_id_institucion'));
        $anterior = $this->pdm('persona_estudio_profesional')->where('pees_id_solicitud', $solicitudId)->where('pees_tipo', 'ULTIMO')->value('pees_url_titulo');
        $verificar('formacion');

        // 6. Sustitución del título del último grado → la versión anterior queda como Sustituido.
        $this->actingAs($user)->post('/registro/formacion-academica', [
            'nivel_origen' => 'Licenciatura', 'titulo_origen' => 'Administración',
            'nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro', 'titulo_ultimo_grado_otro' => 'Finanzas corporativas',
            'institucion_afiliada_id' => 1, 'titulo_ultimo_grado_archivo' => $this->pdf('tu2.pdf'),
        ])->assertSessionHasNoErrors();
        $this->assertSame(['Sustituido'], $this->estadoDocumento($anterior), 'el historial documental se conserva');
        $this->assertSame(5, $this->pdm('solicitud_documento')->where('socu_id_solicitud', $solicitudId)->count());
        $verificar('sustitucion');

        // 7. Autorización de la formación → evento FA.
        $this->actingAs($user)->post('/registro/formacion-academica/autorizar', ['confirmacion_formacion' => '1'])
            ->assertRedirect(route('registro.documentos')); // «Autorizar y continuar» avanza a Documentos probatorios
        $this->assertSame(['BO', 'FA'], $this->eventos($solicitudId));
        $verificar('formacion_autorizada');

        // 8. Documentos probatorios, enlace y pago.
        $this->actingAs($user)->post('/registro/documentos-probatorios', [
            'link' => 'https://example.test/evidencias',
            'constancia_antiguedad_archivo' => $this->pdf('c.pdf'), 'comprobante_pago_archivo' => $this->pdf('p.pdf'),
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->pdm('pago')->where('pago_id_solicitud', $solicitudId)->count());
        $this->assertSame(1, $this->pdm('documentos_solicitud')->where('dosc_id_solicitud', $solicitudId)->count());
        $verificar('documentos');

        // 9. Compromiso → evento CA.
        $this->actingAs($user)->post('/registro/compromiso/guardar', ['compromiso_aceptado' => '1'])->assertRedirect(route('registro.completado'));
        $this->assertSame(['BO', 'FA', 'CA'], $this->eventos($solicitudId));
        $verificar('compromiso');

        // 10. Envío → evento EN.
        $this->actingAs($user)->post('/registro/completado/confirmar')->assertSessionHas('success', 'Solicitud enviada correctamente.');
        $this->assertSame(['BO', 'FA', 'CA', 'EN'], $this->eventos($solicitudId));
        $verificar('envio');

        // 11. Acuse y panel siguen funcionando (leen a través del modo activo).
        $this->actingAs($user)->get("/registro/acuse/{$solicitudId}")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($user)->get('/panel')->assertOk();

        return [$user, $personaId, $solicitudId];
    }

    /** Refrendo con artículo 14 a partir del antecedente histórico ficticio. */
    protected function flujoRefrendo(callable $verificar): int
    {
        if (auth()->check()) {
            auth()->logout(); // el registro es solo para invitados
        }
        $this->registrar('FIRE800101HDFRRN05', 'refrendo@example.test')->assertRedirect();
        $user = User::where('email', 'refrendo@example.test')->firstOrFail();
        $this->actingAs($user)->post('/mis-datos/autorizar', ['confirmacion_datos' => '1']);
        $verificar('registro_refrendo');

        $this->actingAs($user)->post('/registro/certificacion', [
            'certificacion' => 'ANFECA', 'tipo_personal' => self::TC, 'area_certificacion' => 'Administración',
            'numero_certificado_anterior' => '1000022',
        ])->assertRedirect(route('registro.refrendo'));
        $personaId = (int) $this->pdm('usuario')->where('usua_id_usuario', $user->id)->value('usua_id_persona');
        $s = $this->pdm('solicitud')->where('soli_id_persona', $personaId)->first();
        $this->assertSame('refrendo', $s->soli_tipo_tramite);
        $this->assertSame(1, (int) $s->soli_id_historico_importacion);
        $this->assertSame('1000022', $s->soli_numero_certificado);
        $verificar('certificacion_refrendo');

        $this->actingAs($user)->post('/registro/refrendo', ['aplica_articulo_14' => '1', 'inciso_articulo_14' => '14.1'])
            ->assertRedirect(route('registro.formacion'));
        $s = $this->pdm('solicitud')->where('soli_id_solicitud', $s->soli_id_solicitud)->first();
        $this->assertTrue($s->soli_art14);
        $this->assertFalse($s->soli_art13);
        $this->assertSame('14.1', $this->pdm('articulo_subindice')->where('arce_id_articulo', $s->soli_id_articulo)->value('arce_subindice'));
        $this->assertSame(1, $this->pdm('solicitud_refrendo')->where('sore_id_solicitud', $s->soli_id_solicitud)->count());
        $verificar('refrendo');

        // Etapa 1: el art. 14 también exige formación académica antes de documentos.
        $this->actingAs($user)->get('/registro/documentos-probatorios')->assertRedirect(route('registro.formacion'));

        return (int) $s->soli_id_solicitud;
    }
}
