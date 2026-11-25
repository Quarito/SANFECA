<?php

namespace Tests\Feature;

use App\Models\RevisionDocumental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\TestCase;

/**
 * Fase 6.3.1 · Revisión documental funcional (matriz de 27 casos + casos adicionales).
 * Solo SQLite en memoria y disco falso: la migración PROPUESTA se aplica aquí de forma aislada.
 */
class RevisionDocumentalTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refreshDatabaseLaravel;
    }

    private const PROPUESTA = 'database/migrations_propuestas/2026_11_26_000000_create_revisiones_documentales_table.php';

    private array $zona = [];
    private array $inst = [];
    private array $sol = [];
    private array $coord = [];
    private User $solicitante;

    public function refreshDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('SEGURIDAD: las pruebas de revisión documental requieren SQLite en memoria.');
        }
        $this->refreshDatabaseLaravel();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutVite();
        config(['anfeca_coordinacion.revision_documental' => true]);
        (require base_path(self::PROPUESTA))->up();

        foreach (['ZR1', 'ZR2'] as $s) {
            $this->zona[$s] = DB::table('zonas')->insertGetId(['siglas' => $s, 'descripcion' => "Zona ficticia {$s}"]);
            $this->inst[$s] = DB::table('instituciones_afiliadas')->insertGetId(['numero_afiliacion' => "I{$s}", 'nombre' => "Institución {$s}", 'zona_id' => $this->zona[$s]]);
        }
        $this->inst['SIN'] = DB::table('instituciones_afiliadas')->insertGetId(['numero_afiliacion' => 'ISIN', 'nombre' => 'Institución sin zona', 'zona_id' => null]);

        $this->crear('E1', 'ZR1', 'enviado', 'inicial');
        $this->crear('E2', 'ZR2', 'enviado', 'inicial');
        $this->crear('SIN', 'SIN', 'enviado', 'inicial');
        $this->crear('BORR', 'ZR1', 'borrador', 'inicial');
        $this->crear('REF', 'ZR1', 'enviado', 'refrendo');
        $this->solicitante = User::where('email', 'sol.E1@example.test')->firstOrFail();

        foreach (['nacional' => ['coordinador_nacional', null], 'z1' => ['coordinador_zona', 'ZR1'], 'z1b' => ['coordinador_zona', 'ZR1'], 'z2' => ['coordinador_zona', 'ZR2']] as $k => [$rol, $z]) {
            $u = new User();
            $u->forceFill(['name' => "Coordinación {$k}", 'email' => "coord.{$k}@anfeca.test", 'password' => Hash::make('ContrasenaCoordinacion1'), 'rol' => $rol])->save();
            if ($z) {
                DB::table('coordinador_zona')->insert(['user_id' => $u->id, 'zona_id' => $this->zona[$z]]);
            }
            $this->coord[$k] = $u;
        }
    }

    /** Solicitud ficticia con 4 documentos cargados y 2 sin cargar (cédulas). */
    private function crear(string $clave, string $inst, string $estado, string $tipo): void
    {
        $u = User::create(['name' => "Solicitante {$clave}", 'email' => "sol.{$clave}@example.test", 'password' => Hash::make('ContrasenaFicticia1')]);
        $id = DB::table('solicitudes_certificacion')->insertGetId([
            'user_id' => $u->id, 'tipo_tramite' => $tipo, 'certificacion' => 'ANFECA', 'estado' => $estado,
            'tipo_personal' => 'Profesoras y profesores de Tiempo Completo', 'area_certificacion' => 'Administración',
            'fecha_envio' => $estado === 'enviado' ? now() : null, 'compromiso_aceptado' => $estado === 'enviado',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $ruta = fn (string $n) => tap("anfeca/documentos/solicitudes/{$id}/{$n}.pdf", fn ($r) => Storage::disk('local')->put($r, "%PDF-1.4 ficticio {$clave} {$n}"));
        DB::table('formaciones_academicas_solicitud')->insert([
            'solicitud_id' => $id, 'nivel_origen' => 'Licenciatura', 'titulo_origen' => 'Administración',
            'institucion_afiliada_id' => $this->inst[$inst],
            'titulo_origen_archivo' => $ruta('titulo'), 'titulo_ultimo_grado_archivo' => $ruta('titulo_ug'),
        ]);
        DB::table('documentos_probatorios_solicitud')->insert([
            'solicitud_id' => $id, 'comprobante_pago_archivo' => $ruta('pago'), 'constancia_antiguedad_archivo' => $ruta('antiguedad'),
        ]);
        $this->sol[$clave] = $id;
    }

    private function url(string $sol, string $campo = ''): string
    {
        $id = $this->sol[$sol];

        return $campo === '' ? "/coordinacion/solicitudes/{$id}/revision" : "/coordinacion/solicitudes/{$id}/documentos/{$campo}/revisiones";
    }

    private function abrir(string $quien, string $sol, string $campo): array
    {
        return $this->actingAs($this->coord[$quien])->getJson($this->url($sol, $campo))->assertOk()->json();
    }

    /** Abre el documento (GET) y registra con los datos de control obtenidos. */
    private function revisar(string $quien, string $sol, string $campo, array $datos = [], ?array $abierto = null)
    {
        $abierto ??= $this->abrir($quien, $sol, $campo);

        return $this->actingAs($this->coord[$quien])->postJson($this->url($sol, $campo), $datos + [
            'resultado' => 'validado',
            'observaciones' => null,
            'motivo_cambio' => null,
            'revision_vigente_id' => $abierto['vigente']['id'] ?? null,
            'version' => $abierto['version']['token'],
            'clave_idempotencia' => $abierto['clave_idempotencia'],
        ]);
    }

    private function huellaOriginales(): array
    {
        $tablas = [];
        foreach (['solicitudes_certificacion', 'formaciones_academicas_solicitud', 'documentos_probatorios_solicitud', 'users'] as $t) {
            $tablas[$t] = md5(json_encode(DB::table($t)->orderBy('id')->get()));
        }
        $archivos = collect(Storage::disk('local')->allFiles('anfeca'))->sort()->mapWithKeys(fn ($f) => [$f => hash('sha256', Storage::disk('local')->get($f))])->all();

        return [$tablas, $archivos];
    }

    // ===================================================================== 1–3

    public function test_01_validar_documento_cargado_con_datos_fijados_en_el_servidor(): void
    {
        $r = $this->revisar('z1', 'E1', 'comprobante_pago_archivo', ['coordinador_id' => $this->coord['z2']->id, 'revisado_en' => '2000-01-01'])
            ->assertCreated()->json('revision');
        $fila = RevisionDocumental::findOrFail($r['id']);
        $contenido = Storage::disk('local')->get("anfeca/documentos/solicitudes/{$this->sol['E1']}/pago.pdf");

        $this->assertSame((int) $this->coord['z1']->id, (int) $fila->coordinador_id, 'El coordinador sale de la sesión, no del formulario.');
        $this->assertSame('Coordinación z1', $fila->coordinador_nombre);
        $this->assertSame('coordinador_zona', $fila->coordinador_rol);
        $this->assertSame(hash('sha256', $contenido), $fila->version_sha256);
        $this->assertSame(strlen($contenido), $fila->version_bytes);
        $this->assertSame('cargado', $fila->archivo_estado);
        $this->assertNotSame('2000', $fila->revisado_en->format('Y'));
    }

    public function test_02_no_se_valida_un_documento_sin_archivo_ni_no_localizado(): void
    {
        $this->revisar('z1', 'E1', 'cedula_origen_archivo')->assertStatus(422)->assertJsonValidationErrors('resultado');
        Storage::disk('local')->delete("anfeca/documentos/solicitudes/{$this->sol['E1']}/titulo.pdf");
        $this->revisar('z1', 'E1', 'titulo_origen_archivo')->assertStatus(422)->assertJsonValidationErrors('resultado');
        $this->assertSame(0, RevisionDocumental::count());
    }

    public function test_03_observaciones_obligatorias_y_limite_de_1000(): void
    {
        foreach (['con_observaciones', 'no_valido'] as $r) {
            $this->revisar('z1', 'E1', 'comprobante_pago_archivo', ['resultado' => $r])->assertStatus(422)->assertJsonValidationErrors('observaciones');
            $this->revisar('z1', 'E1', 'comprobante_pago_archivo', ['resultado' => $r, 'observaciones' => '   corta   '])->assertStatus(422);
        }
        $this->revisar('z1', 'E1', 'comprobante_pago_archivo', ['resultado' => 'no_valido', 'observaciones' => str_repeat('x', 1001)])
            ->assertStatus(422)->assertJsonValidationErrors('observaciones');
        $this->revisar('z1', 'E1', 'comprobante_pago_archivo', ['resultado' => 'no_valido', 'observaciones' => str_repeat('x', 1000)])->assertCreated();
        $this->assertSame(1, RevisionDocumental::count());
    }

    // ===================================================================== 4, 23

    public function test_04_23_guardado_parcial_y_consistencia_tras_cerrar_sesion(): void
    {
        $this->revisar('z1', 'E1', 'comprobante_pago_archivo')->assertCreated();
        $this->revisar('z1', 'E1', 'cedula_origen_archivo', ['resultado' => 'no_valido', 'observaciones' => 'El documento no fue cargado.'])->assertCreated();
        $antes = $this->actingAs($this->coord['z1'])->getJson($this->url('E1'))->json();

        Auth::logout();
        $this->flushSession();
        $this->getJson($this->url('E1'))->assertUnauthorized();

        $despues = $this->actingAs($this->coord['z1b'])->getJson($this->url('E1'))->assertOk()->json();
        $this->assertSame($antes['indicadores'], $despues['indicadores']);
        $this->assertSame([
            'aplicables' => 6, 'cargados' => 4, 'faltantes' => 2, 'por_revisar' => 4, 'validados' => 1,
            'con_observaciones' => 0, 'no_validos' => 1, 'porcentaje_revisado' => 33, 'porcentaje_validado' => 17,
        ], $despues['indicadores']);
        $this->assertSame('validado', $despues['documentos']['comprobante_pago_archivo']['estado']);
        $this->assertSame('no_aplica', $despues['documentos']['solicitud_articulo_14_archivo']['estado']);
    }

    // ===================================================================== 5, 6, 20

    public function test_05_06_historial_cambio_de_resultado_con_motivo_e_inmutabilidad(): void
    {
        $r1 = $this->revisar('z1', 'E1', 'comprobante_pago_archivo')->assertCreated()->json('revision.id');
        $this->revisar('z1', 'E1', 'comprobante_pago_archivo', ['resultado' => 'no_valido', 'observaciones' => 'El comprobante no corresponde.'])
            ->assertStatus(422)->assertJsonValidationErrors('motivo_cambio');
        $r2 = $this->revisar('z1', 'E1', 'comprobante_pago_archivo', ['resultado' => 'no_valido', 'observaciones' => 'El comprobante no corresponde.',
            'motivo_cambio' => 'Se verificó con la institución que el pago es de otro periodo.'])->assertCreated()->json('revision');

        $this->assertSame($r1, $r2['revision_anterior_id']);
        $h = $this->abrir('z1', 'E1', 'comprobante_pago_archivo');
        $this->assertSame([1, 2], array_column($h['historial'], 'num'), 'Historial con consecutivo «Núm.».');
        $this->assertSame(['validado', 'no_valido'], array_column($h['historial'], 'resultado'));
        $this->assertSame($r2['id'], $h['vigente']['id']);

        $fila = RevisionDocumental::findOrFail($r1);
        foreach ([fn () => $fila->forceFill(['observaciones' => 'Alterada maliciosamente'])->save(), fn () => $fila->delete()] as $accion) {
            try {
                $accion();
                $this->fail('El modelo debió impedir la modificación del historial.');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('validado', RevisionDocumental::findOrFail($r1)->resultado->value);
    }

    public function test_20_no_existen_rutas_de_actualizacion_ni_eliminacion(): void
    {
        $id = $this->revisar('z1', 'E1', 'comprobante_pago_archivo')->assertCreated()->json('revision.id');
        foreach (['put', 'patch', 'delete'] as $m) {
            $this->actingAs($this->coord['nacional'])->json($m, $this->url('E1', 'comprobante_pago_archivo'))->assertStatus(405);
            $this->actingAs($this->coord['nacional'])->json($m, $this->url('E1', 'comprobante_pago_archivo')."/{$id}")->assertNotFound();
        }
        $rutas = collect(app('router')->getRoutes())->filter(fn ($r) => str_contains($r->uri(), 'revision'));
        $this->assertSame([], $rutas->flatMap(fn ($r) => array_diff($r->methods(), ['GET', 'HEAD', 'POST']))->values()->all());
        $this->assertSame(1, RevisionDocumental::count());
    }

    // ===================================================================== 7, 8

    public function test_07_no_aplica_no_se_puede_revisar_ni_registrar(): void
    {
        $this->actingAs($this->coord['z1'])->getJson($this->url('E1', 'solicitud_articulo_14_archivo'))->assertNotFound();
        $this->actingAs($this->coord['z1'])->postJson($this->url('E1', 'solicitud_articulo_14_archivo'), ['resultado' => 'validado'])->assertNotFound();
        $this->revisar('z1', 'E1', 'comprobante_pago_archivo', ['resultado' => 'no_aplica', 'observaciones' => 'Intento de omitir un obligatorio.'])
            ->assertStatus(422)->assertJsonValidationErrors('resultado');
        $this->assertSame(0, RevisionDocumental::count());
    }

    public function test_08_adicionales_del_articulo_solo_en_refrendo_aplicable(): void
    {
        $id = $this->sol['REF'];
        $this->actingAs($this->coord['z1'])->getJson($this->url('REF', 'solicitud_articulo_14_archivo'))->assertNotFound();

        DB::table('datos_refrendo')->insert(['solicitud_id' => $id, 'aplica_articulo_14' => true, 'inciso_articulo_14' => '14.1']);
        $ruta = "anfeca/documentos/solicitudes/{$id}/art14.pdf";
        Storage::disk('local')->put($ruta, '%PDF-1.4 ficticio art14');
        DB::table('documentos_probatorios_solicitud')->where('solicitud_id', $id)->update(['solicitud_articulo_14_archivo' => $ruta]);

        $this->revisar('z1', 'REF', 'solicitud_articulo_14_archivo')->assertCreated();
        $ind = $this->actingAs($this->coord['z1'])->getJson($this->url('REF'))->json('indicadores');
        $this->assertSame(8, $ind['aplicables']);
        $this->assertSame(5, $ind['cargados']);
    }

    // ===================================================================== 9, 18, extras de versión

    public function test_09_nueva_version_vuelve_a_por_revisar_sin_trasladar_la_validacion(): void
    {
        $this->revisar('z1', 'E1', 'comprobante_pago_archivo')->assertCreated();
        $nueva = "anfeca/documentos/solicitudes/{$this->sol['E1']}/pago_v2.pdf";
        Storage::disk('local')->put($nueva, '%PDF-1.4 ficticio segunda version');
        DB::table('documentos_probatorios_solicitud')->where('solicitud_id', $this->sol['E1'])->update(['comprobante_pago_archivo' => $nueva]);

        $res = $this->actingAs($this->coord['z1'])->getJson($this->url('E1'))->json();
        $this->assertSame('por_revisar', $res['documentos']['comprobante_pago_archivo']['estado']);
        $this->assertTrue($res['documentos']['comprobante_pago_archivo']['vigente_de_otra_version']);
        $this->assertSame(0, $res['indicadores']['validados']);

        // Revisar la nueva versión con el mismo resultado no exige motivo; queda encadenada.
        $r = $this->revisar('z1', 'E1', 'comprobante_pago_archivo')->assertCreated()->json('revision');
        $this->assertSame('pago_v2.pdf', $r['version']['archivo']);
        $this->assertSame('validado', $this->actingAs($this->coord['z1'])->getJson($this->url('E1'))->json('documentos.comprobante_pago_archivo.estado'));
    }

    public function test_18_archivo_cambia_entre_la_consulta_y_el_guardado(): void
    {
        $abierto = $this->abrir('z1', 'E1', 'comprobante_pago_archivo');
        Storage::disk('local')->put("anfeca/documentos/solicitudes/{$this->sol['E1']}/pago.pdf", '%PDF-1.4 contenido sustituido');
        $this->revisar('z1', 'E1', 'comprobante_pago_archivo', [], $abierto)->assertStatus(409)->assertJson(['motivo' => 'version']);
        $this->assertSame(0, RevisionDocumental::count());
    }

    public function test_extra_cambio_de_ruta_con_la_misma_huella(): void
    {
        $abierto = $this->abrir('z1', 'E1', 'comprobante_pago_archivo');
        $original = "anfeca/documentos/solicitudes/{$this->sol['E1']}/pago.pdf";
        $copia = "anfeca/documentos/solicitudes/{$this->sol['E1']}/pago_copia.pdf";
        Storage::disk('local')->copy($original, $copia);
        DB::table('documentos_probatorios_solicitud')->where('solicitud_id', $this->sol['E1'])->update(['comprobante_pago_archivo' => $copia]);

        $this->assertSame($abierto['version']['sha256_corto'], $this->abrir('z1', 'E1', 'comprobante_pago_archivo')['version']['sha256_corto'], 'Misma huella.');
        $this->revisar('z1', 'E1', 'comprobante_pago_archivo', [], $abierto)->assertStatus(409)->assertJson(['motivo' => 'version']);
    }

    public function test_extra_cambio_de_estado_de_carga_sin_huella(): void
    {
        $abierto = $this->abrir('z1', 'E1', 'cedula_origen_archivo');          // no cargado
        $this->assertSame('no_cargado', $abierto['version']['estado']);
        DB::table('formaciones_academicas_solicitud')->where('solicitud_id', $this->sol['E1'])
            ->update(['cedula_origen_archivo' => "anfeca/documentos/solicitudes/{$this->sol['E1']}/inexistente.pdf"]);
        $this->assertSame('no_localizado', $this->abrir('z1', 'E1', 'cedula_origen_archivo')['version']['estado']);

        $this->revisar('z1', 'E1', 'cedula_origen_archivo', ['resultado' => 'no_valido', 'observaciones' => 'No se recibió el documento.'], $abierto)
            ->assertStatus(409)->assertJson(['motivo' => 'version']);
        $this->assertSame(0, RevisionDocumental::count());
    }

    public function test_16_version_falsificada_por_el_navegador(): void
    {
        $this->revisar('z1', 'E1', 'comprobante_pago_archivo', ['version' => str_repeat('a', 64)])->assertStatus(409)->assertJson(['motivo' => 'version']);
        $this->revisar('z1', 'E1', 'comprobante_pago_archivo', ['version' => 'no-es-hex'])->assertStatus(422);
    }

    public function test_extra_ver_con_version_obsoleta_responde_409(): void
    {
        $token = $this->abrir('z1', 'E1', 'comprobante_pago_archivo')['version']['token'];
        $base = "/coordinacion/solicitudes/{$this->sol['E1']}/documentos/comprobante_pago_archivo";
        $this->actingAs($this->coord['z1'])->get("{$base}?v={$token}")->assertOk();
        Storage::disk('local')->put("anfeca/documentos/solicitudes/{$this->sol['E1']}/pago.pdf", '%PDF-1.4 otro contenido');
        $this->actingAs($this->coord['z1'])->get("{$base}?v={$token}")->assertStatus(409);
    }

    // ===================================================================== 10–13, permisos

    public function test_10_otra_zona_recibe_404_sin_insercion(): void
    {
        $this->actingAs($this->coord['z2'])->getJson($this->url('E1'))->assertNotFound();
        $this->actingAs($this->coord['z2'])->getJson($this->url('E1', 'comprobante_pago_archivo'))->assertNotFound();
        $this->actingAs($this->coord['z2'])->postJson($this->url('E1', 'comprobante_pago_archivo'), ['resultado' => 'validado'])->assertNotFound();
        $this->assertSame(0, RevisionDocumental::count());
    }

    public function test_11_coordinacion_nacional_revisa_incluso_sin_zona(): void
    {
        $this->revisar('nacional', 'SIN', 'comprobante_pago_archivo')->assertCreated();
        $this->revisar('nacional', 'E2', 'comprobante_pago_archivo')->assertCreated();
        $this->actingAs($this->coord['z1'])->getJson($this->url('SIN'))->assertNotFound();
    }

    public function test_12_solicitante_e_invitado(): void
    {
        $this->actingAs($this->solicitante)->getJson($this->url('E1'))->assertForbidden();
        $this->actingAs($this->solicitante)->postJson($this->url('E1', 'comprobante_pago_archivo'), ['resultado' => 'validado'])->assertForbidden();
        Auth::logout();
        $this->getJson($this->url('E1'))->assertUnauthorized();
    }

    public function test_13_solicitud_en_borrador_responde_409(): void
    {
        $this->revisar('z1', 'BORR', 'comprobante_pago_archivo')->assertStatus(409)->assertJson(['motivo' => 'estado']);
        $html = $this->actingAs($this->coord['z1'])->get("/coordinacion/solicitudes/{$this->sol['BORR']}")->assertOk()->getContent();
        $this->assertStringNotContainsString('data-revisar=', $html, 'Sin botón «Revisar» activo en borrador.');
        $this->assertSame(0, RevisionDocumental::count());
    }

    public function test_extra_permisos_cambian_antes_de_un_reintento(): void
    {
        $abierto = $this->abrir('z1', 'E1', 'comprobante_pago_archivo');
        $this->revisar('z1', 'E1', 'comprobante_pago_archivo', [], $abierto)->assertCreated();

        DB::table('coordinador_zona')->where('user_id', $this->coord['z1']->id)->delete();   // pierde la zona
        $respuesta = $this->revisar('z1', 'E1', 'comprobante_pago_archivo', [], $abierto)->assertNotFound();
        $this->assertStringNotContainsString('Coordinación z1', $respuesta->getContent(), 'No se devuelve la revisión previa.');
        $this->actingAs($this->coord['z1'])->getJson($this->url('E1', 'comprobante_pago_archivo'))->assertNotFound();
        $this->assertSame(1, RevisionDocumental::count());
    }

    // ===================================================================== 14, 21, 22, idempotencia

    public function test_14_22_dos_coordinadores_sobre_la_misma_vigente(): void
    {
        $a = $this->abrir('z1', 'E1', 'comprobante_pago_archivo');
        $b = $this->abrir('z1b', 'E1', 'comprobante_pago_archivo');
        $this->revisar('z1', 'E1', 'comprobante_pago_archivo', [], $a)->assertCreated();
        $this->revisar('z1b', 'E1', 'comprobante_pago_archivo', ['resultado' => 'no_valido', 'observaciones' => 'Lo consulté al mismo tiempo.'], $b)
            ->assertStatus(409)->assertJson(['motivo' => 'concurrencia'])->assertJsonPath('vigente.coordinador', 'Coordinación z1');

        // Doble clic o dos pestañas del MISMO coordinador con claves distintas: también 409.
        $c = $this->abrir('z1', 'E1', 'cedula_origen_archivo');
        $d = $this->abrir('z1', 'E1', 'cedula_origen_archivo');
        $obs = ['resultado' => 'no_valido', 'observaciones' => 'Documento no cargado por el solicitante.'];
        $this->revisar('z1', 'E1', 'cedula_origen_archivo', $obs, $c)->assertCreated();
        $this->revisar('z1', 'E1', 'cedula_origen_archivo', $obs, $d)->assertStatus(409);
        $this->assertSame(2, RevisionDocumental::count());
    }

    public function test_21_reintento_identico_recupera_la_revision_sin_duplicar(): void
    {
        $abierto = $this->abrir('z1', 'E1', 'comprobante_pago_archivo');
        $primera = $this->revisar('z1', 'E1', 'comprobante_pago_archivo', ['observaciones' => '  Nota opcional  '], $abierto)->assertCreated()->json('revision');
        $reintento = $this->revisar('z1', 'E1', 'comprobante_pago_archivo', ['observaciones' => 'Nota opcional'], $abierto)->assertOk();
        $this->assertTrue($reintento->json('reintento'));
        $this->assertSame($primera['id'], $reintento->json('revision.id'));
        $this->assertSame(1, RevisionDocumental::count());
    }

    public function test_extra_clave_reutilizada_con_contenido_diferente_responde_409(): void
    {
        $abierto = $this->abrir('z1', 'E1', 'comprobante_pago_archivo');
        $this->revisar('z1', 'E1', 'comprobante_pago_archivo', [], $abierto)->assertCreated();

        $variantes = [
            'resultado' => ['resultado' => 'no_valido', 'observaciones' => 'Contenido distinto con la misma clave.'],
            'observaciones' => ['observaciones' => 'Otra observación distinta'],
            'documento' => null,
            'coordinador' => null,
            'solicitud' => null,
        ];
        foreach ($variantes as $caso => $cambio) {
            $respuesta = match ($caso) {
                'documento' => $this->revisar('z1', 'E1', 'titulo_origen_archivo', ['clave_idempotencia' => $abierto['clave_idempotencia']]),
                'coordinador' => $this->revisar('z1b', 'E1', 'comprobante_pago_archivo', [], $abierto),
                'solicitud' => $this->revisar('nacional', 'E2', 'comprobante_pago_archivo', ['clave_idempotencia' => $abierto['clave_idempotencia']]),
                default => $this->revisar('z1', 'E1', 'comprobante_pago_archivo', $cambio, $abierto),
            };
            $respuesta->assertStatus(409);
            $this->assertSame('idempotencia', $respuesta->json('motivo'), $caso);
        }
        $this->assertSame(1, RevisionDocumental::count());
    }

    // ===================================================================== 15, 24 integridad

    public function test_15_24_integridad_de_registros_y_archivos_originales(): void
    {
        [$tablasAntes, $archivosAntes] = $this->huellaOriginales();

        $this->revisar('z1', 'E1', 'comprobante_pago_archivo')->assertCreated();
        $this->revisar('z1', 'E1', 'titulo_origen_archivo', ['resultado' => 'con_observaciones', 'observaciones' => 'La imagen es poco legible.'])->assertCreated();
        $this->revisar('z1', 'E1', 'cedula_origen_archivo', ['resultado' => 'no_valido', 'observaciones' => 'No se cargó la cédula.'])->assertCreated();
        $this->revisar('z1', 'E1', 'comprobante_pago_archivo', ['resultado' => 'con_observaciones', 'observaciones' => 'Falta el sello bancario.',
            'motivo_cambio' => 'Al revisar con detalle no tiene sello.'])->assertCreated();
        $this->actingAs($this->coord['z1'])->get("/coordinacion/solicitudes/{$this->sol['E1']}")->assertOk();
        $this->actingAs($this->coord['z1'])->get("/coordinacion/solicitudes/{$this->sol['E1']}/documentos/comprobante_pago_archivo")->assertOk();

        [$tablasDespues, $archivosDespues] = $this->huellaOriginales();
        $this->assertSame($tablasAntes, $tablasDespues, 'Solicitudes, formación, documentos y usuarios sin cambios.');
        $this->assertSame($archivosAntes, $archivosDespues, 'Archivos originales idénticos (SHA-256).');
        $this->assertSame(4, RevisionDocumental::count());
    }

    // ===================================================================== 16, 19

    public function test_16_parametros_manipulados(): void
    {
        $this->revisar('z1', 'E1', 'comprobante_pago_archivo', ['resultado' => 'por_revisar', 'observaciones' => 'Intento de estado calculado.'])->assertStatus(422);
        $this->actingAs($this->coord['z1'])->getJson("/coordinacion/solicitudes/{$this->sol['E1']}/documentos/curp_archivo/revisiones")->assertNotFound();
        $this->revisar('z1', 'E1', 'comprobante_pago_archivo', ['clave_idempotencia' => 'no-es-uuid'])->assertStatus(422);
        $this->revisar('z1', 'E1', 'comprobante_pago_archivo', ['revision_vigente_id' => 999])->assertStatus(409)->assertJson(['motivo' => 'concurrencia']);
        $this->assertSame(0, RevisionDocumental::count());
    }

    public function test_19_documento_sin_ruta_admite_solo_resultados_desfavorables(): void
    {
        $r = $this->revisar('z1', 'E1', 'cedula_origen_archivo', ['resultado' => 'con_observaciones', 'observaciones' => 'Se solicita la cédula.'])
            ->assertCreated()->json('revision');
        $fila = RevisionDocumental::findOrFail($r['id']);
        $this->assertSame('no_cargado', $fila->archivo_estado);
        $this->assertNull($fila->version_ruta);
        $this->assertNull($fila->version_sha256);
        $this->assertNull($fila->version_bytes);
    }

    // ===================================================================== interfaz y activación

    public function test_interfaz_integrada_compacta_con_dialogo_y_sin_formularios_en_las_tarjetas(): void
    {
        $this->revisar('z1', 'E1', 'comprobante_pago_archivo')->assertCreated();
        $html = $this->actingAs($this->coord['z1'])->get("/coordinacion/solicitudes/{$this->sol['E1']}")->assertOk()->getContent();

        $this->assertSame(6, substr_count($html, 'data-revisar="'), 'Un botón «Revisar» por documento aplicable.');
        $this->assertStringContainsString('data-resultado="validado"', $html);
        $this->assertSame(5, substr_count($html, 'data-resultado="por_revisar"'));
        $this->assertStringContainsString('id="vue-revision-documental"', $html);
        $this->assertStringContainsString('class="coord-indicadores-revision"', $html);
        $this->assertStringContainsString('data-indicador="porcentaje_validado">17 %', $html);
        $this->assertStringNotContainsString('<textarea', $html, 'El formulario vive en el diálogo, no en las tarjetas.');
        $this->assertStringNotContainsString('<form', substr($html, strpos($html, 'coord-panel-documentos')));
        $this->assertStringContainsString('name="csrf-token"', $html);
        // La compactación 6.2.4 se conserva.
        $this->assertSame(1, substr_count($html, 'class="coord-documentos-grupo"'));
        $this->assertStringContainsString('class="coord-fichas"', $html);
        $this->assertLessThan(strpos($html, 'ficha-solicitud"'), strpos($html, 'ficha-solicitante"'));
    }

    public function test_desactivada_por_omision_sin_cambios_en_el_expediente(): void
    {
        config(['anfeca_coordinacion.revision_documental' => false]);
        $this->actingAs($this->coord['z1'])->getJson($this->url('E1'))->assertNotFound();
        $this->actingAs($this->coord['z1'])->getJson($this->url('E1', 'comprobante_pago_archivo'))->assertNotFound();
        $this->actingAs($this->coord['z1'])->postJson($this->url('E1', 'comprobante_pago_archivo'), ['resultado' => 'validado'])->assertNotFound();
        $html = $this->actingAs($this->coord['z1'])->get("/coordinacion/solicitudes/{$this->sol['E1']}")->assertOk()->getContent();
        $this->assertStringNotContainsString('data-revisar', $html);
        $this->assertStringNotContainsString('vue-revision-documental', $html);
        $this->assertStringContainsString('Revisión documental: pendiente (fase 6.3).', $html);
        $this->assertFalse((bool) env('ANFECA_COORDINACION_REVISION', false), 'Valor por omisión: apagado.');
    }

    public function test_componente_vue_limita_textos_a_1000_y_reutiliza_la_clave_en_reintentos(): void
    {
        $vue = file_get_contents(resource_path('js/components/RevisionDocumentalDialogo.vue'));
        $this->assertStringContainsString(':maxlength="maximo"', $vue);
        $this->assertStringContainsString('clave_idempotencia: datos.value.clave_idempotencia', $vue);
        $this->assertStringNotContainsString('localStorage', $vue);
        $this->assertSame(1000, config('anfeca_coordinacion.revision_texto_maximo'));
        $this->assertStringContainsString('maxlength="1000"', file_get_contents(resource_path('js/components/RevisionDocumentalPrototipo.vue')));
    }

    // ===================================================================== aislamiento

    public function test_aislamiento_de_archivos_y_raiz_de_las_pruebas(): void
    {
        $raiz = realpath(base_path());
        $this->assertSame(realpath((string) getcwd()), $raiz, 'La raíz de Laravel es el árbol de pruebas.');
        $this->assertFalse(str_starts_with($raiz, '/var/www/'), 'Las pruebas no corren dentro de un sitio publicado.');
        $raizDisco = realpath(Storage::disk('local')->path(''));
        $this->assertStringStartsWith($raiz.'/storage/framework/testing/', $raizDisco, 'Disco falso dentro del árbol aislado.');
        $this->assertStringNotContainsString('storage/app/private', $raizDisco);
    }
}
