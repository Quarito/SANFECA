<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Catalogos\CatalogoPosgrados;
use App\Services\Catalogos\TituloUltimoGrado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Catálogo de posgrados en «Último grado de estudios» (alternativa A, columnas aditivas).
 * Datos y catálogo FICTICIOS (tests/fixtures/catalogos), SQLite en memoria; la migración propuesta
 * se aplica explícitamente solo en las pruebas que la requieren.
 */
class CatalogoPosgradosTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refreshDatabaseLaravel;
    }

    private const MIGRACION = 'database/migrations_propuestas/2026_11_27_000000_add_titulo_especifico_to_formaciones_academicas_solicitud.php';
    private const URL = '/registro/catalogos/posgrados';

    private User $user;
    private int $solicitudId;

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
        CatalogoPosgrados::olvidarPersistencia();
        config(['anfeca_catalogos.posgrados' => [
            'activo' => false,
            'ruta' => base_path('tests/fixtures/catalogos/posgrados_ficticio.json'),
            'sha256' => null, 'version' => 'FICTICIO-1',
            'maximo_resultados' => 400, 'titulo_manual_maximo' => 200, 'titulo_manual_minimo' => 5,
        ]]);

        DB::table('instituciones_afiliadas')->insert(['id' => 1, 'numero_afiliacion' => 'F01', 'nombre' => 'Institución Ficticia']);
        $this->user = User::create(['name' => 'Solicitante', 'email' => 'catalogo@example.test', 'password' => Hash::make('ContrasenaFicticia1')]);
        DB::table('datos_solicitantes')->insert([
            'user_id' => $this->user->id, 'curp' => 'FICA800101HDFRRN09', 'rfc' => 'FICA800101AB9', 'nombres' => 'Persona',
            'apellido_paterno' => 'Ficticia', 'genero' => 'Femenino', 'calle' => 'C', 'numero_exterior' => '1', 'codigo_postal' => '01000',
            'entidad_federativa' => 'Ciudad de México', 'colonia' => 'C', 'municipio' => 'M', 'correo_electronico' => 'catalogo@example.test',
            'telefono_celular' => '5500000000', 'datos_autorizados' => true,
        ]);
        // Selección de certificación por el flujo real (crea la solicitud inicial en borrador).
        $this->actingAs($this->user)->post('/registro/certificacion', ['certificacion' => 'ANFECA',
            'tipo_personal' => 'Profesoras y profesores de Tiempo Completo', 'area_certificacion' => 'Administración'])
            ->assertRedirect(route('registro.formacion'));
        $this->solicitudId = (int) DB::table('solicitudes_certificacion')->where('user_id', $this->user->id)->value('id');
    }

    private function migrar(): void
    {
        (require base_path(self::MIGRACION))->up();
        CatalogoPosgrados::olvidarPersistencia();
    }

    private function activar(bool $activo = true): void
    {
        config(['anfeca_catalogos.posgrados.activo' => $activo]);
    }

    private function base(array $ultimo): array
    {
        $pdf = fn ($n) => UploadedFile::fake()->create($n, 20, 'application/pdf');

        return array_merge([
            'nivel_origen' => 'Licenciatura', 'titulo_origen' => 'Administración', 'institucion_afiliada_id' => 1,
            'titulo_origen_archivo' => $pdf('to.pdf'), 'cedula_origen_archivo' => $pdf('co.pdf'),
            'titulo_ultimo_grado_archivo' => $pdf('tu.pdf'), 'cedula_ultimo_grado_archivo' => $pdf('cu.pdf'),
        ], $ultimo);
    }

    private function guardar(array $ultimo)
    {
        return $this->actingAs($this->user)->post('/registro/formacion-academica', $this->base($ultimo));
    }

    private function fila(): ?object
    {
        return DB::table('formaciones_academicas_solicitud')->where('solicitud_id', $this->solicitudId)->first();
    }

    private function estadoEnPantalla(): array
    {
        $html = $this->actingAs($this->user)->get('/registro/formacion-academica')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/data-catalogo-posgrados="([^"]*)"/', $html, $m));

        return json_decode(html_entity_decode($m[1]), true);
    }

    // --- Bandera y disponibilidad --------------------------------------------------------------

    public function test_bandera_desactivada_sin_migracion_conserva_el_formulario_anterior(): void
    {
        $this->assertSame('desactivado', $this->estadoEnPantalla()['estado']);
        $this->actingAs($this->user)->getJson(self::URL.'?nivel=Maestría&area=Administración')->assertNotFound();
        $this->guardar(['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro', 'titulo_ultimo_grado_otro' => 'Finanzas'])
            ->assertSessionHasNoErrors();
        $f = $this->fila();
        $this->assertSame(['Maestría', 'Otro', 'Finanzas'], [$f->nivel_ultimo_grado, $f->titulo_ultimo_grado, $f->titulo_ultimo_grado_otro]);
        $this->assertObjectNotHasProperty('titulo_ultimo_grado_especifico', $f);
    }

    public function test_bandera_activa_sin_migracion_usa_el_formulario_anterior_con_aviso(): void
    {
        $this->activar();
        $estado = $this->estadoEnPantalla();
        $this->assertSame('error', $estado['estado']);
        $this->assertNull($estado['url']);
        $this->actingAs($this->user)->getJson(self::URL.'?nivel=Maestría&area=Administración')->assertNotFound();
        $this->guardar(['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Informática'])->assertSessionHasNoErrors();
    }

    public function test_bandera_activa_con_catalogo_ausente_o_alterado_no_produce_un_catalogo_vacio(): void
    {
        $this->migrar();
        $this->activar();
        config(['anfeca_catalogos.posgrados.ruta' => base_path('tests/fixtures/catalogos/no_existe.json')]);
        $this->assertSame('error', $this->estadoEnPantalla()['estado']);
        config(['anfeca_catalogos.posgrados.ruta' => base_path('tests/fixtures/catalogos/posgrados_ficticio.json'), 'anfeca_catalogos.posgrados.sha256' => str_repeat('0', 64)]);
        $this->assertSame('error', $this->estadoEnPantalla()['estado']);
        config(['anfeca_catalogos.posgrados.sha256' => hash_file('sha256', base_path('tests/fixtures/catalogos/posgrados_ficticio.json'))]);
        $this->assertSame('activo', $this->estadoEnPantalla()['estado']);
        config(['anfeca_catalogos.posgrados.version' => 'OTRA']);
        $this->assertSame('error', $this->estadoEnPantalla()['estado']);
    }

    // --- Endpoint ------------------------------------------------------------------------------

    public function test_endpoint_autenticado_minimo_filtrado_y_con_busqueda_sin_acentos(): void
    {
        $this->migrar();
        $this->activar();
        auth()->logout();
        $this->getJson(self::URL.'?nivel=Maestría&area=Administración')->assertUnauthorized();

        $r = $this->actingAs($this->user)->getJson(self::URL.'?nivel=Maestría&area=Administración')->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, private');
        $this->assertSame('FICTICIO-1', $r->json('version'));
        $this->assertSame(['Maestría Ficticia en Administración Pública Comparada', 'Maestría Ficticia en Dirección de Empresas'],
            array_column($r->json('titulos'), 'titulo'), 'Solo nivel y área solicitados, en orden alfabético.');
        $this->assertSame(['clave', 'titulo'], array_keys($r->json('titulos.0')), 'Solo datos mínimos.');

        $r = $this->actingAs($this->user)->getJson(self::URL.'?nivel=Maestría&area=Administración&q='.urlencode('ADMINISTRACION publica'));
        $this->assertSame(['MAE-00000000000B'], array_column($r->json('titulos'), 'clave'));
        $r = $this->actingAs($this->user)->getJson(self::URL.'?nivel=Maestría&area=Administración&q=direcc');
        $this->assertSame(['MAE-00000000000A'], array_column($r->json('titulos'), 'clave'));

        $this->actingAs($this->user)->getJson(self::URL.'?nivel=Licenciatura&area=Administración')->assertUnprocessable();
        $this->actingAs($this->user)->getJson(self::URL.'?nivel=Maestría&area=Economía')->assertUnprocessable();
        $this->actingAs($this->user)->getJson(self::URL.'?nivel=Maestría&area=Otro&q='.str_repeat('a', 101))->assertUnprocessable();
    }

    // --- Persistencia y validaciones -----------------------------------------------------------

    public function test_seleccion_del_catalogo_guarda_el_titulo_del_servidor_clave_y_version(): void
    {
        $this->migrar();
        $this->activar();
        $this->assertSame(['estado' => 'activo', 'url' => url(self::URL), 'version' => 'FICTICIO-1'], $this->estadoEnPantalla());

        $this->guardar(['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Informática', 'modo_titulo_ultimo_grado' => 'catalogo',
            'clave_posgrado_catalogo' => 'MAE-00000000000D',
            // Manipulación: el navegador intenta imponer el nombre, el origen y la versión.
            'titulo_ultimo_grado_especifico' => 'Título inventado', 'origen_titulo_ultimo_grado' => 'manual', 'version_catalogo_posgrados' => 'X',
        ])->assertSessionHasNoErrors();

        $f = $this->fila();
        $this->assertSame('Maestría Ficticia en Ciencias Computacionales', $f->titulo_ultimo_grado_especifico);
        $this->assertSame(['catalogo', 'MAE-00000000000D', 'FICTICIO-1'], [$f->origen_titulo_ultimo_grado, $f->clave_posgrado_catalogo, $f->version_catalogo_posgrados]);
        $this->assertSame(['Maestría', 'Informática', null], [$f->nivel_ultimo_grado, $f->titulo_ultimo_grado, $f->titulo_ultimo_grado_otro]);
    }

    public function test_area_otro_del_catalogo_se_refleja_en_el_campo_existente(): void
    {
        $this->migrar();
        $this->activar();
        $this->guardar(['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro', 'modo_titulo_ultimo_grado' => 'catalogo',
            'clave_posgrado_catalogo' => 'MAE-00000000000F'])->assertSessionHasNoErrors();
        $f = $this->fila();
        $this->assertSame($f->titulo_ultimo_grado_especifico, $f->titulo_ultimo_grado_otro, '«Otro» no significa «no elegible»: conserva el título.');
        $this->assertSame('catalogo', $f->origen_titulo_ultimo_grado);
    }

    public function test_manipulacion_del_post_se_rechaza_sin_guardar(): void
    {
        $this->migrar();
        $this->activar();
        $casos = [
            'clave de otra área' => [['titulo_ultimo_grado' => 'Contaduría', 'clave_posgrado_catalogo' => 'MAE-00000000000D'], 'clave_posgrado_catalogo'],
            'clave de otro nivel' => [['nivel_ultimo_grado' => 'Doctorado', 'clave_posgrado_catalogo' => 'MAE-00000000000A'], 'clave_posgrado_catalogo'],
            'clave inexistente' => [['clave_posgrado_catalogo' => 'MAE-FFFFFFFFFFFF'], 'clave_posgrado_catalogo'],
            'clave con formato inválido' => [['clave_posgrado_catalogo' => "MAE-1' OR 1=1"], 'clave_posgrado_catalogo'],
            'sin clave' => [['clave_posgrado_catalogo' => ''], 'clave_posgrado_catalogo'],
            'modo inválido' => [['modo_titulo_ultimo_grado' => 'libre'], 'modo_titulo_ultimo_grado'],
            'sin modo' => [['modo_titulo_ultimo_grado' => null], 'modo_titulo_ultimo_grado'],
            'título libre enviado' => [['titulo_ultimo_grado_otro' => 'Inventado'], 'titulo_ultimo_grado_otro'],
            'texto manual en modo catálogo' => [['titulo_ultimo_grado_manual' => 'Inventado largo'], 'titulo_ultimo_grado_manual'],
            'área inválida' => [['titulo_ultimo_grado' => 'Economía'], 'titulo_ultimo_grado'],
        ];
        foreach ($casos as $nombre => [$cambios, $campo]) {
            $this->guardar(array_merge(['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Administración',
                'modo_titulo_ultimo_grado' => 'catalogo', 'clave_posgrado_catalogo' => 'MAE-00000000000A'], $cambios))
                ->assertSessionHasErrors($campo);
            $this->assertNull($this->fila(), "No se guarda nada: {$nombre}");
        }
    }

    public function test_captura_manual_en_cualquier_area_sin_clave_ni_version(): void
    {
        $this->migrar();
        $this->activar();
        foreach (['Administración', 'Contaduría', 'Informática', 'Negocios Internacionales', 'Otro'] as $area) {
            $this->guardar(['nivel_ultimo_grado' => 'Doctorado', 'titulo_ultimo_grado' => $area, 'modo_titulo_ultimo_grado' => 'manual',
                'titulo_ultimo_grado_manual' => "  Doctorado en {$area} Comparada  ", 'clave_posgrado_catalogo' => ''])->assertSessionHasNoErrors();
            $f = $this->fila();
            $this->assertSame("Doctorado en {$area} Comparada", $f->titulo_ultimo_grado_especifico);
            $this->assertSame(['manual', null, null], [$f->origen_titulo_ultimo_grado, $f->clave_posgrado_catalogo, $f->version_catalogo_posgrados]);
            $this->assertSame($area === 'Otro' ? "Doctorado en {$area} Comparada" : null, $f->titulo_ultimo_grado_otro);
        }
        $base = ['nivel_ultimo_grado' => 'Doctorado', 'titulo_ultimo_grado' => 'Otro', 'modo_titulo_ultimo_grado' => 'manual'];
        $this->guardar($base + ['titulo_ultimo_grado_manual' => ''])->assertSessionHasErrors('titulo_ultimo_grado_manual');
        $this->guardar($base + ['titulo_ultimo_grado_manual' => 'Abc'])->assertSessionHasErrors('titulo_ultimo_grado_manual');
        $this->guardar($base + ['titulo_ultimo_grado_manual' => str_repeat('a', 201)])->assertSessionHasErrors('titulo_ultimo_grado_manual');
        $this->guardar($base + ['titulo_ultimo_grado_manual' => 'Doctorado <script>'])->assertSessionHasErrors('titulo_ultimo_grado_manual');
        $this->guardar($base + ['titulo_ultimo_grado_manual' => 'Doctorado en algo', 'clave_posgrado_catalogo' => 'MAE-00000000000F'])
            ->assertSessionHasErrors('clave_posgrado_catalogo');
    }

    public function test_licenciatura_conserva_el_comportamiento_actual_y_limpia_el_titulo_especifico(): void
    {
        $this->migrar();
        $this->activar();
        $this->guardar(['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Administración', 'modo_titulo_ultimo_grado' => 'catalogo',
            'clave_posgrado_catalogo' => 'MAE-00000000000A'])->assertSessionHasNoErrors();
        $this->guardar(['nivel_ultimo_grado' => 'Licenciatura', 'titulo_ultimo_grado' => 'Otro'])->assertSessionHasErrors('titulo_ultimo_grado_otro');
        $this->guardar(['nivel_ultimo_grado' => 'Licenciatura', 'titulo_ultimo_grado' => 'Otro', 'titulo_ultimo_grado_otro' => 'Economía'])
            ->assertSessionHasNoErrors();
        $f = $this->fila();
        $this->assertSame(['Licenciatura', 'Otro', 'Economía'], [$f->nivel_ultimo_grado, $f->titulo_ultimo_grado, $f->titulo_ultimo_grado_otro]);
        $this->assertNull($f->titulo_ultimo_grado_especifico);
        $this->assertNull($f->clave_posgrado_catalogo);
    }

    // --- Reversión (nivel 1: bandera) ----------------------------------------------------------

    public function test_desactivar_la_bandera_no_pierde_titulos_claves_ni_versiones_y_reactivar_los_recupera(): void
    {
        $this->migrar();
        $this->activar();
        $this->guardar(['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Contaduría', 'modo_titulo_ultimo_grado' => 'catalogo',
            'clave_posgrado_catalogo' => 'MAE-00000000000C'])->assertSessionHasNoErrors();
        $guardado = (array) $this->fila();

        $this->activar(false);
        $this->assertSame('desactivado', $this->estadoEnPantalla()['estado']);
        // Guardar con el formulario anterior sin cambiar nivel ni área conserva el título.
        $this->guardar(['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Contaduría'])->assertSessionHasNoErrors();
        $f = $this->fila();
        foreach (CatalogoPosgrados::COLUMNAS as $c) {
            $this->assertSame($guardado[$c], $f->{$c}, $c);
        }
        // Cambiar nivel o área desde el formulario anterior se rechaza de forma explícita; nada cambia.
        $this->guardar(['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Informática'])->assertSessionHasErrors('nivel_ultimo_grado');
        $this->guardar(['nivel_ultimo_grado' => 'Doctorado', 'titulo_ultimo_grado' => 'Contaduría'])->assertSessionHasErrors('nivel_ultimo_grado');
        $this->assertSame(['Maestría', 'Contaduría', 'MAE-00000000000C'], [$this->fila()->nivel_ultimo_grado, $this->fila()->titulo_ultimo_grado, $this->fila()->clave_posgrado_catalogo]);

        $this->activar();
        $this->assertSame('activo', $this->estadoEnPantalla()['estado']);
        $html = $this->actingAs($this->user)->get('/registro/formacion-academica')->getContent();
        $this->assertStringContainsString(json_encode('Maestría Ficticia en Auditoría y Tributación'), html_entity_decode($html));
    }

    public function test_formulario_anterior_sobre_titulos_del_area_otro(): void
    {
        $this->migrar();
        $this->activar();
        $this->guardar(['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro', 'modo_titulo_ultimo_grado' => 'manual',
            'titulo_ultimo_grado_manual' => 'Maestría en Economía Regional'])->assertSessionHasNoErrors();
        $this->activar(false);
        // Título manual: el formulario anterior lo edita mediante el campo existente y se mantiene sincronizado.
        $this->guardar(['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro', 'titulo_ultimo_grado_otro' => 'Maestría en Economía Urbana'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Maestría en Economía Urbana', $this->fila()->titulo_ultimo_grado_especifico);

        // Título del catálogo: no se modifica desde el formulario anterior.
        $this->activar();
        $this->guardar(['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro', 'modo_titulo_ultimo_grado' => 'catalogo',
            'clave_posgrado_catalogo' => 'MAE-00000000000F'])->assertSessionHasNoErrors();
        $this->activar(false);
        $otro = $this->fila()->titulo_ultimo_grado_otro;
        $this->guardar(['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro', 'titulo_ultimo_grado_otro' => 'Cambiado'])
            ->assertSessionHasErrors('titulo_ultimo_grado_otro');
        $this->guardar(['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro', 'titulo_ultimo_grado_otro' => $otro])
            ->assertSessionHasNoErrors();
        $this->assertSame('MAE-00000000000F', $this->fila()->clave_posgrado_catalogo);
    }

    public function test_registros_historicos_no_se_modifican_y_se_presentan_igual(): void
    {
        $this->migrar();
        DB::table('formaciones_academicas_solicitud')->insert(['solicitud_id' => $this->solicitudId, 'nivel_origen' => 'Licenciatura',
            'titulo_origen' => 'Administración', 'nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro',
            'titulo_ultimo_grado_otro' => 'Economía', 'institucion_afiliada_id' => 1]);
        $antes = (array) $this->fila();
        $this->activar();
        $this->actingAs($this->user)->get('/registro/formacion-academica')->assertOk();
        $this->actingAs($this->user)->getJson(self::URL.'?nivel=Maestría&area=Otro')->assertOk();
        $this->assertEquals($antes, (array) $this->fila(), 'Consultar no escribe.');
        $this->assertSame('Economía', TituloUltimoGrado::presentar($this->fila()));
    }

    public function test_presentacion_del_titulo_en_consultas(): void
    {
        $this->assertSame('Título exacto', TituloUltimoGrado::presentar(['titulo_ultimo_grado_especifico' => 'Título exacto', 'titulo_ultimo_grado' => 'Otro', 'titulo_ultimo_grado_otro' => 'x']));
        $this->assertSame('Libre', TituloUltimoGrado::presentar((object) ['titulo_ultimo_grado' => 'Otro', 'titulo_ultimo_grado_otro' => 'Libre']));
        $this->assertSame('Informática', TituloUltimoGrado::presentar((object) ['titulo_ultimo_grado' => 'Informática']));
        $this->assertSame('—', TituloUltimoGrado::presentar(null));
    }

    // --- Migración propuesta ---------------------------------------------------------------------

    public function test_migracion_aditiva_reversible_solo_sin_datos(): void
    {
        $migracion = require base_path(self::MIGRACION);
        $migracion->up();
        CatalogoPosgrados::olvidarPersistencia();
        $this->assertTrue(CatalogoPosgrados::persistenciaDisponible());
        $migracion->down();
        CatalogoPosgrados::olvidarPersistencia();
        $this->assertFalse(CatalogoPosgrados::persistenciaDisponible());

        $migracion->up();
        CatalogoPosgrados::olvidarPersistencia();
        $this->activar();
        $this->guardar(['nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Administración', 'modo_titulo_ultimo_grado' => 'catalogo',
            'clave_posgrado_catalogo' => 'MAE-00000000000A'])->assertSessionHasNoErrors();
        try {
            $migracion->down();
            $this->fail('down() debe negarse a eliminar columnas con datos.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('se niega', $e->getMessage());
        }
        $this->assertSame('MAE-00000000000A', $this->fila()->clave_posgrado_catalogo);
    }

    public function test_la_migracion_no_esta_en_la_ruta_automatica(): void
    {
        $this->assertFileDoesNotExist(database_path('migrations/'.basename(self::MIGRACION)));
        $this->assertFalse((bool) (require base_path('config/anfeca_catalogos.php'))['posgrados']['activo'], 'La bandera está desactivada por omisión.');
    }
}
