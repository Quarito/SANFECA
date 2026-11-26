<?php

namespace Tests\Feature;

use App\Http\Controllers\RegistroCompletadoController;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use App\Services\Coordinacion\SeguimientoRegistros;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/** Bloque D · Seguimiento de registros y borradores (27 casos) y seguridad documental de borradores. */
class SeguimientoRegistrosTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refreshDatabaseLaravel;
    }

    private array $u = [];
    private array $sol = [];
    private array $coord = [];
    private array $inst = [];
    private array $zona = [];
    private int $n = 0;

    public function refreshDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('SEGURIDAD: requiere SQLite en memoria.');
        }
        $this->refreshDatabaseLaravel();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutVite();
        Carbon::setTestNow('2026-11-25 12:00:00');

        $this->zona[3] = DB::table('zonas')->insertGetId(['siglas' => 'Z3', 'descripcion' => 'Zona 3 - Centro']);
        $this->inst['Z3'] = DB::table('instituciones_afiliadas')->insertGetId(['numero_afiliacion' => 'S3', 'nombre' => 'Institución Zona 3', 'zona_id' => $this->zona[3], 'zona_anfeca' => 'Zona 3 - Centro']);
        $this->inst['TXT6'] = DB::table('instituciones_afiliadas')->insertGetId(['numero_afiliacion' => 'S6', 'nombre' => 'Institución texto 6', 'zona_anfeca' => 'Zona 6 - Sur']);

        // 1/2: cuenta recién creada, sin datos (datos personales incompletos = sin registro guardado).
        $this->persona('NUEVA');
        // 3: datos capturados sin autorización.
        $this->persona('SIN_AUT', datos: true);
        // 4/5: datos autorizados, certificación pendiente.
        $this->persona('AUT', datos: true, autorizados: true);
        // 6: formación incompleta (sin documentos académicos).
        $this->persona('FORM', datos: true, autorizados: true, solicitud: ['inst' => 'Z3', 'formacion' => 'incompleta']);
        // 7: documentos probatorios incompletos.
        $this->persona('DOCS', datos: true, autorizados: true, solicitud: ['inst' => 'Z3', 'formacion' => 'completa', 'docs' => ['constancia_antiguedad_archivo']]);
        // 8: refrendo por artículo 14 con adicionales pendientes.
        $this->persona('ART14', datos: true, autorizados: true, solicitud: ['inst' => 'Z3', 'tipo' => 'refrendo', 'art14' => '14.2', 'formacion' => 'completa',
            'docs' => ['constancia_antiguedad_archivo', 'comprobante_pago_archivo']]);
        // 9: compromiso pendiente.
        $this->persona('COMP', datos: true, autorizados: true, solicitud: ['inst' => 'TXT6', 'formacion' => 'completa', 'docs' => ['constancia_antiguedad_archivo', 'comprobante_pago_archivo']]);
        // 10: listo para envío.
        $this->persona('LISTO', datos: true, autorizados: true, solicitud: ['inst' => 'Z3', 'formacion' => 'completa',
            'docs' => ['constancia_antiguedad_archivo', 'comprobante_pago_archivo'], 'compromiso' => true]);
        // 11: enviada.
        $this->persona('ENV', datos: true, autorizados: true, solicitud: ['inst' => 'Z3', 'formacion' => 'completa',
            'docs' => ['constancia_antiguedad_archivo', 'comprobante_pago_archivo'], 'compromiso' => true, 'estado' => 'enviado']);
        // 12: usuario con varios trámites (primera vez enviada y nuevo refrendo en borrador).
        $this->persona('VARIOS', datos: true, autorizados: true, solicitud: ['inst' => 'Z3', 'formacion' => 'completa',
            'docs' => ['constancia_antiguedad_archivo', 'comprobante_pago_archivo'], 'compromiso' => true, 'estado' => 'enviado']);
        $this->tramite('VARIOS_2', $this->u['VARIOS'], ['inst' => 'Z3', 'tipo' => 'refrendo', 'formacion' => 'incompleta']);
        // 14/15: solicitud sin institución (sin zona).
        $this->persona('SIN_INST', datos: true, autorizados: true, solicitud: ['inst' => null, 'formacion' => 'sin_institucion']);

        foreach (['nacional' => ['coordinador_nacional', null], 'z3' => ['coordinador_zona', 3], 'sin' => ['coordinador_zona', null]] as $k => [$rol, $z]) {
            $c = new User();
            $c->forceFill(['name' => "Coord {$k}", 'email' => "c.{$k}@anfeca.test", 'password' => Hash::make('ContrasenaCoordinacion1'), 'rol' => $rol])->save();
            if ($z) {
                DB::table('coordinador_zona')->insert(['user_id' => $c->id, 'zona_id' => $this->zona[$z]]);
            }
            $this->coord[$k] = $c;
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function persona(string $k, bool $datos = false, bool $autorizados = false, ?array $solicitud = null): void
    {
        $this->n++;
        $u = User::create(['name' => "Cuenta {$k}", 'email' => Str::lower($k).'@example.test', 'password' => Hash::make('ContrasenaFicticia1')]);
        $this->u[$k] = $u->id;
        if ($datos) {
            DB::table('datos_solicitantes')->insert([
                'user_id' => $u->id, 'curp' => sprintf('SEGU8001%02dHDFRRN0%d', $this->n, $this->n % 10), 'rfc' => sprintf('SEGU8001%02dA%d', $this->n, $this->n % 10),
                'nombres' => "Persona {$k}", 'apellido_paterno' => 'Ficticia', 'genero' => 'Femenino', 'calle' => 'C', 'numero_exterior' => '1',
                'codigo_postal' => '01000', 'entidad_federativa' => 'Ciudad de México', 'colonia' => 'C', 'municipio' => 'M',
                'correo_electronico' => Str::lower($k).'@example.test', 'telefono_celular' => '5500000000', 'datos_autorizados' => $autorizados,
                'created_at' => now()->subDays(20), 'updated_at' => now()->subDays(20),
            ]);
        }
        if ($solicitud !== null) {
            $this->tramite($k, $u->id, $solicitud);
        }
    }

    private function tramite(string $k, int $userId, array $o): void
    {
        $o += ['tipo' => 'inicial', 'estado' => 'borrador', 'compromiso' => false, 'docs' => [], 'art14' => null];
        $hist = null;
        if ($o['tipo'] === 'refrendo') {
            $hist = DB::table('certificados_historicos')->insertGetId(['nombre_historico' => "Hist {$k}", 'certificacion' => 'ANFECA', 'numero_certificado' => "CERT-{$k}"]);
        }
        $id = DB::table('solicitudes_certificacion')->insertGetId([
            'user_id' => $userId, 'tipo_tramite' => $o['tipo'], 'certificacion' => 'ANFECA', 'estado' => $o['estado'],
            'tipo_personal' => 'Profesoras y profesores de Tiempo Completo', 'area_certificacion' => 'Administración',
            'certificado_historico_id' => $hist, 'compromiso_aceptado' => $o['compromiso'], 'compromiso_aceptado_en' => $o['compromiso'] ? now()->subDays(2) : null,
            'fecha_envio' => $o['estado'] === 'enviado' ? now()->subDay() : null, 'created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10),
        ]);
        $this->sol[$k] = $id;
        $archivo = function (string $c) use ($id) {
            $r = "anfeca/documentos/solicitudes/{$id}/{$c}.pdf";
            Storage::disk('local')->put($r, "%PDF-1.4 ficticio {$c}");

            return $r;
        };
        if ($o['tipo'] === 'refrendo') {
            DB::table('datos_refrendo')->insert(['solicitud_id' => $id, 'aplica_articulo_14' => $o['art14'] !== null, 'inciso_articulo_14' => $o['art14'],
                'created_at' => now()->subDays(9), 'updated_at' => now()->subDays(9)]);
        }
        $f = ['solicitud_id' => $id, 'nivel_origen' => 'Licenciatura', 'titulo_origen' => 'Administración', 'nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Administración',
            'institucion_afiliada_id' => $o['inst'] ? $this->inst[$o['inst']] : null, 'created_at' => now()->subDays(8), 'updated_at' => now()->subDays(8)];
        if ($o['formacion'] === 'completa') {
            foreach (['titulo_origen_archivo', 'cedula_origen_archivo', 'titulo_ultimo_grado_archivo', 'cedula_ultimo_grado_archivo'] as $c) {
                $f[$c] = $archivo($c);
            }
            $f['formacion_completada_at'] = now()->subDays(7);
        }
        DB::table('formaciones_academicas_solicitud')->insert($f);
        if ($o['docs'] !== []) {
            $d = ['solicitud_id' => $id, 'link' => 'https://drive.example.test/carpeta', 'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5)];
            foreach ($o['docs'] as $c) {
                $d[$c] = $archivo($c);
            }
            DB::table('documentos_probatorios_solicitud')->insert($d);
        }
    }

    private function api(string $quien, array $p = [])
    {
        return $this->actingAs($this->coord[$quien])->getJson('/coordinacion/api/seguimiento?'.http_build_query($p + ['por_pagina' => 50, 'orden' => 'solicitante', 'direccion' => 'asc']));
    }

    private function fila(string $k, string $quien = 'nacional'): array
    {
        return app(SeguimientoRegistros::class)->filas($this->coord[$quien])->firstWhere('usuario_id', $this->u[$k]);
    }

    // ------------------------------------------------------------- 1–11 etapas

    public function test_01_a_11_etapa_actual_y_clasificacion_por_requisito_pendiente(): void
    {
        $esperado = [
            'NUEVA' => ['sin_iniciar', 'datos'],
            'SIN_AUT' => ['en_proceso_sin_solicitud', 'autorizacion'],
            'AUT' => ['en_proceso_sin_solicitud', 'certificacion'],
            'FORM' => ['borrador', 'formacion'],
            'DOCS' => ['borrador', 'probatorios'],
            'ART14' => ['borrador', 'adicionales'],
            'COMP' => ['borrador', 'compromiso'],
            'LISTO' => ['listo_para_envio', 'envio'],
            'ENV' => ['enviada', null],
        ];
        foreach ($esperado as $k => [$clasificacion, $etapa]) {
            $f = $this->fila($k);
            $this->assertSame([$clasificacion, $etapa], [$f['clasificacion'], $f['etapa_actual']], $k);
        }
        $this->assertSame('en_proceso', $this->fila('FORM')['etapas']['formacion']);
        $this->assertSame('en_proceso', $this->fila('DOCS')['etapas']['probatorios']);
        $this->assertSame('Enviada', $this->fila('ENV')['etapa_actual_etiqueta']);
    }

    public function test_10_listo_para_envio_coincide_con_las_reglas_reales_del_envio(): void
    {
        $requisitos = new ReflectionMethod(RegistroCompletadoController::class, 'requisitos');
        $controlador = app(RegistroCompletadoController::class);
        foreach (['FORM', 'DOCS', 'ART14', 'COMP', 'LISTO', 'VARIOS_2', 'SIN_INST'] as $k) {
            $s = SolicitudCertificacion::findOrFail($this->sol[$k]);
            $real = !in_array(false, $requisitos->invoke($controlador, $s), true) && (bool) $s->compromiso_aceptado;
            $fila = app(SeguimientoRegistros::class)->filas($this->coord['nacional'])->firstWhere('folio', $s->id)
                ?? app(SeguimientoRegistros::class)->filas($this->coord['nacional'])->firstWhere('usuario_id', $s->user_id);
            $this->assertSame($real, $fila['clasificacion'] === 'listo_para_envio' && $fila['folio'] === $s->id, "folio {$k}");
        }
    }

    // ------------------------------------------------------------- 12–17

    public function test_12_usuario_con_varios_tramites_y_nuevo_refrendo(): void
    {
        $f = $this->fila('VARIOS');
        $this->assertSame($this->sol['VARIOS_2'], $f['folio'], 'El trámite actual es el nuevo borrador de refrendo.');
        $this->assertSame('refrendo', $f['tipo_tramite']);
        $this->assertCount(1, $f['historial']);
        $this->assertSame($this->sol['VARIOS'], $f['historial'][0]['folio']);
        $this->assertSame(2, $f['tramites']);
        $html = $this->actingAs($this->coord['nacional'])->get("/coordinacion/seguimiento/{$this->u['VARIOS']}")->assertOk()->getContent();
        $this->assertStringContainsString('Otros trámites del solicitante', $html);
        $this->assertStringContainsString('<td>'.$this->sol['VARIOS'].'</td>', $html, 'La primera vez enviada aparece en el historial.');
    }

    public function test_13_14_15_sin_folio_sin_institucion_sin_zona(): void
    {
        $datos = collect($this->api('nacional')->assertOk()->json('datos'))->keyBy('correo');
        $this->assertNull($datos['nueva@example.test']['folio'], 'Sin folio (no se sustituye por el id de usuario).');
        $this->assertNotSame($this->u['NUEVA'], $datos['nueva@example.test']['folio']);
        $this->assertNull($datos['sin_inst@example.test']['institucion']);
        $this->assertFalse($datos['sin_inst@example.test']['zona_clasificada']);
        $this->assertSame('Sin clasificar', $datos['sin_inst@example.test']['zona']);
        $this->assertSame('Zona 6 - Sur', $datos['comp@example.test']['zona'], 'Zona resuelta del dato institucional.');
        $html = $this->actingAs($this->coord['nacional'])->get("/coordinacion/seguimiento/{$this->u['NUEVA']}")->assertOk()->getContent();
        $this->assertStringContainsString('Sin folio', $html);
    }

    public function test_16_17_avance_y_etapas_no_aplicables(): void
    {
        $nueva = $this->fila('NUEVA');
        $this->assertSame([1, 7, 14], [$nueva['completadas'], $nueva['total'], $nueva['avance']]);
        $comp = $this->fila('COMP');
        $this->assertSame(['no_aplica', 'no_aplica', 'no_aplica'], [$comp['etapas']['certificado'], $comp['etapas']['refrendo'], $comp['etapas']['adicionales']]);
        $this->assertSame([6, 7, 85], [$comp['completadas'], $comp['total'], $comp['avance']]);
        $art = $this->fila('ART14');
        $this->assertSame(10, $art['total'], 'Refrendo por art. 14: certificado, refrendo y adicionales aplican.');
        $this->assertSame([8, 80], [$art['completadas'], $art['avance']]);
        $listo = $this->fila('LISTO');
        $this->assertSame([100, 'pendiente'], [$listo['avance'], $listo['etapas']['envio']], '100 % de captura ≠ enviada.');
    }

    // ------------------------------------------------------------- 18–22

    public function test_18_19_20_filtros_por_etapa_zona_y_ultimo_avance(): void
    {
        $correos = fn (array $p) => collect($this->api('nacional', $p)->assertOk()->json('datos'))->pluck('correo')->sort()->values()->all();
        // Formación pendiente: FORM, el nuevo refrendo de VARIOS y la solicitud sin institución.
        $this->assertSame(['form@example.test', 'sin_inst@example.test', 'varios@example.test'], $correos(['etapa' => 'formacion']));
        $this->assertSame(['comp@example.test'], $correos(['zona' => 'z6']));
        $this->assertContains('sin_inst@example.test', $correos(['zona' => 'sin_zona']));
        $this->assertContains('nueva@example.test', $correos(['zona' => 'sin_zona']));
        // Último avance: datos sin solicitud se capturaron hace 20 días; ENV no cuenta (enviada).
        $this->assertSame(['aut@example.test', 'sin_aut@example.test'], $correos(['sin_avance' => '15']));
        $this->assertNotContains('env@example.test', $correos(['sin_avance' => '3']));
        $this->assertNotContains('nueva@example.test', $correos(['sin_avance' => '3']), 'Sin fecha confiable de avance no se clasifica por inactividad.');
        $this->assertSame([], $correos(['avance_desde' => '2026-11-25']));
        $this->assertSame(['nueva@example.test'], $correos(['q' => 'NUEVA']));
        $this->assertContains('comp@example.test', $correos(['q' => (string) $this->sol['COMP']]), 'Búsqueda por folio (también coincide con texto).');
        $this->api('nacional', ['sin_avance' => '4'])->assertStatus(422);
        $this->api('nacional', ['etapa' => 'inventada'])->assertStatus(422);
    }

    public function test_21_paginacion_y_consecutivos(): void
    {
        $p1 = $this->api('nacional', ['por_pagina' => 10, 'pagina' => 1])->json();
        $p2 = $this->api('nacional', ['por_pagina' => 10, 'pagina' => 2])->json();
        $this->assertSame(range(1, 10), array_column($p1['datos'], 'num'));
        $this->assertSame(range(11, $p1['meta']['total']), array_column($p2['datos'], 'num'));
        $this->assertSame(range(1, 2), array_column($this->api('nacional', ['estado' => 'en_proceso_sin_solicitud'])->json('datos'), 'num'));
    }

    public function test_22_indicadores_sin_duplicidades(): void
    {
        $s = $this->actingAs($this->coord['nacional'])->getJson('/coordinacion/api/indicadores')->assertOk()->json('seguimiento');
        $personas = collect($s['personas'])->keyBy('clave');
        $tramites = collect($s['tramites'])->keyBy('clave');
        $this->assertSame(11, $personas['registradas']['valor'], 'Cada persona una vez, aunque tenga 2 trámites.');
        $this->assertSame([1, 2, 1], [$personas['sin_iniciar']['valor'], $personas['en_proceso']['valor'], $personas['listas']['valor']]);
        $this->assertSame(9, $tramites['borradores']['valor'] + $tramites['enviadas']['valor'], 'Cada solicitud una vez.');
        $this->assertSame(2, $tramites['enviadas']['valor']);
        $this->assertSame($tramites['primera_vez']['valor'] + $tramites['refrendos']['valor'], 9);
        $this->assertSame(11, collect($s['por_zona'])->sum('valor'));
        $this->assertStringContainsString('/coordinacion/seguimiento?estado=sin_iniciar', $personas['sin_iniciar']['enlace']);
    }

    // ------------------------------------------------------------- 23–27 seguridad

    public function test_23_no_se_modifican_borradores_ni_existen_rutas_de_escritura(): void
    {
        $antes = [DB::table('solicitudes_certificacion')->orderBy('id')->get()->toJson(), DB::table('datos_solicitantes')->orderBy('id')->get()->toJson(),
            DB::table('formaciones_academicas_solicitud')->orderBy('id')->get()->toJson(), DB::table('documentos_probatorios_solicitud')->orderBy('id')->get()->toJson()];
        $n = $this->coord['nacional'];
        $this->actingAs($n)->get('/coordinacion/seguimiento')->assertOk();
        $this->api('nacional');
        foreach ($this->u as $id) {
            $this->actingAs($n)->get("/coordinacion/seguimiento/{$id}")->assertOk();
        }
        foreach (['post', 'put', 'patch', 'delete'] as $m) {
            $this->assertContains($this->actingAs($n)->{$m}("/coordinacion/seguimiento/{$this->u['FORM']}")->getStatusCode(), [404, 405]);
            $this->assertContains($this->actingAs($n)->{$m}('/coordinacion/api/seguimiento')->getStatusCode(), [404, 405]);
        }
        $rutas = collect(app('router')->getRoutes())->filter(fn ($r) => str_contains($r->uri(), 'seguimiento'));
        $this->assertSame([], $rutas->flatMap(fn ($r) => array_diff($r->methods(), ['GET', 'HEAD']))->values()->all());
        $this->assertSame($antes, [DB::table('solicitudes_certificacion')->orderBy('id')->get()->toJson(), DB::table('datos_solicitantes')->orderBy('id')->get()->toJson(),
            DB::table('formaciones_academicas_solicitud')->orderBy('id')->get()->toJson(), DB::table('documentos_probatorios_solicitud')->orderBy('id')->get()->toJson()]);
    }

    public function test_24_no_se_consultan_ni_descargan_pdf_de_borradores(): void
    {
        $n = $this->coord['nacional'];
        $this->actingAs($n)->get("/coordinacion/solicitudes/{$this->sol['LISTO']}/documentos/titulo_origen_archivo")->assertForbidden();
        $this->actingAs($n)->get("/coordinacion/solicitudes/{$this->sol['LISTO']}/documentos/titulo_origen_archivo?descargar=1")->assertForbidden();
        $html = $this->actingAs($n)->get("/coordinacion/solicitudes/{$this->sol['LISTO']}")->assertOk()->getContent();
        $this->assertStringNotContainsString('data-ver=', $html, 'Sin «Ver» activo en borradores.');
        $this->assertStringNotContainsString('/documentos/titulo_origen_archivo"', $html);
        // Enviada: sí, con encabezados de visualización en línea y sin caché.
        $r = $this->actingAs($n)->get("/coordinacion/solicitudes/{$this->sol['ENV']}/documentos/titulo_origen_archivo")->assertOk();
        $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', $r->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('anfeca/documentos', $r->headers->get('Content-Disposition'), 'No expone la ruta física.');
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertStringStartsWith('attachment', $this->actingAs($n)->get("/coordinacion/solicitudes/{$this->sol['ENV']}/documentos/titulo_origen_archivo?descargar=1")->headers->get('Content-Disposition'));
    }

    public function test_25_no_se_registran_revisiones_en_borradores(): void
    {
        config(['anfeca_coordinacion.revision_documental' => true]);
        $n = $this->coord['nacional'];
        $d = $this->actingAs($n)->getJson("/coordinacion/solicitudes/{$this->sol['LISTO']}/documentos/titulo_origen_archivo/revisiones")->assertOk()->json();
        $this->actingAs($n)->postJson("/coordinacion/solicitudes/{$this->sol['LISTO']}/documentos/titulo_origen_archivo/revisiones", [
            'resultado' => 'validado', 'revision_vigente_id' => null, 'version' => $d['version']['token'], 'clave_idempotencia' => $d['clave_idempotencia'],
        ])->assertStatus(409)->assertJson(['motivo' => 'estado']);
        $this->assertSame(0, DB::table('revisiones_documentales')->count());
    }

    public function test_26_acceso_directo_no_autorizado(): void
    {
        $z3 = collect($this->api('z3')->assertOk()->json('datos'));
        $this->assertNotContains('nueva@example.test', $z3->pluck('correo'), 'Sin trámite: solo coordinación nacional.');
        $this->assertNotContains('comp@example.test', $z3->pluck('correo'), 'Zona solo textual: no concede acceso regional.');
        $this->assertNotContains('sin_inst@example.test', $z3->pluck('correo'));
        $this->assertContains('form@example.test', $z3->pluck('correo'));
        $this->actingAs($this->coord['z3'])->get("/coordinacion/seguimiento/{$this->u['NUEVA']}")->assertNotFound();
        $this->actingAs($this->coord['z3'])->get("/coordinacion/seguimiento/{$this->u['COMP']}")->assertNotFound();
        $this->assertSame([], $this->api('sin')->json('datos'), 'Coordinador sin zona asignada: nada.');
        $this->api('z3', ['zona' => 'z6'])->assertForbidden();
        $this->api('z3', ['zona' => 'sin_zona'])->assertForbidden();
        $this->actingAs(User::findOrFail($this->u['FORM']))->get('/coordinacion/seguimiento')->assertForbidden();
        $this->actingAs(User::findOrFail($this->u['FORM']))->getJson('/coordinacion/api/seguimiento')->assertForbidden();
        auth()->logout();
        $this->get('/coordinacion/seguimiento')->assertRedirect('/login');
        $ind = $this->actingAs($this->coord['z3'])->getJson('/coordinacion/api/indicadores')->json('seguimiento');
        $this->assertSame(collect($this->api('z3')->json('datos'))->count(), collect($ind['personas'])->firstWhere('clave', 'registradas')['valor']);
    }

    public function test_27_transicion_de_borrador_a_enviada(): void
    {
        $this->assertSame('listo_para_envio', $this->fila('LISTO')['clasificacion']);
        DB::table('solicitudes_certificacion')->where('id', $this->sol['LISTO'])->update(['estado' => 'enviado', 'fecha_envio' => now()]);
        $f = $this->fila('LISTO');
        $this->assertSame(['enviada', 'completada', 'Enviada'], [$f['clasificacion'], $f['etapas']['envio'], $f['etapa_actual_etiqueta']]);
        $this->assertSame(now()->toDateTimeString(), $f['ultimo_avance']->toDateTimeString());
    }

    public function test_menu_y_pantallas_con_estandares(): void
    {
        $html = $this->actingAs($this->coord['nacional'])->get('/coordinacion/seguimiento')->assertOk()->getContent();
        $this->assertStringContainsString('Seguimiento de registros', $html);
        $this->assertStringContainsString('id="vue-coordinacion-seguimiento"', $html);
        $menu = $this->actingAs($this->coord['nacional'])->get('/coordinacion')->getContent();
        $this->assertStringContainsString('Seguimiento de registros', $menu);
        $this->assertStringContainsString(route('coordinacion.solicitudes'), $menu, 'Se conserva «Solicitudes».');
        $vue = file_get_contents(resource_path('js/components/SeguimientoRegistros.vue'));
        $this->assertMatchesRegularExpression('#<th scope="col" class="col-num"[^>]*>Núm\.</th>#', $vue);
        $this->assertStringContainsString("f.folio ?? 'Sin folio'", $vue);
        $detalle = $this->actingAs($this->coord['nacional'])->get("/coordinacion/seguimiento/{$this->u['ART14']}")->getContent();
        $this->assertSame(11, substr_count($detalle, 'class="seg-paso'), 'Línea de progreso con todas las etapas del flujo.');
        $this->assertStringContainsString('data-estado="no_aplica"', $this->actingAs($this->coord['nacional'])->get("/coordinacion/seguimiento/{$this->u['COMP']}")->getContent());
        $this->assertStringContainsString('class="coord-fichas"', $detalle);
    }
}
