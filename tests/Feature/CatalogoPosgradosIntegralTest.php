<?php

namespace Tests\Feature;

use App\Models\SolicitudCertificacion;
use App\Models\User;
use App\Services\Catalogos\CatalogoPosgrados;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Catálogo de posgrados: refrendos, integridad documental, seguridad, dos pestañas y versionado.
 * Datos y catálogos FICTICIOS (tests/fixtures/catalogos), SQLite en memoria, almacenamiento falso.
 * Se conserva la regla de la etapa 1: todos los trámites completan Formación académica y sus 4 documentos.
 */
class CatalogoPosgradosIntegralTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refreshDatabaseLaravel;
    }

    private const MIGRACION = 'database/migrations_propuestas/2026_11_27_000000_add_titulo_especifico_to_formaciones_academicas_solicitud.php';
    private const TC = 'Profesoras y profesores de Tiempo Completo';
    private const URL = '/registro/catalogos/posgrados';
    private const DOCS = ['titulo_origen_archivo', 'cedula_origen_archivo', 'titulo_ultimo_grado_archivo', 'cedula_ultimo_grado_archivo'];

    public function refreshDatabase(): void
    {
        if (!app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('SEGURIDAD: estas pruebas requieren SQLite en memoria.');
        }
        $this->refreshDatabaseLaravel();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutVite();
        config(['anfeca_pdm.modo' => 'apagado']);
        (require base_path(self::MIGRACION))->up();
        CatalogoPosgrados::olvidarPersistencia();
        $this->catalogo('posgrados_ficticio.json', 'FICTICIO-1');
        DB::table('instituciones_afiliadas')->insert(['id' => 1, 'numero_afiliacion' => 'F01', 'nombre' => 'Institución Ficticia']);
    }

    private function catalogo(string $archivo, string $version, bool $activo = true): void
    {
        config(['anfeca_catalogos.posgrados' => ['activo' => $activo, 'ruta' => base_path("tests/fixtures/catalogos/{$archivo}"),
            'sha256' => null, 'version' => $version, 'maximo_resultados' => 400, 'titulo_manual_maximo' => 200, 'titulo_manual_minimo' => 5]]);
    }

    private function pdf(string $n): UploadedFile
    {
        return UploadedFile::fake()->create($n, 20, 'application/pdf');
    }

    private int $filaHistorica = 0;

    private function historico(string $curp, string $numero): int
    {
        return DB::table('certificados_historicos')->insertGetId(['numero_certificado' => $numero, 'primera_vez' => '2019', 'curp_historica' => $curp,
            'certificacion' => 'ANFECA', 'lote_importacion' => 'L-PRUEBA', 'fila_origen' => ++$this->filaHistorica, 'estado_vinculacion' => 'pendiente']);
    }

    /** Alta por el flujo real, datos personales autorizados. */
    private function solicitante(string $curp, string $correo): User
    {
        if (auth()->check()) {
            auth()->logout();
        }
        $this->post('/registro/datos-personales', [
            'curp' => $curp, 'rfc' => substr($curp, 0, 10).substr($curp, -3), 'nombres' => 'Persona', 'apellido_paterno' => 'Ficticia',
            'genero' => 'Femenino', 'calle' => 'Calle Ficticia', 'numero_exterior' => '10', 'codigo_postal' => '01000',
            'entidad_federativa' => 'Ciudad de México', 'colonia' => 'Centro', 'municipio' => 'Cuauhtémoc', 'correo_electronico' => $correo,
            'telefono_celular' => '5511111111', 'password' => 'ContrasenaFicticia1', 'password_confirmation' => 'ContrasenaFicticia1',
        ])->assertRedirect(route('registro.certificacion'));
        $user = User::where('email', $correo)->firstOrFail();
        $this->actingAs($user)->post('/mis-datos/autorizar', ['confirmacion_datos' => '1'])->assertRedirect(route('registro.certificacion'));

        return $user;
    }

    private function refrendo(User $u, string $numero, array $refrendo = ['aplica_articulo_14' => '0']): SolicitudCertificacion
    {
        $this->actingAs($u)->post('/registro/certificacion', ['certificacion' => 'ANFECA', 'tipo_personal' => self::TC,
            'area_certificacion' => 'Administración', 'numero_certificado_anterior' => $numero])->assertRedirect(route('registro.refrendo'));
        $this->actingAs($u)->post('/registro/refrendo', $refrendo)->assertRedirect(route('registro.formacion'));

        return SolicitudCertificacion::where('user_id', $u->id)->latest('id')->firstOrFail();
    }

    private function inicial(User $u): SolicitudCertificacion
    {
        $this->actingAs($u)->post('/registro/certificacion', ['certificacion' => 'ANFECA', 'tipo_personal' => self::TC,
            'area_certificacion' => 'Administración'])->assertRedirect(route('registro.formacion'));

        return SolicitudCertificacion::where('user_id', $u->id)->latest('id')->firstOrFail();
    }

    /** Guarda Formación; $docs = true adjunta los 4 documentos, o un subconjunto por nombre. */
    private function guardar(User $u, array $ultimo, array|bool $docs = true, bool $conOrigen = true)
    {
        $campos = $docs === true ? self::DOCS : ($docs === false ? [] : $docs);
        $archivos = [];
        foreach ($campos as $c) {
            $archivos[$c] = $this->pdf("{$c}.pdf");
        }

        return $this->actingAs($u)->post('/registro/formacion-academica', array_merge(
            ['nivel_origen' => 'Licenciatura', 'institucion_afiliada_id' => 1] + ($conOrigen ? ['titulo_origen' => 'Administración'] : []), $ultimo, $archivos));
    }

    private function fila(SolicitudCertificacion $s): object
    {
        return DB::table('formaciones_academicas_solicitud')->where('solicitud_id', $s->id)->first();
    }

    private function catalogoMae(string $clave, string $area = 'Informática', string $nivel = 'Maestría'): array
    {
        return ['nivel_ultimo_grado' => $nivel, 'titulo_ultimo_grado' => $area, 'modo_titulo_ultimo_grado' => 'catalogo', 'clave_posgrado_catalogo' => $clave];
    }

    private function estadoCatalogo(User $u): string
    {
        $html = $this->actingAs($u)->get('/registro/formacion-academica')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/data-catalogo-posgrados="([^"]*)"/', $html, $m));

        return json_decode(html_entity_decode($m[1]), true)['estado'];
    }

    private function datosFormacion(User $u): array
    {
        $html = $this->actingAs($u)->get('/registro/formacion-academica')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/data-formacion="([^"]*)"/', $html, $m));

        return json_decode(html_entity_decode($m[1]), true);
    }

    // ------------------------------------------------------------------ refrendos y documentos

    public function test_refrendo_ordinario_titulo_documentos_historial_autorizacion_y_envio(): void
    {
        $this->historico('FIRB800101HDFRRN02', '1000031');
        $u = $this->solicitante('FIRB800101HDFRRN02', 'refrendo.cat@example.test');
        $s = $this->refrendo($u, '1000031');
        $this->assertSame('refrendo', $s->tipo_tramite);

        // Etapa 1: sin Formación no se avanza a documentos probatorios.
        $this->actingAs($u)->get('/registro/documentos-probatorios')->assertRedirect(route('registro.formacion'));

        $this->guardar($u, $this->catalogoMae('MAE-00000000000D'))->assertSessionHasNoErrors();
        // Regla vigente del refrendo: el título de origen histórico ya registrado no se vuelve a enviar.
        $this->guardar($u, $this->catalogoMae('MAE-00000000000D') + ['titulo_origen' => 'Contaduría'], false)->assertSessionHasErrors('titulo_origen');
        $f1 = $this->fila($s);
        $this->assertSame(['catalogo', 'Maestría Ficticia en Ciencias Computacionales'], [$f1->origen_titulo_ultimo_grado, $f1->titulo_ultimo_grado_especifico]);
        foreach (self::DOCS as $c) {
            Storage::disk('local')->assertExists($f1->{$c});
        }

        // Retorno al borrador: se muestra lo guardado; guardar sin cambios ni archivos no altera nada.
        $d = $this->datosFormacion($u);
        $this->assertSame(['MAE-00000000000D', 'Maestría Ficticia en Ciencias Computacionales'], [$d['clave_posgrado_catalogo'], $d['titulo_ultimo_grado_especifico']]);
        $this->guardar($u, $this->catalogoMae('MAE-00000000000D'), false, false)->assertSessionHasNoErrors();
        $this->assertEquals(collect((array) $f1)->except('updated_at')->all(), collect((array) $this->fila($s))->except('updated_at')->all());
        $this->assertSame(0, DB::table('historial_documentos_academicos')->count());

        // Cambiar a captura manual NO toca documentos; sustituir los del último grado conserva el historial.
        $this->guardar($u, ['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro', 'modo_titulo_ultimo_grado' => 'manual',
            'titulo_ultimo_grado_manual' => 'Maestría en Economía Ficticia'], false, false)->assertSessionHasNoErrors();
        $f2 = $this->fila($s);
        foreach (self::DOCS as $c) {
            $this->assertSame($f1->{$c}, $f2->{$c}, "El cambio de título no altera {$c}");
        }
        $this->guardar($u, ['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro', 'modo_titulo_ultimo_grado' => 'manual',
            'titulo_ultimo_grado_manual' => 'Maestría en Economía Ficticia'], ['titulo_ultimo_grado_archivo', 'cedula_ultimo_grado_archivo'], false)->assertSessionHasNoErrors();
        $f3 = $this->fila($s);
        $this->assertNotSame($f2->titulo_ultimo_grado_archivo, $f3->titulo_ultimo_grado_archivo);
        $this->assertSame([$f2->titulo_origen_archivo, $f2->cedula_origen_archivo], [$f3->titulo_origen_archivo, $f3->cedula_origen_archivo]);
        $historial = DB::table('historial_documentos_academicos')->where('solicitud_id', $s->id)->pluck('ruta_archivo', 'tipo_documento')->all();
        $this->assertEqualsCanonicalizing(['titulo_ultimo_grado_archivo' => $f2->titulo_ultimo_grado_archivo, 'cedula_ultimo_grado_archivo' => $f2->cedula_ultimo_grado_archivo], $historial);
        foreach ($historial as $ruta) {
            Storage::disk('local')->assertExists($ruta);   // el archivo anterior se conserva físicamente
        }

        // Autorizada: cualquier cambio (catálogo o manual) se rechaza y nada cambia.
        $this->actingAs($u)->post('/registro/formacion-academica/autorizar', ['confirmacion_formacion' => '1'])->assertRedirect(route('registro.documentos'));
        $autorizada = (array) $this->fila($s);
        $this->guardar($u, $this->catalogoMae('MAE-00000000000D'), true, false)->assertSessionHas('error');
        $this->assertEquals($autorizada, (array) $this->fila($s));

        // Envío completo y solicitud enviada: tampoco cambia.
        $this->actingAs($u)->post('/registro/documentos-probatorios', ['link' => 'https://example.test/evidencias',
            'constancia_antiguedad_archivo' => $this->pdf('c.pdf'), 'comprobante_pago_archivo' => $this->pdf('p.pdf')])->assertRedirect(route('registro.compromiso'));
        $this->actingAs($u)->post('/registro/compromiso/guardar', ['compromiso_aceptado' => '1'])->assertRedirect(route('registro.completado'));
        $this->actingAs($u)->post('/registro/completado/confirmar')->assertSessionHas('success');
        $this->assertSame('enviado', $s->refresh()->estado);
        $this->guardar($u, $this->catalogoMae('MAE-00000000000F', 'Otro'), true, false)->assertSessionHas('error');
        $this->assertEquals($autorizada, (array) $this->fila($s));
        $this->actingAs($u)->get("/registro/acuse/{$s->id}")->assertOk()->assertHeader('content-type', 'application/pdf');
        foreach (self::DOCS as $c) {
            Storage::disk('local')->assertExists($autorizada[$c]);   // los archivos físicos siguen presentes
        }
    }

    public function test_refrendo_articulo_14_y_13_conservan_la_formacion_obligatoria(): void
    {
        foreach ([['FIRC800101HDFRRN03', '1000032', ['aplica_articulo_14' => '1', 'inciso_articulo_14' => '14.1']],
                  ['FIRD800101HDFRRN04', '1000033', ['aplica_articulo_14' => '0']]] as $n => [$curp, $num, $ref]) {
            $this->historico($curp, $num);
            $u = $this->solicitante($curp, "refrendo14.{$n}@example.test");
            $s = $this->refrendo($u, $num, $ref);
            $this->actingAs($u)->get('/registro/documentos-probatorios')->assertRedirect(route('registro.formacion'));
            $this->guardar($u, ['nivel_ultimo_grado' => 'Doctorado', 'titulo_ultimo_grado' => 'Administración', 'modo_titulo_ultimo_grado' => 'manual',
                'titulo_ultimo_grado_manual' => 'Doctorado en Administración Ficticia'])->assertSessionHasNoErrors();
            $this->assertSame('manual', $this->fila($s)->origen_titulo_ultimo_grado);
            // Sin los documentos del último grado no se puede guardar por primera vez (regla vigente).
        }
        $u = $this->solicitante('FIRE800101HDFRRN05', 'sin.docs@example.test');
        $s = $this->inicial($u);
        $this->guardar($u, $this->catalogoMae('MAE-00000000000D'), ['titulo_origen_archivo', 'cedula_origen_archivo'])
            ->assertSessionHasErrors(['titulo_ultimo_grado_archivo', 'cedula_ultimo_grado_archivo']);
        $this->assertNull(DB::table('formaciones_academicas_solicitud')->where('solicitud_id', $s->id)->first());
    }

    public function test_usuario_con_dos_certificados_historicos(): void
    {
        $this->historico('FIRF800101HDFRRN06', '1000041');
        $segundo = $this->historico('FIRF800101HDFRRN06', '1000042');
        $u = $this->solicitante('FIRF800101HDFRRN06', 'dos.certificados@example.test');
        $s = $this->refrendo($u, '1000042');
        $this->assertSame($segundo, (int) $s->certificado_historico_id);
        $this->guardar($u, $this->catalogoMae('MAE-00000000000A', 'Administración'))->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('formaciones_academicas_solicitud')->count(), 'Una sola formación, la del trámite en curso.');
        $this->assertSame('MAE-00000000000A', $this->fila($s)->clave_posgrado_catalogo);
    }

    // ------------------------------------------------------------------ seguridad y concurrencia

    public function test_seguridad_endpoint_roles_y_archivo_privado(): void
    {
        $this->getJson(self::URL.'?nivel=Maestría&area=Otro')->assertUnauthorized();
        $this->get(self::URL.'?nivel=Maestría&area=Otro')->assertRedirect(route('login'));

        $coord = new User();
        $coord->forceFill(['name' => 'Coordinación', 'email' => 'coord@anfeca.test', 'password' => Hash::make('ContrasenaCoordinacion1'),
            'rol' => 'coordinador_nacional'])->save();
        $this->actingAs($coord)->get(self::URL.'?nivel=Maestría&area=Otro')->assertRedirect(route('coordinacion.inicio'));
        $this->actingAs($coord)->post('/registro/formacion-academica', $this->catalogoMae('MAE-00000000000D'))->assertRedirect(route('coordinacion.inicio'));
        $this->assertSame(0, DB::table('formaciones_academicas_solicitud')->count());

        // El catálogo vive fuera del directorio público; ninguna ruta lo sirve.
        $this->assertStringStartsNotWith(public_path(), (string) config('anfeca_catalogos.posgrados.ruta'));
        $this->assertStringStartsNotWith(public_path(), storage_path('app/private/catalogos/posgrados_anuies_2025.json'));
        foreach (['/storage/app/private/catalogos/posgrados_anuies_2025.json', '/catalogos/posgrados_anuies_2025.json',
                  '/posgrados_anuies_2025.json', '/storage/catalogos/posgrados_anuies_2025.json'] as $ruta) {
            // 404, o 403 en /storage/{ruta} (ruta firmada del disco local de Laravel: sin firma válida no sirve nada).
            $r = $this->get($ruta);
            $this->assertContains($r->getStatusCode(), [403, 404], $ruta);
            $this->assertStringNotContainsString('MAE-0000', (string) $r->getContent());
        }
        $u = $this->solicitante('FISE800101HDFRRN07', 'seguridad@example.test');
        $this->inicial($u);
        $r = $this->actingAs($u)->getJson(self::URL.'?nivel=Maestría&area=Otro')->assertOk();
        $this->assertSame(['clave', 'titulo'], array_keys($r->json('titulos.0')), 'Sin metadatos de clasificación ni de la fuente.');
        $this->assertStringNotContainsString('confianza', $r->getContent());
    }

    public function test_el_servidor_determina_el_titulo_y_rechaza_manipulaciones(): void
    {
        $u = $this->solicitante('FIMA800101HDFRRN08', 'manipulacion@example.test');
        $s = $this->inicial($u);
        $base = $this->catalogoMae('MAE-00000000000D');
        // Nombre, origen y versión impuestos por el navegador se ignoran.
        $this->guardar($u, $base + ['titulo_ultimo_grado_especifico' => 'Doctorado Inventado', 'origen_titulo_ultimo_grado' => 'manual',
            'version_catalogo_posgrados' => 'FICTICIO-99', 'clave_posgrado_catalogo_old' => 'X'])->assertSessionHasNoErrors();
        $this->assertSame(['Maestría Ficticia en Ciencias Computacionales', 'catalogo', 'FICTICIO-1'],
            [$this->fila($s)->titulo_ultimo_grado_especifico, $this->fila($s)->origen_titulo_ultimo_grado, $this->fila($s)->version_catalogo_posgrados]);
        $antes = (array) $this->fila($s);
        foreach ([['titulo_ultimo_grado' => 'Contaduría'], ['nivel_ultimo_grado' => 'Doctorado'], ['clave_posgrado_catalogo' => 'MAE-0000000000ZZ'],
                  ['clave_posgrado_catalogo' => 'mae-00000000000d'], ['modo_titulo_ultimo_grado' => 'manual'],
                  ['titulo_ultimo_grado_otro' => 'Texto libre']] as $cambio) {
            $this->guardar($u, array_merge($base, $cambio), false)->assertSessionHasErrors();
            $this->assertEquals($antes, (array) $this->fila($s), json_encode($cambio));
        }
        // Versión inexistente configurada: el catálogo queda en error y se usa el formulario anterior (sin pérdida).
        $this->catalogo('posgrados_ficticio.json', 'FICTICIO-9');
        $this->assertSame('error', app(CatalogoPosgrados::class)->estado()['estado']);
        $this->actingAs($u)->getJson(self::URL.'?nivel=Maestría&area=Informática')->assertNotFound();
        $this->assertSame('error', $this->estadoCatalogo($u), 'La pantalla recibe el estado de error y muestra el aviso (formulario anterior).');
        $this->guardar($u, ['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Informática'], false)->assertSessionHasNoErrors();
        $this->assertEquals(collect($antes)->except('updated_at')->all(), collect((array) $this->fila($s))->except('updated_at')->all());
    }

    public function test_dos_pestanas_el_ultimo_guardado_es_coherente_y_nunca_mezcla_datos(): void
    {
        $u = $this->solicitante('FIDP800101HDFRRN09', 'pestanas@example.test');
        $s = $this->inicial($u);
        $this->guardar($u, $this->catalogoMae('MAE-00000000000D'))->assertSessionHasNoErrors();
        // Pestaña A (abierta antes) y pestaña B guardan selecciones distintas en sucesión.
        $this->guardar($u, $this->catalogoMae('MAE-00000000000C', 'Contaduría'), false)->assertSessionHasNoErrors();   // B
        $this->guardar($u, ['nivel_ultimo_grado' => 'Especialidad', 'titulo_ultimo_grado' => 'Otro', 'modo_titulo_ultimo_grado' => 'manual',
            'titulo_ultimo_grado_manual' => 'Especialidad Ficticia Manual'], false)->assertSessionHasNoErrors();               // A
        $f = $this->fila($s);
        $this->assertSame(['Especialidad', 'Otro', 'Especialidad Ficticia Manual', 'manual', null, null, 'Especialidad Ficticia Manual'],
            [$f->nivel_ultimo_grado, $f->titulo_ultimo_grado, $f->titulo_ultimo_grado_especifico, $f->origen_titulo_ultimo_grado,
             $f->clave_posgrado_catalogo, $f->version_catalogo_posgrados, $f->titulo_ultimo_grado_otro]);
        $this->assertSame(1, DB::table('formaciones_academicas_solicitud')->where('solicitud_id', $s->id)->count());
    }

    // ------------------------------------------------------------------ versionado

    public function test_versiones_del_catalogo_conservan_lo_registrado_y_aplican_la_version_vigente_a_lo_nuevo(): void
    {
        $casos = ['A' => ['MAE-00000000000A', 'Administración'], 'B' => ['MAE-00000000000B', 'Administración'],
                  'C' => ['MAE-00000000000C', 'Contaduría'], 'E' => ['MAE-00000000000E', 'Negocios Internacionales']];
        $sol = [];
        foreach ($casos as $k => [$clave, $area]) {
            $u = $this->solicitante(sprintf('FIVE8001%02dHDFRRN1%d', ord($k) - 64, ord($k) - 64), "version.{$k}@example.test");
            $sol[$k] = [$u, $this->inicial($u)];
            $this->guardar($u, $this->catalogoMae($clave, $area))->assertSessionHasNoErrors();
        }
        $v1 = collect($sol)->map(fn ($p) => (array) $this->fila($p[1]))->all();

        // Versión 2: alta, baja (E), reclasificación (C → Administración), renombre con clave nueva (B), solo acentos (A).
        $this->catalogo('posgrados_ficticio_v2.json', 'FICTICIO-2');
        $u0 = $sol['A'][0];
        $nombres = fn ($area) => array_column($this->actingAs($u0)->getJson(self::URL."?nivel=Maestría&area={$area}")->json('titulos'), 'clave');
        $this->assertSame(['MAE-00000000000C', 'MAE-00000000000A', 'MAE-000000000011'], $nombres('Administración'));
        $this->assertContains('MAE-000000000010', $nombres('Informática'));
        $this->assertSame([], $nombres('Negocios Internacionales'), 'El título retirado ya no se ofrece.');

        foreach ($casos as $k => [$clave, $area]) {
            [$u, $s] = $sol[$k];
            // El formulario conserva lo registrado (título, clave y versión de origen) sin modificarlo.
            $d = $this->datosFormacion($u);
            $this->assertSame([$clave, $v1[$k]['titulo_ultimo_grado_especifico'], 'FICTICIO-1'],
                [$d['clave_posgrado_catalogo'], $d['titulo_ultimo_grado_especifico'], $d['version_catalogo_posgrados']]);
            // Guardar SIN cambios conserva el registro original aunque la versión vigente lo haya retirado o cambiado.
            $this->guardar($u, $this->catalogoMae($clave, $area), false)->assertSessionHasNoErrors();
            $this->assertEquals(collect($v1[$k])->except('updated_at')->all(), collect((array) $this->fila($s))->except('updated_at')->all(), "Caso {$k}");
        }
        $this->assertSame('Maestría Ficticia en Dirección de Empresas', $this->fila($sol['A'][1])->titulo_ultimo_grado_especifico,
            'Un cambio de denominación no reescribe el título guardado.');

        // Una selección NUEVA usa la versión vigente; las claves retiradas o en otra área se rechazan.
        [$uE, $sE] = $sol['E'];
        $this->guardar($uE, $this->catalogoMae('MAE-00000000000E', 'Administración'), false)->assertSessionHasErrors('clave_posgrado_catalogo');
        [$uC, $sC] = $sol['C'];
        $this->guardar($uC, $this->catalogoMae('MAE-00000000000C', 'Administración'), false)->assertSessionHasNoErrors();
        $this->assertSame(['Administración', 'FICTICIO-2'], [$this->fila($sC)->titulo_ultimo_grado, $this->fila($sC)->version_catalogo_posgrados]);
        [$uB, $sB] = $sol['B'];
        $this->guardar($uB, $this->catalogoMae('MAE-000000000011', 'Administración'), false)->assertSessionHasNoErrors();
        $this->assertSame(['Maestría Ficticia en Gestión Pública Comparada', 'FICTICIO-2'],
            [$this->fila($sB)->titulo_ultimo_grado_especifico, $this->fila($sB)->version_catalogo_posgrados]);

        // Consulta de registros por versión de origen.
        $this->assertSame(['FICTICIO-1' => 2, 'FICTICIO-2' => 2], DB::table('formaciones_academicas_solicitud')
            ->whereNotNull('version_catalogo_posgrados')->groupBy('version_catalogo_posgrados')->orderBy('version_catalogo_posgrados')
            ->pluck(DB::raw('count(*)'), 'version_catalogo_posgrados')->map(fn ($n) => (int) $n)->all());
    }

    public function test_colision_de_claves_rechaza_el_catalogo_completo_sin_perder_datos(): void
    {
        $u = $this->solicitante('FICO800101HDFRRN19', 'colision@example.test');
        $s = $this->inicial($u);
        $this->guardar($u, $this->catalogoMae('MAE-00000000000A', 'Administración'))->assertSessionHasNoErrors();
        $antes = (array) $this->fila($s);
        $this->catalogo('posgrados_ficticio_colision.json', 'FICTICIO-COLISION');
        $this->assertSame(['estado' => 'error', 'motivo' => 'catalogo'], app(CatalogoPosgrados::class)->estado());
        $this->assertSame('error', $this->estadoCatalogo($u));
        // Formulario anterior: guardar sin cambiar nivel ni área conserva el título; cambiarlos se rechaza con explicación.
        $this->guardar($u, ['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Administración'], false)->assertSessionHasNoErrors();
        $this->guardar($u, ['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Contaduría'], false)->assertSessionHasErrors('nivel_ultimo_grado');
        $this->assertEquals(collect($antes)->except('updated_at')->all(), collect((array) $this->fila($s))->except('updated_at')->all());
    }
}
