<?php

namespace Tests\Feature;

use App\Models\SolicitudCertificacion;
use App\Models\User;
use App\Services\Catalogos\CatalogoLicenciaturas;
use App\Services\Catalogos\CatalogoPosgrados;
use App\Services\Coordinacion\DocumentosAplicables;
use App\Services\Formacion\RequisitosFormacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Formación Académica: catálogo de licenciaturas en «Nombre del Título» y casilla «Cuento con estudios de posgrado
 * concluidos». SOLO dos escenarios: (1) solo licenciatura, (2) licenciatura con posgrado. Datos y catálogos FICTICIOS.
 */
class FormacionLicenciaturaPosgradoTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refreshDatabaseLaravel;
    }

    private const MIG_POSGRADOS = 'database/migrations_propuestas/2026_11_27_000000_add_titulo_especifico_to_formaciones_academicas_solicitud.php';
    private const MIG_LICENCIATURAS = 'database/migrations_propuestas/2026_11_28_000000_add_titulo_origen_especifico_y_declaracion_posgrado.php';
    private const TC = 'Profesoras y profesores de Tiempo Completo';
    private const DOCS_ORIGEN = ['titulo_origen_archivo', 'cedula_origen_archivo'];
    private const DOCS_ULTIMO = ['titulo_ultimo_grado_archivo', 'cedula_ultimo_grado_archivo'];
    private int $fila = 0;

    public function refreshDatabase(): void
    {
        if (!app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('SEGURIDAD: estas pruebas requieren SQLite en memoria.');
        }
        $this->refreshDatabaseLaravel();
    }

    private static function olvidar(): void
    {
        CatalogoPosgrados::olvidarPersistencia();
        CatalogoLicenciaturas::olvidarPersistencia();
        RequisitosFormacion::olvidarPersistencia();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutVite();
        config(['anfeca_pdm.modo' => 'apagado']);
        (require base_path(self::MIG_POSGRADOS))->up();
        (require base_path(self::MIG_LICENCIATURAS))->up();
        self::olvidar();
        $this->banderas(true, true);
        DB::table('instituciones_afiliadas')->insert(['id' => 1, 'numero_afiliacion' => 'F01', 'nombre' => 'Institución Ficticia']);
    }

    protected function tearDown(): void
    {
        self::olvidar();
        parent::tearDown();
    }

    private function banderas(bool $licenciaturas, bool $declaracion, string $rutaLic = 'licenciaturas_ficticio.json'): void
    {
        config(['anfeca_catalogos.posgrados' => ['activo' => true, 'ruta' => base_path('tests/fixtures/catalogos/posgrados_ficticio.json'),
            'sha256' => null, 'version' => 'FICTICIO-1', 'maximo_resultados' => 400, 'titulo_manual_maximo' => 200, 'titulo_manual_minimo' => 5]]);
        config(['anfeca_catalogos.licenciaturas' => ['activo' => $licenciaturas, 'ruta' => base_path("tests/fixtures/catalogos/{$rutaLic}"),
            'sha256' => null, 'version' => 'FICTICIO-LIC-1', 'maximo_resultados' => 60, 'busqueda_minima' => 3,
            'titulo_manual_maximo' => 200, 'titulo_manual_minimo' => 5]]);
        config(['anfeca_catalogos.declaracion_posgrado.activo' => $declaracion]);
    }

    private function pdf(string $n): UploadedFile
    {
        return UploadedFile::fake()->create($n, 20, 'application/pdf');
    }

    private function solicitante(string $area, string $tipo = 'inicial', ?string $numero = null): array
    {
        $this->fila++;
        $correo = "lic{$this->fila}@example.test";
        $curp = sprintf('FILI8001%02dHDFRRN0%d', $this->fila, $this->fila % 10);
        $u = User::create(['name' => "Solicitante {$this->fila}", 'email' => $correo, 'password' => Hash::make('ContrasenaFicticia1')]);
        DB::table('datos_solicitantes')->insert(['user_id' => $u->id, 'curp' => $curp, 'rfc' => substr($curp, 0, 13), 'nombres' => 'Persona',
            'apellido_paterno' => 'Ficticia', 'genero' => 'Femenino', 'calle' => 'C', 'numero_exterior' => '1', 'codigo_postal' => '01000',
            'entidad_federativa' => 'Ciudad de México', 'colonia' => 'C', 'municipio' => 'M', 'correo_electronico' => $correo,
            'telefono_celular' => '5500000000', 'datos_autorizados' => true]);
        $datos = ['certificacion' => 'ANFECA', 'tipo_personal' => self::TC, 'area_certificacion' => $area];
        if ($tipo === 'refrendo') {
            DB::table('certificados_historicos')->insert(['numero_certificado' => $numero, 'primera_vez' => '2019', 'curp_historica' => $curp,
                'certificacion' => 'ANFECA', 'lote_importacion' => 'L-LIC', 'fila_origen' => $this->fila, 'estado_vinculacion' => 'pendiente']);
            $this->actingAs($u)->post('/registro/certificacion', $datos + ['numero_certificado_anterior' => $numero])->assertRedirect(route('registro.refrendo'));
            $this->actingAs($u)->post('/registro/refrendo', ['aplica_articulo_14' => '0'])->assertRedirect(route('registro.formacion'));
        } else {
            $this->actingAs($u)->post('/registro/certificacion', $datos)->assertRedirect(route('registro.formacion'));
        }

        return [$u, SolicitudCertificacion::where('user_id', $u->id)->latest('id')->firstOrFail()];
    }

    private function guardar(User $u, array $datos, array $archivos = []): \Illuminate\Testing\TestResponse
    {
        $f = [];
        foreach ($archivos as $c) {
            $f[$c] = $this->pdf("{$c}.pdf");
        }

        return $this->actingAs($u)->post('/registro/formacion-academica', array_merge(['nivel_origen' => 'Licenciatura', 'institucion_afiliada_id' => 1], $datos, $f));
    }

    private function catLic(string $clave): array
    {
        return ['modo_titulo_origen' => 'catalogo', 'clave_licenciatura_catalogo' => $clave];
    }

    private function posgrado(string $nivel = 'Maestría', string $area = 'Administración', string $clave = 'MAE-00000000000A'): array
    {
        return ['cuenta_con_posgrado' => '1', 'nivel_ultimo_grado' => $nivel, 'titulo_ultimo_grado' => $area,
            'modo_titulo_ultimo_grado' => 'catalogo', 'clave_posgrado_catalogo' => $clave];
    }

    private function fila(SolicitudCertificacion $s): ?object
    {
        return DB::table('formaciones_academicas_solicitud')->where('solicitud_id', $s->id)->first();
    }

    private function pantalla(User $u): array
    {
        $html = $this->actingAs($u)->get('/registro/formacion-academica')->assertOk()->getContent();
        $leer = function ($atributo) use ($html) {
            $this->assertSame(1, preg_match('/data-'.$atributo.'="([^"]*)"/', $html, $m), $atributo);

            return html_entity_decode($m[1]);
        };

        return ['licenciaturas' => json_decode($leer('catalogo-licenciaturas'), true), 'declaracion' => $leer('declaracion-posgrado'),
            'posgrado_obligatorio' => $leer('posgrado-obligatorio'),
            'formacion' => json_decode($leer('formacion'), true)];
    }

    private function archivosFisicos(SolicitudCertificacion $s): int
    {
        return count(Storage::disk('local')->allFiles("anfeca/documentos/solicitudes/{$s->id}"));
    }

    // ===================================================== Escenario 1: solo licenciatura

    public function test_escenario_1_solo_licenciatura_del_catalogo_completo_sin_duplicar(): void
    {
        [$u, $s] = $this->solicitante('Informática Administrativa');
        $p = $this->pantalla($u);
        $this->assertSame(['activo', true], [$p['licenciaturas']['estado'], $p['licenciaturas']['aplica']]);
        $this->assertSame('true', $p['declaracion']);
        $this->assertNull($p['formacion']['cuenta_con_posgrado'], 'Registro nuevo: sin declaración previa (la casilla inicia desmarcada).');

        $this->guardar($u, $this->catLic('LIC-00000000000C') + ['cuenta_con_posgrado' => '0', 'version_catalogo_licenciaturas' => 'X',
            'titulo_origen_especifico' => 'Inventado'], self::DOCS_ORIGEN)->assertSessionHasNoErrors();
        $f = $this->fila($s);
        // Valores físicos del escenario 1.
        $this->assertSame(['Licenciatura', 'Informática', null], [$f->nivel_origen, $f->titulo_origen, $f->titulo_origen_otro]);
        $this->assertSame(['Ingeniería Ficticia en Computación', 'catalogo', 'LIC-00000000000C', 'FICTICIO-LIC-1'],
            [$f->titulo_origen_especifico, $f->origen_titulo_origen, $f->clave_licenciatura_catalogo, $f->version_catalogo_licenciaturas]);
        $this->assertFalse((bool) $f->cuenta_con_posgrado);
        foreach (['nivel_ultimo_grado', 'titulo_ultimo_grado', 'titulo_ultimo_grado_otro', 'titulo_ultimo_grado_especifico', 'origen_titulo_ultimo_grado',
                  'clave_posgrado_catalogo', 'version_catalogo_posgrados', 'titulo_ultimo_grado_archivo', 'cedula_ultimo_grado_archivo'] as $c) {
            $this->assertNull($f->{$c}, "{$c} permanece nulo (el último grado se deriva de la licenciatura)");
        }
        $this->assertSame(2, $this->archivosFisicos($s), 'Solo los 2 archivos de licenciatura: ninguna copia.');
        $this->assertSame('Licenciatura', RequisitosFormacion::maximoNivel($f));

        // Recuperación de datos al volver.
        $p = $this->pantalla($u);
        $this->assertSame(['LIC-00000000000C', 'Ingeniería Ficticia en Computación', '0'],
            [$p['formacion']['clave_licenciatura_catalogo'], $p['formacion']['titulo_origen_especifico'], $p['formacion']['cuenta_con_posgrado']]);

        // Autorización, completitud, envío y acuse con solo 2 documentos académicos.
        $this->actingAs($u)->post('/registro/formacion-academica/autorizar', ['confirmacion_formacion' => '1'])->assertRedirect(route('registro.documentos'));
        $this->assertFalse(app(DocumentosAplicables::class)->aplica($s->fresh(), 'titulo_ultimo_grado_archivo'), 'Sin revisión documental duplicada.');
        $this->actingAs($u)->post('/registro/documentos-probatorios', ['link' => 'https://example.test/e',
            'constancia_antiguedad_archivo' => $this->pdf('c.pdf'), 'comprobante_pago_archivo' => $this->pdf('p.pdf')])->assertRedirect(route('registro.compromiso'));
        $this->actingAs($u)->post('/registro/compromiso/guardar', ['compromiso_aceptado' => '1'])->assertRedirect(route('registro.completado'));
        $this->actingAs($u)->post('/registro/completado/confirmar')->assertSessionHas('success');
        $this->assertSame('enviado', $s->fresh()->estado);
        $this->actingAs($u)->get("/registro/acuse/{$s->id}")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame(4, $this->archivosFisicos($s), '2 de licenciatura + 2 probatorios; ningún documento de posgrado.');

        // Expediente de coordinación: máximo nivel Licenciatura y documentos de posgrado «no aplican».
        $coord = new User();
        $coord->forceFill(['name' => 'Coordinación', 'email' => 'coord.lic@anfeca.test', 'password' => Hash::make('ContrasenaCoordinacion1'), 'rol' => 'coordinador_nacional'])->save();
        $html = $this->actingAs($coord)->get("/coordinacion/solicitudes/{$s->id}")->assertOk()->getContent();
        $this->assertStringContainsString('Ingeniería Ficticia en Computación', $html);
        $this->assertStringContainsString('sin estudios de posgrado declarados', $html);
        $this->assertStringContainsString('Título del último grado (solo licenciatura)', $html);
    }

    public function test_escenario_1_otro_derecho_y_captura_manual(): void
    {
        [$u, $s] = $this->solicitante('Disciplinas vinculadas al ámbito económico-administrativo');
        // Disciplinas vinculadas exige posgrado (Reglamento arts. 1 y 13; Instrumento 1.1): «solo licenciatura» se rechaza con explicación.
        $this->guardar($u, $this->catLic('LIC-00000000000E') + ['cuenta_con_posgrado' => '0'], self::DOCS_ORIGEN)->assertSessionHasErrors('cuenta_con_posgrado');
        $this->assertStringContainsString('es requisito contar con estudios de posgrado', session('errors')->first('cuenta_con_posgrado'));
        $this->assertNull($this->fila($s));
        $this->assertSame('true', $this->pantalla($u)['posgrado_obligatorio']);
        $this->guardar($u, $this->catLic('LIC-00000000000E') + $this->posgrado('Maestría', 'Otro', 'MAE-00000000000F'),
            [...self::DOCS_ORIGEN, ...self::DOCS_ULTIMO])->assertSessionHasNoErrors();
        $f = $this->fila($s);
        $this->assertSame(['Otro', 'Licenciatura Ficticia en Derecho', 'Licenciatura Ficticia en Derecho'], [$f->titulo_origen, $f->titulo_origen_otro, $f->titulo_origen_especifico]);

        [$u2, $s2] = $this->solicitante('Administración');
        $this->guardar($u2, ['modo_titulo_origen' => 'manual', 'titulo_origen_manual' => '  Licenciatura en Ciencias Administrativas (plan 1985)  ',
            'cuenta_con_posgrado' => '0'], self::DOCS_ORIGEN)->assertSessionHasNoErrors();
        $f2 = $this->fila($s2);
        $this->assertSame(['Administración', 'Licenciatura en Ciencias Administrativas (plan 1985)', 'manual', null, null],
            [$f2->titulo_origen, $f2->titulo_origen_especifico, $f2->origen_titulo_origen, $f2->clave_licenciatura_catalogo, $f2->version_catalogo_licenciaturas]);
    }

    public function test_titulo_clasificado_en_otra_area_se_detiene_con_explicacion_sin_guardar(): void
    {
        [$u, $s] = $this->solicitante('Administración');
        $r = $this->guardar($u, $this->catLic('LIC-00000000000E') + ['cuenta_con_posgrado' => '0'], self::DOCS_ORIGEN);
        $r->assertSessionHasErrors('clave_licenciatura_catalogo');
        $this->assertStringContainsString('clasificado de forma preliminar en el área Otro', session('errors')->first('clave_licenciatura_catalogo'));
        $this->assertNull($this->fila($s));
    }

    // ===================================================== Escenario 2: licenciatura con posgrado

    public function test_escenario_2_licenciatura_con_posgrado_en_los_tres_niveles(): void
    {
        foreach ([['Especialidad', 'ESP-00000000000A'], ['Maestría', 'MAE-00000000000A'], ['Doctorado', 'DOC-00000000000A']] as [$nivel, $clave]) {
            [$u, $s] = $this->solicitante('Contaduría Pública');
            $this->guardar($u, $this->catLic('LIC-00000000000A') + $this->posgrado($nivel, 'Administración', $clave),
                [...self::DOCS_ORIGEN, ...self::DOCS_ULTIMO])->assertSessionHasNoErrors();
            $f = $this->fila($s);
            $this->assertSame(['Contaduría', 'Licenciatura Ficticia en Contaduría Pública', 'LIC-00000000000A'], [$f->titulo_origen, $f->titulo_origen_especifico, $f->clave_licenciatura_catalogo]);
            $this->assertTrue((bool) $f->cuenta_con_posgrado);
            $this->assertSame([$nivel, 'Administración', $clave, 'FICTICIO-1'], [$f->nivel_ultimo_grado, $f->titulo_ultimo_grado, $f->clave_posgrado_catalogo, $f->version_catalogo_posgrados]);
            $this->assertNotNull($f->titulo_ultimo_grado_archivo);
            $this->assertSame(4, $this->archivosFisicos($s));
            $this->assertSame($nivel, RequisitosFormacion::maximoNivel($f));
            $this->actingAs($u)->post('/registro/formacion-academica/autorizar', ['confirmacion_formacion' => '1'])->assertRedirect(route('registro.documentos'));
            $this->assertTrue(app(DocumentosAplicables::class)->aplica($s->fresh(), 'titulo_ultimo_grado_archivo'));
        }
        // Posgrado capturado manualmente.
        [$u, $s] = $this->solicitante('Contaduría Pública');
        $this->guardar($u, $this->catLic('LIC-00000000000A') + ['cuenta_con_posgrado' => '1', 'nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro',
            'modo_titulo_ultimo_grado' => 'manual', 'titulo_ultimo_grado_manual' => 'Maestría en Estudios Ficticios'], [...self::DOCS_ORIGEN, ...self::DOCS_ULTIMO])->assertSessionHasNoErrors();
        $this->assertSame(['manual', 'Maestría en Estudios Ficticios'], [$this->fila($s)->origen_titulo_ultimo_grado, $this->fila($s)->titulo_ultimo_grado_otro]);
    }

    // ===================================================== Negativas

    public function test_negativas_licenciatura_como_posgrado_manipulaciones_y_obligatorios(): void
    {
        [$u, $s] = $this->solicitante('Contaduría Pública');
        $todo = [...self::DOCS_ORIGEN, ...self::DOCS_ULTIMO];
        $casos = [
            'Licenciatura como último grado' => [$this->catLic('LIC-00000000000A') + ['cuenta_con_posgrado' => '1', 'nivel_ultimo_grado' => 'Licenciatura', 'titulo_ultimo_grado' => 'Contaduría'], $todo, 'nivel_ultimo_grado'],
            'sin declaración' => [$this->catLic('LIC-00000000000A') + ['nivel_ultimo_grado' => 'Maestría'], $todo, 'cuenta_con_posgrado'],
            'declaración inválida' => [$this->catLic('LIC-00000000000A') + ['cuenta_con_posgrado' => 'si'], self::DOCS_ORIGEN, 'cuenta_con_posgrado'],
            'posgrado enviado con solo licenciatura' => [$this->catLic('LIC-00000000000A') + ['cuenta_con_posgrado' => '0', 'nivel_ultimo_grado' => 'Maestría'], self::DOCS_ORIGEN, 'nivel_ultimo_grado'],
            'documento de posgrado con solo licenciatura' => [$this->catLic('LIC-00000000000A') + ['cuenta_con_posgrado' => '0'], $todo, 'titulo_ultimo_grado_archivo'],
            'clave de licenciatura inexistente' => [$this->catLic('LIC-FFFFFFFFFFFF') + ['cuenta_con_posgrado' => '0'], self::DOCS_ORIGEN, 'clave_licenciatura_catalogo'],
            'clave con formato inválido' => [$this->catLic('MAE-00000000000A') + ['cuenta_con_posgrado' => '0'], self::DOCS_ORIGEN, 'clave_licenciatura_catalogo'],
            'área enviada por el navegador' => [$this->catLic('LIC-00000000000A') + ['cuenta_con_posgrado' => '0', 'titulo_origen' => 'Contaduría'], self::DOCS_ORIGEN, 'titulo_origen'],
            'sin título de licenciatura' => [['cuenta_con_posgrado' => '0'], self::DOCS_ORIGEN, 'modo_titulo_origen'],
            'manual demasiado corto' => [['modo_titulo_origen' => 'manual', 'titulo_origen_manual' => 'Lic', 'cuenta_con_posgrado' => '0'], self::DOCS_ORIGEN, 'titulo_origen_manual'],
            'manual con clave' => [['modo_titulo_origen' => 'manual', 'titulo_origen_manual' => 'Licenciatura X', 'clave_licenciatura_catalogo' => 'LIC-00000000000A', 'cuenta_con_posgrado' => '0'], self::DOCS_ORIGEN, 'clave_licenciatura_catalogo'],
            'sin documentos de licenciatura' => [$this->catLic('LIC-00000000000A') + ['cuenta_con_posgrado' => '0'], [], 'titulo_origen_archivo'],
            'clave de posgrado de otra área' => [$this->catLic('LIC-00000000000A') + $this->posgrado('Maestría', 'Contaduría', 'MAE-00000000000D'), $todo, 'clave_posgrado_catalogo'],
        ];
        foreach ($casos as $nombre => [$datos, $archivos, $campo]) {
            $this->guardar($u, $datos, $archivos)->assertSessionHasErrors($campo);
            $this->assertNull($this->fila($s), "No se guarda nada: {$nombre}");
        }
        $this->assertSame(0, $this->archivosFisicos($s), 'Los archivos subidos en intentos rechazados no permanecen.');
    }

    public function test_cambio_de_casilla_conserva_y_recupera_el_posgrado_sin_borrar(): void
    {
        [$u, $s] = $this->solicitante('Contaduría Pública');
        $this->guardar($u, $this->catLic('LIC-00000000000A') + $this->posgrado(), [...self::DOCS_ORIGEN, ...self::DOCS_ULTIMO])->assertSessionHasNoErrors();
        $con = (array) $this->fila($s);
        // Pestaña B: declara solo licenciatura (sin archivos).
        $this->guardar($u, $this->catLic('LIC-00000000000A') + ['cuenta_con_posgrado' => '0'])->assertSessionHasNoErrors();
        $sin = (array) $this->fila($s);
        $this->assertFalse((bool) $sin['cuenta_con_posgrado']);
        foreach (['nivel_ultimo_grado', 'titulo_ultimo_grado', 'titulo_ultimo_grado_especifico', 'clave_posgrado_catalogo', 'version_catalogo_posgrados',
                  'titulo_ultimo_grado_archivo', 'cedula_ultimo_grado_archivo'] as $c) {
            $this->assertSame($con[$c], $sin[$c], "{$c} se conserva (no vigente)");
        }
        $this->assertSame(4, $this->archivosFisicos($s), 'Ningún documento eliminado.');
        $this->assertSame('Licenciatura', RequisitosFormacion::maximoNivel((object) $sin));
        $this->assertTrue($this->pantalla($u)['formacion']['tiene_datos_posgrado'], 'La interfaz pide confirmación al desmarcar.');
        // Pestaña A: vuelve a marcar con los mismos datos (sin subir de nuevo): se recupera todo.
        $this->guardar($u, $this->catLic('LIC-00000000000A') + $this->posgrado())->assertSessionHasNoErrors();
        $otra = (array) $this->fila($s);
        $this->assertTrue((bool) $otra['cuenta_con_posgrado']);
        foreach (['titulo_ultimo_grado_especifico', 'clave_posgrado_catalogo', 'titulo_ultimo_grado_archivo', 'cedula_ultimo_grado_archivo'] as $c) {
            $this->assertSame($con[$c], $otra[$c]);
        }
        $this->assertSame(0, DB::table('historial_documentos_academicos')->count());
    }

    public function test_autorizada_y_enviada_no_cambian(): void
    {
        [$u, $s] = $this->solicitante('Informática Administrativa');
        $this->guardar($u, $this->catLic('LIC-00000000000C') + ['cuenta_con_posgrado' => '0'], self::DOCS_ORIGEN)->assertSessionHasNoErrors();
        $this->actingAs($u)->post('/registro/formacion-academica/autorizar', ['confirmacion_formacion' => '1']);
        $antes = (array) $this->fila($s);
        $this->guardar($u, $this->catLic('LIC-00000000000C') + $this->posgrado('Maestría', 'Informática', 'MAE-00000000000D'), self::DOCS_ULTIMO)->assertSessionHas('error');
        $this->assertEquals($antes, (array) $this->fila($s));
    }

    // ===================================================== Endpoint y catálogo

    public function test_endpoint_busqueda_en_todas_las_areas_con_limite_y_seguridad(): void
    {
        $this->getJson('/registro/catalogos/licenciaturas?q=administracion')->assertUnauthorized();
        [$u] = $this->solicitante('Administración');
        $r = $this->actingAs($u)->getJson('/registro/catalogos/licenciaturas?q=ADMINISTRACION')->assertOk();
        $this->assertSame(3, $r->json('total'));
        $this->assertSame(['clave', 'titulo'], array_keys($r->json('titulos.0')));
        $this->assertSame(['LIC-00000000000E'], array_column($this->actingAs($u)->getJson('/registro/catalogos/licenciaturas?q=derecho')->json('titulos'), 'clave'), 'También títulos del área Otro.');
        $this->assertSame(['LIC-00000000000F'], array_column($this->actingAs($u)->getJson('/registro/catalogos/licenciaturas?q='.urlencode('economia aplic'))->json('titulos'), 'clave'));
        config(['anfeca_catalogos.licenciaturas.maximo_resultados' => 2]);
        $r = $this->actingAs($u)->getJson('/registro/catalogos/licenciaturas?q=ficticia')->assertOk();
        $this->assertSame([8, 2], [$r->json('total'), count($r->json('titulos'))], 'Total completo para pedir que se afine la búsqueda.');
        $this->actingAs($u)->getJson('/registro/catalogos/licenciaturas?q=ad')->assertUnprocessable();
    }

    public function test_filtro_por_area_solo_acota_la_busqueda_y_no_sustituye_la_validacion(): void
    {
        [$u, $s] = $this->solicitante('Administración');
        $catalogo = app(\App\Services\Catalogos\CatalogoLicenciaturas::class);
        $todas = $this->actingAs($u)->getJson('/registro/catalogos/licenciaturas?q=ficticia')->assertOk()->json('total');
        $porArea = 0;
        foreach (['Administración', 'Contaduría', 'Informática', 'Negocios Internacionales', 'Otro'] as $area) {
            $r = $this->actingAs($u)->getJson('/registro/catalogos/licenciaturas?q=ficticia&area='.urlencode($area))->assertOk();
            $porArea += $r->json('total');
            foreach ($r->json('titulos') as $t) {
                $this->assertSame($area, $catalogo->porClave($t['clave'])['area'], 'Solo títulos del área solicitada.');
            }
        }
        $this->assertSame($todas, $porArea, 'Las áreas reparten el catálogo completo: ningún título queda inaccesible.');
        $this->actingAs($u)->getJson('/registro/catalogos/licenciaturas?q=ficticia&area=Inexistente')->assertUnprocessable();
        // El filtro de la interfaz no habilita títulos de otra área: el servidor sigue aplicando D-LIC-1.
        $this->guardar($u, $this->catLic('LIC-00000000000A') + ['cuenta_con_posgrado' => '0'], self::DOCS_ORIGEN)
            ->assertSessionHasErrors('clave_licenciatura_catalogo');
        $this->assertNull($this->fila($s));
    }

    public function test_catalogo_ausente_o_danado_usa_formulario_anterior_sin_perder_datos(): void
    {
        [$u, $s] = $this->solicitante('Administración');
        $this->guardar($u, $this->catLic('LIC-00000000000B') + ['cuenta_con_posgrado' => '0'], self::DOCS_ORIGEN)->assertSessionHasNoErrors();
        $antes = (array) $this->fila($s);
        $this->banderas(true, true, 'no_existe.json');
        $p = $this->pantalla($u);
        $this->assertSame(['error', false], [$p['licenciaturas']['estado'], $p['licenciaturas']['aplica']]);
        $this->actingAs($u)->getJson('/registro/catalogos/licenciaturas?q=administracion')->assertNotFound();
        $this->guardar($u, ['titulo_origen' => 'Administración', 'cuenta_con_posgrado' => '0'])->assertSessionHasNoErrors();
        $this->guardar($u, ['titulo_origen' => 'Contaduría', 'cuenta_con_posgrado' => '0'])->assertSessionHasErrors();
        $this->assertEquals(collect($antes)->except('updated_at')->all(), collect((array) $this->fila($s))->except('updated_at')->all());
    }

    // ===================================================== Rollback por banderas

    public function test_desactivar_las_banderas_conserva_todo_y_no_obliga_a_duplicar(): void
    {
        [$u1, $s1] = $this->solicitante('Informática Administrativa');
        $this->guardar($u1, $this->catLic('LIC-00000000000C') + ['cuenta_con_posgrado' => '0'], self::DOCS_ORIGEN)->assertSessionHasNoErrors();
        [$u2, $s2] = $this->solicitante('Contaduría Pública');
        $this->guardar($u2, $this->catLic('LIC-00000000000A') + $this->posgrado(), [...self::DOCS_ORIGEN, ...self::DOCS_ULTIMO])->assertSessionHasNoErrors();
        $antes1 = (array) $this->fila($s1);
        $antes2 = (array) $this->fila($s2);

        $this->banderas(false, false);
        // Escenario 1 registrado: la declaración guardada sigue rigiendo (no se piden datos ni documentos de posgrado).
        $p = $this->pantalla($u1);
        $this->assertSame(['desactivado', 'true'], [$p['licenciaturas']['estado'], $p['declaracion']]);
        $this->guardar($u1, ['titulo_origen' => 'Informática', 'cuenta_con_posgrado' => '0'])->assertSessionHasNoErrors();
        $this->guardar($u1, ['titulo_origen' => 'Administración', 'cuenta_con_posgrado' => '0'])->assertSessionHasErrors('titulo_origen');
        $despues1 = (array) $this->fila($s1);
        foreach (['titulo_origen_especifico', 'origen_titulo_origen', 'clave_licenciatura_catalogo', 'version_catalogo_licenciaturas', 'cuenta_con_posgrado'] as $c) {
            $this->assertEquals($antes1[$c], $despues1[$c], $c);
        }
        // Interfaz anterior (reversión de código) que no envía la casilla: rige la declaración guardada.
        $this->guardar($u1, ['titulo_origen' => 'Informática'])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $this->fila($s1)->cuenta_con_posgrado);
        $this->guardar($u1, ['titulo_origen' => 'Informática', 'nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Informática'])
            ->assertSessionHasErrors('nivel_ultimo_grado');
        $this->actingAs($u1)->post('/registro/formacion-academica/autorizar', ['confirmacion_formacion' => '1'])->assertRedirect(route('registro.documentos'));
        $this->assertSame(2, $this->archivosFisicos($s1), 'Sin documentos duplicados tras volver al formulario anterior.');

        // Escenario 2 registrado: títulos, claves y versiones de ambos grados se conservan.
        $this->guardar($u2, ['titulo_origen' => 'Contaduría', 'cuenta_con_posgrado' => '1', 'nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Administración',
            'modo_titulo_ultimo_grado' => 'catalogo', 'clave_posgrado_catalogo' => 'MAE-00000000000A'])->assertSessionHasNoErrors();
        $despues2 = (array) $this->fila($s2);
        foreach (['titulo_origen_especifico', 'clave_licenciatura_catalogo', 'version_catalogo_licenciaturas', 'cuenta_con_posgrado',
                  'titulo_ultimo_grado_especifico', 'clave_posgrado_catalogo', 'version_catalogo_posgrados', 'titulo_ultimo_grado_archivo'] as $c) {
            $this->assertEquals($antes2[$c], $despues2[$c], $c);
        }

        // Registro nuevo con banderas desactivadas: comportamiento anterior íntegro (sin casilla).
        [$u3, $s3] = $this->solicitante('Administración');
        $this->assertSame('false', $this->pantalla($u3)['declaracion']);
        $this->guardar($u3, ['titulo_origen' => 'Administración', 'nivel_ultimo_grado' => 'Licenciatura', 'titulo_ultimo_grado' => 'Administración'],
            [...self::DOCS_ORIGEN, ...self::DOCS_ULTIMO])->assertSessionHasNoErrors();
        $this->assertNull($this->fila($s3)->cuenta_con_posgrado);
    }

    // ===================================================== Refrendo

    public function test_refrendo_conserva_el_antecedente_y_admite_solo_licenciatura(): void
    {
        [$u, $s] = $this->solicitante('Contaduría Pública', 'refrendo', '2000771');
        $this->assertSame('refrendo', $s->tipo_tramite);
        $this->actingAs($u)->get('/registro/documentos-probatorios')->assertRedirect(route('registro.formacion'));
        $this->guardar($u, $this->catLic('LIC-00000000000A') + ['cuenta_con_posgrado' => '0'], self::DOCS_ORIGEN)->assertSessionHasNoErrors();
        $f = $this->fila($s);
        $this->assertSame(['Contaduría', 'LIC-00000000000A'], [$f->titulo_origen, $f->clave_licenciatura_catalogo]);
        // Segundo guardado: el título de origen registrado se conserva (regla vigente del refrendo).
        $p = $this->pantalla($u);
        $this->assertFalse($p['licenciaturas']['aplica'], 'Con título de origen registrado en refrendo, el origen no cambia.');
        $this->guardar($u, ['cuenta_con_posgrado' => '0'])->assertSessionHasNoErrors();
        $this->assertSame('Licenciatura Ficticia en Contaduría Pública', $this->fila($s)->titulo_origen_especifico);
        $this->guardar($u, ['titulo_origen' => 'Administración', 'cuenta_con_posgrado' => '0'])->assertSessionHasErrors('titulo_origen');
    }

    public function test_sin_la_migracion_nueva_todo_funciona_como_antes(): void
    {
        $m = require base_path(self::MIG_LICENCIATURAS);
        $m->down();
        self::olvidar();
        [$u, $s] = $this->solicitante('Administración');
        $p = $this->pantalla($u);
        $this->assertSame(['error', 'false'], [$p['licenciaturas']['estado'], $p['declaracion']]);
        $this->guardar($u, ['titulo_origen' => 'Administración', 'nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Administración',
            'modo_titulo_ultimo_grado' => 'catalogo', 'clave_posgrado_catalogo' => 'MAE-00000000000A'], [...self::DOCS_ORIGEN, ...self::DOCS_ULTIMO])->assertSessionHasNoErrors();
        $this->assertSame('MAE-00000000000A', $this->fila($s)->clave_posgrado_catalogo, 'El catálogo de posgrados sigue funcionando.');
    }

    public function test_migracion_reversible_solo_sin_datos(): void
    {
        $m = require base_path(self::MIG_LICENCIATURAS);
        [$u, $s] = $this->solicitante('Administración');
        $this->guardar($u, $this->catLic('LIC-00000000000B') + ['cuenta_con_posgrado' => '0'], self::DOCS_ORIGEN)->assertSessionHasNoErrors();
        try {
            $m->down();
            $this->fail('down() debe negarse con datos');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('se niega', $e->getMessage());
        }
        $this->assertSame('LIC-00000000000B', $this->fila($s)->clave_licenciatura_catalogo);
        $this->assertFileDoesNotExist(database_path('migrations/'.basename(self::MIG_LICENCIATURAS)));
        $this->assertFalse((bool) (require base_path('config/anfeca_catalogos.php'))['licenciaturas']['activo']);
        $this->assertFalse((bool) (require base_path('config/anfeca_catalogos.php'))['declaracion_posgrado']['activo']);
    }
}
