<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Módulo de coordinación (fases 6.1 y 6.2): acceso, aislamiento por zona,
 * manipulación de URL y parámetros, documentos, indicadores vs bandeja e
 * integridad de solicitudes. Datos FICTICIOS creados en SQLite en memoria.
 */
class CoordinacionTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refreshDatabaseLaravel;
    }

    private array $zona = [];
    private array $sol = [];
    private array $coord = [];
    private User $solicitante;
    private \Closure $crear;
    private array $inst = [];

    public function refreshDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('SEGURIDAD: las pruebas de coordinación requieren SQLite en memoria.');
        }
        $this->refreshDatabaseLaravel();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutVite();

        // Zonas ficticias: los IDs NO coinciden con el número de zona a propósito.
        foreach (['ZF6' => 'Zona ficticia seis', 'ZF3' => 'Zona ficticia tres', 'ZF4' => 'Zona ficticia cuatro'] as $s => $d) {
            $this->zona[$s] = DB::table('zonas')->insertGetId(['siglas' => $s, 'descripcion' => $d]);
        }

        $inst = [
            'ZF3' => DB::table('instituciones_afiliadas')->insertGetId(['numero_afiliacion' => 'I3', 'nombre' => 'Institución Tres', 'zona_id' => $this->zona['ZF3'], 'escuela_facultad' => 'Facultad Tres']),
            'ZF4' => DB::table('instituciones_afiliadas')->insertGetId(['numero_afiliacion' => 'I4', 'nombre' => 'Institución Cuatro', 'zona_id' => $this->zona['ZF4']]),
            'ZF6' => DB::table('instituciones_afiliadas')->insertGetId(['numero_afiliacion' => 'I6', 'nombre' => 'Institución Seis', 'zona_id' => $this->zona['ZF6']]),
            'SIN' => DB::table('instituciones_afiliadas')->insertGetId(['numero_afiliacion' => 'I0', 'nombre' => 'Institución Sin Zona', 'zona_id' => null]),
        ];

        $n = 0;
        $crear = function (string $clave, ?int $institucion, string $estado, string $tipo) use (&$n) {
            $n++;
            $u = User::create(['name' => "Solicitante {$n}", 'email' => "sol{$n}@example.test", 'password' => Hash::make('ContrasenaFicticia1')]);
            DB::table('datos_solicitantes')->insert([
                'user_id' => $u->id, 'curp' => sprintf('FICT8001%02dHDFRRN0%d', $n, $n % 10), 'rfc' => sprintf('FICT8001%02dAB%d', $n, $n % 10),
                'nombres' => "Nombre{$n}", 'apellido_paterno' => "Paterno{$n}", 'genero' => 'Femenino', 'calle' => 'C', 'numero_exterior' => '1',
                'codigo_postal' => '01000', 'entidad_federativa' => 'Ciudad de México', 'colonia' => 'C', 'municipio' => 'M',
                'correo_electronico' => "sol{$n}@example.test", 'telefono_celular' => '5500000000', 'datos_autorizados' => true,
            ]);
            $id = DB::table('solicitudes_certificacion')->insertGetId([
                'user_id' => $u->id, 'tipo_tramite' => $tipo, 'certificacion' => 'ANFECA', 'estado' => $estado,
                'tipo_personal' => 'Profesoras y profesores de Tiempo Completo', 'area_certificacion' => 'Administración',
                'fecha_envio' => $estado === 'enviado' ? now()->subMinutes(10 - $n) : null,
                'compromiso_aceptado' => $estado === 'enviado', 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($institucion !== null) {
                $ruta = "anfeca/documentos/solicitudes/{$id}/titulo.pdf";
                Storage::disk('local')->put($ruta, "%PDF-1.4 ficticio {$clave}");
                DB::table('formaciones_academicas_solicitud')->insert([
                    'solicitud_id' => $id, 'nivel_origen' => 'Licenciatura', 'titulo_origen' => 'Administración',
                    'institucion_afiliada_id' => $institucion, 'titulo_origen_archivo' => $ruta,
                ]);
                DB::table('documentos_probatorios_solicitud')->insert([
                    'solicitud_id' => $id, 'comprobante_pago_archivo' => $ruta, 'constancia_antiguedad_archivo' => $ruta,
                ]);
            }
            $this->sol[$clave] = $id;
        };

        $crear('ZF3', $inst['ZF3'], 'enviado', 'inicial');
        $crear('ZF4', $inst['ZF4'], 'enviado', 'refrendo');
        $crear('ZF6', $inst['ZF6'], 'enviado', 'refrendo');
        $crear('SIN', $inst['SIN'], 'enviado', 'inicial');      // institución sin zona
        $crear('BORR', null, 'borrador', 'inicial');            // sin formación: zona indeterminable
        $crear('ZF3B', $inst['ZF3'], 'borrador', 'inicial');
        $this->crear = $crear;
        $this->inst = $inst;

        $this->solicitante = User::where('email', 'sol1@example.test')->firstOrFail();

        foreach ([
            'nacional' => ['coordinador_nacional', []],
            'z3' => ['coordinador_zona', ['ZF3']],
            'z4' => ['coordinador_zona', ['ZF4']],
            'z6' => ['coordinador_zona', ['ZF6']],
            'sin' => ['coordinador_zona', []],
        ] as $clave => [$rol, $zonas]) {
            $u = new User();
            $u->forceFill(['name' => "Coordinación {$clave}", 'email' => "coord.{$clave}@anfeca.test",
                'password' => Hash::make('ContrasenaCoordinacion1'), 'rol' => $rol])->save();
            foreach ($zonas as $s) {
                DB::table('coordinador_zona')->insert(['user_id' => $u->id, 'zona_id' => $this->zona[$s]]);
            }
            $this->coord[$clave] = $u;
        }
    }

    private function bandeja(User $u, array $p = []): array
    {
        return $this->actingAs($u)->getJson('/coordinacion/api/solicitudes?'.http_build_query($p + ['por_pagina' => 50]))
            ->assertOk()->json();
    }

    private function folios(User $u, array $p = []): array
    {
        $f = array_column($this->bandeja($u, $p)['datos'], 'folio');
        sort($f);

        return $f;
    }

    private function ids(string ...$claves): array
    {
        $ids = array_map(fn ($c) => $this->sol[$c], $claves);
        sort($ids);

        return $ids;
    }

    // ------------------------------------------------------------------ acceso

    public function test_acceso_de_los_cuatro_coordinadores_y_redireccion_tras_el_login(): void
    {
        foreach (['nacional', 'z3', 'z4', 'z6'] as $c) {
            $this->post('/login', ['email' => "coord.{$c}@anfeca.test", 'password' => 'ContrasenaCoordinacion1'])
                ->assertRedirect(route('coordinacion.inicio'));
            $this->get('/coordinacion')->assertOk()->assertSee('vue-coordinacion-inicio', false)->assertSee('Pendiente');
            $this->get('/coordinacion/solicitudes')->assertOk()->assertSee('vue-coordinacion-bandeja', false);
            $this->get('/coordinacion/api/indicadores')->assertOk();
            $this->post('/logout');
            $this->assertGuest();
        }
    }

    public function test_solicitantes_e_invitados_no_acceden_al_modulo(): void
    {
        $this->get('/coordinacion')->assertRedirect(route('login'));
        $this->getJson('/coordinacion/api/solicitudes')->assertUnauthorized();

        foreach (['/coordinacion', '/coordinacion/solicitudes', '/coordinacion/api/indicadores', '/coordinacion/api/solicitudes',
                  "/coordinacion/solicitudes/{$this->sol['ZF3']}", "/coordinacion/solicitudes/{$this->sol['ZF3']}/documentos/titulo_origen_archivo"] as $url) {
            $this->actingAs($this->solicitante)->get($url)->assertForbidden();
        }
    }

    public function test_coordinacion_no_usa_el_flujo_de_solicitantes(): void
    {
        $c = $this->coord['z3'];
        foreach (['/', '/panel', '/mis-datos', '/registro/datos-personales', '/registro/certificacion', '/registro/documentos-probatorios'] as $url) {
            $this->actingAs($c)->get($url)->assertRedirect(route('coordinacion.inicio'));
        }
        $this->actingAs($c)->post('/registro/datos-personales', ['curp' => 'X'])->assertRedirect(route('coordinacion.inicio'));
        $this->assertFalse(DB::table('datos_solicitantes')->where('user_id', $c->id)->exists());
    }

    public function test_el_alta_publica_asigna_siempre_el_rol_solicitante(): void
    {
        $this->post('/registro/datos-personales', [
            'curp' => 'FICT900101HDFRRN09', 'rfc' => 'FICT900101AB9', 'nombres' => 'Nueva', 'apellido_paterno' => 'Ficticia',
            'genero' => 'Femenino', 'calle' => 'C', 'numero_exterior' => '1', 'codigo_postal' => '01000',
            'entidad_federativa' => 'Ciudad de México', 'colonia' => 'C', 'municipio' => 'M',
            'correo_electronico' => 'nueva@example.test', 'telefono_celular' => '5511111111',
            'password' => 'ContrasenaFicticia1', 'password_confirmation' => 'ContrasenaFicticia1',
            'rol' => 'coordinador_nacional',
        ])->assertRedirect(route('registro.certificacion'));

        $this->assertSame('solicitante', DB::table('users')->where('email', 'nueva@example.test')->value('rol'));
        $this->get('/coordinacion')->assertForbidden();
    }

    // ------------------------------------------------------- aislamiento por zona

    public function test_aislamiento_por_zona_y_alcance_nacional(): void
    {
        $this->assertSame($this->ids('ZF3', 'ZF3B'), $this->folios($this->coord['z3']));
        $this->assertSame($this->ids('ZF4'), $this->folios($this->coord['z4']));
        $this->assertSame($this->ids('ZF6'), $this->folios($this->coord['z6']));
        $this->assertSame($this->ids('ZF3', 'ZF4', 'ZF6', 'SIN', 'BORR', 'ZF3B'), $this->folios($this->coord['nacional']));

        // Solo la coordinación nacional ve las «pendientes de clasificación».
        $this->assertSame($this->ids('SIN', 'BORR'), $this->folios($this->coord['nacional'], ['zona' => 'sin_zona']));
    }

    public function test_coordinador_de_zona_sin_asignacion_no_ve_nada(): void
    {
        $c = $this->coord['sin'];
        $this->assertSame([], $this->folios($c));
        $ind = $this->actingAs($c)->getJson('/coordinacion/api/indicadores')->assertOk()->json();
        $this->assertSame(0, collect($ind['tarjetas'])->firstWhere('clave', 'total')['valor']);
        $this->assertSame([], $ind['recientes']);
        $this->actingAs($c)->get("/coordinacion/solicitudes/{$this->sol['ZF3']}")->assertNotFound();
        $this->actingAs($c)->get('/coordinacion')->assertOk()->assertSee('Sin zona asignada');
    }

    public function test_acceso_directo_por_url_a_expedientes_y_documentos_de_otra_zona(): void
    {
        $z3 = $this->coord['z3'];

        $this->actingAs($z3)->get("/coordinacion/solicitudes/{$this->sol['ZF3']}")->assertOk()->assertSee('Institución Tres');
        $this->actingAs($z3)->get("/coordinacion/solicitudes/{$this->sol['ZF3']}/documentos/titulo_origen_archivo")
            ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        foreach (['ZF4', 'ZF6', 'SIN', 'BORR'] as $ajena) {
            $this->actingAs($z3)->get("/coordinacion/solicitudes/{$this->sol[$ajena]}")->assertNotFound();
            foreach (['titulo_origen_archivo', 'comprobante_pago_archivo', 'constancia_antiguedad_archivo'] as $campo) {
                $r = $this->actingAs($z3)->get("/coordinacion/solicitudes/{$this->sol[$ajena]}/documentos/{$campo}");
                $r->assertNotFound();
                $this->assertNull($r->headers->get('content-disposition'));
            }
        }

        // Inexistente, campo no permitido y rutas manipuladas.
        $this->actingAs($z3)->get('/coordinacion/solicitudes/999999')->assertNotFound();
        $this->actingAs($z3)->get("/coordinacion/solicitudes/{$this->sol['ZF3']}/documentos/password")->assertNotFound();
        $this->actingAs($z3)->get("/coordinacion/solicitudes/{$this->sol['ZF3']}/documentos/..%2F..%2F.env")->assertNotFound();

        // La coordinación nacional sí accede a todas.
        $this->actingAs($this->coord['nacional'])->get("/coordinacion/solicitudes/{$this->sol['SIN']}")->assertOk()->assertSee('Sin clasificar')->assertDontSee('Pendiente de clasificación');
        $this->actingAs($this->coord['nacional'])->get("/coordinacion/solicitudes/{$this->sol['ZF6']}/documentos/comprobante_pago_archivo")->assertOk();
    }

    public function test_manipulacion_de_parametros(): void
    {
        $z3 = $this->coord['z3'];

        $this->actingAs($z3)->getJson('/coordinacion/api/solicitudes?zona='.$this->zona['ZF4'])->assertForbidden();
        $this->actingAs($z3)->getJson('/coordinacion/api/solicitudes?zona=sin_zona')->assertForbidden();
        $this->actingAs($z3)->getJson('/coordinacion/api/solicitudes?zona=1%20or%201=1')->assertUnprocessable();
        $this->actingAs($z3)->getJson('/coordinacion/api/solicitudes?orden=s.id;drop%20table%20users')->assertUnprocessable();
        $this->actingAs($z3)->getJson('/coordinacion/api/solicitudes?por_pagina=100000')->assertUnprocessable();

        // Institución de otra zona: sin resultados (no amplía el alcance).
        $inst4 = DB::table('instituciones_afiliadas')->where('numero_afiliacion', 'I4')->value('id');
        $this->assertSame([], $this->folios($z3, ['institucion' => $inst4]));
        // Búsqueda por folio ajeno: no aparece.
        $this->assertSame([], $this->folios($z3, ['q' => (string) $this->sol['ZF4']]));
        // Su propia zona sí.
        $this->assertSame($this->ids('ZF3', 'ZF3B'), $this->folios($z3, ['zona' => $this->zona['ZF3']]));
    }

    // ------------------------------------------------ indicadores y consistencia

    public function test_indicadores_reales_y_consistentes_con_la_bandeja(): void
    {
        foreach (['nacional', 'z3', 'z4', 'z6', 'sin'] as $c) {
            $u = $this->coord[$c];
            $ind = $this->actingAs($u)->getJson('/coordinacion/api/indicadores')->assertOk()->json();
            $t = collect($ind['tarjetas'])->pluck('valor', 'clave');
            $b = $this->bandeja($u);

            $this->assertSame($b['meta']['total'], $t['total'], "total ({$c})");
            $this->assertSame($b['meta']['por_estado']['enviado'], $t['enviadas'], "enviadas ({$c})");
            $this->assertSame($b['meta']['por_estado']['borrador'], $t['iniciadas'], "iniciadas ({$c})");
            $this->assertSame($t['total'], $t['iniciadas'] + $t['enviadas']);
            $this->assertSame($t['total'], $t['primera_vez'] + $t['refrendos']);
            $this->assertSame($t['total'], array_sum(array_column($ind['distribucion']['zona'], 'valor')));
            $this->assertSame($t['total'], array_sum(array_column($ind['distribucion']['institucion'], 'valor')));
            $this->assertSame($t['total'], array_sum(array_column($ind['distribucion']['tipo_tramite'], 'valor')));
            $this->assertSame(count($this->folios($u, ['estado' => 'enviado'])), $t['enviadas'], "filtro de estado ({$c})");

            // Estados de evaluación: no implementados (null), nunca cero.
            foreach ($ind['evaluacion'] as $e) {
                $this->assertFalse($e['implementado']);
                $this->assertNull($e['valor']);
            }
        }

        $nac = collect($this->actingAs($this->coord['nacional'])->getJson('/coordinacion/api/indicadores')->json('tarjetas'))->pluck('valor', 'clave');
        $this->assertSame(6, $nac['usuarios'], 'Usuarios: 6 solicitantes; excluye las 5 cuentas de coordinación.');
        $this->assertSame(6, $nac['total']);
        $this->assertSame(4, $nac['enviadas']);
        $this->assertSame(2, $nac['iniciadas']);
        $this->assertSame(2, $nac['refrendos']);

        $sinZona = collect($this->actingAs($this->coord['nacional'])->getJson('/coordinacion/api/indicadores')->json('distribucion.zona'))
            ->firstWhere('sin_clasificar', true);
        $this->assertSame(2, $sinZona['valor']);

        $z3 = collect($this->actingAs($this->coord['z3'])->getJson('/coordinacion/api/indicadores')->json('distribucion.zona'));
        $this->assertNull($z3->firstWhere('sin_clasificar', true), 'La zona no ve solicitudes sin clasificar.');
    }

    public function test_orden_paginacion_y_busqueda(): void
    {
        $nac = $this->coord['nacional'];
        $pag = $this->actingAs($nac)->getJson('/coordinacion/api/solicitudes?por_pagina=10&orden=folio&direccion=asc')->assertOk()->json();
        $this->assertSame($this->ids('ZF3', 'ZF4', 'ZF6', 'SIN', 'BORR', 'ZF3B'), array_column($pag['datos'], 'folio'));
        $this->assertSame(1, $pag['meta']['ultima_pagina']);

        $desc = array_column($this->actingAs($nac)->getJson('/coordinacion/api/solicitudes?orden=folio&direccion=desc')->json('datos'), 'folio');
        $this->assertSame(array_reverse($this->ids('ZF3', 'ZF4', 'ZF6', 'SIN', 'BORR', 'ZF3B')), $desc);

        $this->assertSame($this->ids('ZF4', 'ZF6'), $this->folios($nac, ['tipo_tramite' => 'refrendo']));
        $this->assertSame($this->ids('ZF4'), $this->folios($nac, ['q' => 'cuatro']));
        $this->assertSame($this->ids('ZF3B'), $this->folios($nac, ['estado' => 'borrador', 'institucion' => DB::table('instituciones_afiliadas')->where('numero_afiliacion', 'I3')->value('id')]));
    }

    // ------------------------------------------------- numeración consecutiva

    public function test_numeracion_consecutiva_continua_entre_paginas_y_con_filtros(): void
    {
        // 6 de base + 15 adicionales en la zona 3 = 21 registros (3 páginas de 10).
        for ($k = 1; $k <= 15; $k++) {
            ($this->crear)("EXTRA{$k}", $this->inst['ZF3'], 'enviado', 'inicial');
        }
        $nac = $this->coord['nacional'];
        $pag = fn (int $p, array $extra = []) => $this->bandeja($nac, ['por_pagina' => 10, 'pagina' => $p, 'orden' => 'folio', 'direccion' => 'asc'] + $extra);

        $p1 = $pag(1); $p2 = $pag(2); $p3 = $pag(3);
        $this->assertSame(range(1, 10), array_column($p1['datos'], 'num'));
        $this->assertSame(range(11, 20), array_column($p2['datos'], 'num'));
        $this->assertSame([21], array_column($p3['datos'], 'num'));
        $this->assertSame([11, 20, 21], [$p2['meta']['desde'], $p2['meta']['hasta'], $p2['meta']['total']]);

        // Independiente del folio: el consecutivo no coincide con el id y no cambia al invertir el orden.
        $todos = array_merge($p1['datos'], $p2['datos'], $p3['datos']);
        $this->assertSame(range(1, 21), array_column($todos, 'num'));
        $desc = $this->bandeja($nac, ['por_pagina' => 10, 'orden' => 'folio', 'direccion' => 'desc']);
        $this->assertSame(range(1, 10), array_column($desc['datos'], 'num'));
        $this->assertSame(max(array_column($todos, 'folio')), $desc['datos'][0]['folio']);
        $this->assertNotSame(array_column($desc['datos'], 'folio'), array_column($desc['datos'], 'num'));

        // Se recalcula con búsqueda y filtros: siempre inicia en 1 y es consecutivo.
        foreach ([['q' => 'cuatro'], ['tipo_tramite' => 'refrendo'], ['estado' => 'borrador'], ['zona' => 'sin_zona']] as $filtro) {
            $d = $this->bandeja($nac, $filtro)['datos'];
            $this->assertSame(range(1, count($d)), array_column($d, 'num'), json_encode($filtro));
        }

        // Respeta el alcance: la zona 4 solo ve su registro, numerado como 1.
        $z4 = $this->bandeja($this->coord['z4'])['datos'];
        $this->assertSame([1], array_column($z4, 'num'));
        $this->assertSame($this->ids('ZF4'), array_column($z4, 'folio'));
        // Zona 3: 2 de base + 15 adicionales.
        $this->assertSame(range(1, 17), array_column($this->bandeja($this->coord['z3'])['datos'], 'num'));
        $this->assertSame([], $this->bandeja($this->coord['sin'])['datos']);

        // Página fuera de rango: sin filas ni números.
        $vacia = $pag(9);
        $this->assertSame([], $vacia['datos']);
        $this->assertNull($vacia['meta']['desde']);

        // Sin columnas nuevas en la base para este propósito.
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('solicitudes_certificacion', 'num'));
    }

    public function test_numeracion_en_solicitudes_recientes_del_tablero(): void
    {
        $rec = $this->actingAs($this->coord['nacional'])->getJson('/coordinacion/api/indicadores')->assertOk()->json('recientes');
        $this->assertSame(range(1, count($rec)), array_column($rec, 'num'));
        $this->assertCount(5, $rec);
        $z4 = $this->actingAs($this->coord['z4'])->getJson('/coordinacion/api/indicadores')->assertOk()->json('recientes');
        $this->assertSame([1], array_column($z4, 'num'));
    }

    public function test_columna_num_es_primera_compacta_y_no_ordenable_en_las_tablas(): void
    {
        $vue = [
            'BandejaSolicitudes' => file_get_contents(resource_path('js/components/BandejaSolicitudes.vue')),
            'CoordinacionInicio' => file_get_contents(resource_path('js/components/CoordinacionInicio.vue')),
        ];
        foreach ($vue as $nombre => $fuente) {
            // La primera celda de encabezado de cada tabla es «Núm.» y no lleva botón de orden.
            $this->assertMatchesRegularExpression('#<thead>\s*<tr>\s*<th scope="col" class="col-num"[^>]*>Núm\.</th>#', $fuente, $nombre);
            $this->assertMatchesRegularExpression('#<td class="col-num">\{\{ \w+\.num \}\}</td>#', $fuente, $nombre);
        }
        // «num» no es un criterio de ordenamiento aceptado por la API.
        $this->actingAs($this->coord['nacional'])->getJson('/coordinacion/api/solicitudes?orden=num')->assertStatus(422);
        $css = file_get_contents(public_path('css/coordinacion.css'));
        $this->assertStringContainsString('.coord-tabla td.col-num', $css);
    }

    // ------------------------------------------------------------- integridad

    public function test_la_consulta_no_modifica_solicitudes_y_no_hay_operaciones_de_escritura(): void
    {
        $antes = DB::table('solicitudes_certificacion')->orderBy('id')->get()->toArray();
        $docsAntes = DB::table('documentos_probatorios_solicitud')->orderBy('id')->get()->toArray();

        $n = $this->coord['nacional'];
        $this->actingAs($n)->get('/coordinacion');
        $this->actingAs($n)->getJson('/coordinacion/api/indicadores');
        $this->bandeja($n);
        foreach ($this->sol as $id) {
            $this->actingAs($n)->get("/coordinacion/solicitudes/{$id}");
            $this->actingAs($n)->get("/coordinacion/solicitudes/{$id}/documentos/titulo_origen_archivo");
        }

        foreach (['post', 'put', 'patch', 'delete'] as $m) {
            $this->assertContains($this->actingAs($n)->{$m}("/coordinacion/solicitudes/{$this->sol['ZF3']}")->getStatusCode(), [404, 405]);
        }

        $this->assertEquals($antes, DB::table('solicitudes_certificacion')->orderBy('id')->get()->toArray());
        $this->assertEquals($docsAntes, DB::table('documentos_probatorios_solicitud')->orderBy('id')->get()->toArray());
    }

    // ------------------------------------------------- comando de alta segura

    public function test_comando_de_alta_de_coordinadores(): void
    {
        $dir = sys_get_temp_dir().'/anfeca_cred_'.bin2hex(random_bytes(4));
        mkdir($dir, 0700);
        $archivo = $dir.'/cred.tsv';

        // No convierte cuentas de solicitantes.
        $this->artisan('anfeca:coordinador-alta', ['correo' => 'sol1@example.test', 'rol' => 'coordinador_nacional', '--credenciales' => $archivo])
            ->assertExitCode(1);
        $this->assertSame('solicitante', DB::table('users')->where('email', 'sol1@example.test')->value('rol'));

        // Zona inexistente: no escribe nada.
        $this->artisan('anfeca:coordinador-alta', ['correo' => 'nuevo.z@anfeca.test', 'rol' => 'coordinador_zona', '--zona' => ['NOEXISTE'], '--credenciales' => $archivo])
            ->assertExitCode(1);
        $this->assertFalse(DB::table('users')->where('email', 'nuevo.z@anfeca.test')->exists());

        // Credenciales dentro del proyecto: rechazado.
        $this->artisan('anfeca:coordinador-alta', ['correo' => 'nuevo.z@anfeca.test', 'rol' => 'coordinador_zona', '--credenciales' => base_path('cred.tsv')])
            ->assertExitCode(1);
        $this->assertFileDoesNotExist(base_path('cred.tsv'));

        // Alta correcta, idempotente, por siglas.
        $this->artisan('anfeca:coordinador-alta', ['correo' => 'nuevo.z@anfeca.test', 'rol' => 'coordinador_zona', '--zona' => ['ZF4'], '--credenciales' => $archivo])
            ->doesntExpectOutputToContain("\t")
            ->assertExitCode(0);
        $u = User::where('email', 'nuevo.z@anfeca.test')->firstOrFail();
        $this->assertSame('coordinador_zona', $u->rol);
        $this->assertSame([$this->zona['ZF4']], DB::table('coordinador_zona')->where('user_id', $u->id)->pluck('zona_id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame('0600', substr(sprintf('%o', fileperms($archivo)), -4));
        [, , $clave] = explode("\t", trim(file_get_contents($archivo)));
        $this->assertGreaterThanOrEqual(12, strlen($clave));
        $this->assertTrue(Hash::check($clave, $u->password));
        $this->assertNotSame($clave, $u->password);

        $this->artisan('anfeca:coordinador-alta', ['correo' => 'nuevo.z@anfeca.test', 'rol' => 'coordinador_zona', '--zona' => ['ZF3']])
            ->assertExitCode(0);
        $this->assertTrue(Hash::check($clave, $u->fresh()->password), 'Sin --restablecer conserva la contraseña.');
        $this->assertSame([$this->zona['ZF3']], DB::table('coordinador_zona')->where('user_id', $u->id)->pluck('zona_id')->map(fn ($v) => (int) $v)->all());

        unlink($archivo);
        rmdir($dir);
    }

    // ------------------------------------------------- homologación visual

    public function test_layout_administrativo_homologado_con_el_modulo_de_solicitantes(): void
    {
        foreach (['/coordinacion', '/coordinacion/solicitudes', "/coordinacion/solicitudes/{$this->sol['ZF3']}"] as $url) {
            $this->actingAs($this->coord['nacional'])->get($url)->assertOk()
                ->assertSee('<header class="encabezado">', false)
                ->assertSee('CERTIFICACIÓN DE LA ANFECA')
                ->assertSee('Coordinación Nacional de')
                ->assertSee('<footer class="pie-pagina">', false)
                ->assertSee('Hecho en México')
                ->assertSee('Sitio web administrado por el Centro de Informática')
                ->assertSee('css/certificacion.css', false)
                ->assertSee('css/barra-usuario-certificacion.css', false)
                ->assertSee('barra-usuario barra-usuario-certificacion', false)
                ->assertSee('Cerrar sesión')
                ->assertSee('Coordinación nacional')
                ->assertSee('Todas las zonas')
                ->assertSee('class="coord-menu"', false);
        }
    }

    public function test_consulta_con_dos_fichas_y_cuadricula_de_documentos(): void
    {
        $html = $this->actingAs($this->coord['z3'])->get("/coordinacion/solicitudes/{$this->sol['ZF3']}")
            ->assertOk()->getContent();

        // Dos fichas en el orden obligatorio; formación académica dentro de la ficha del solicitante.
        $this->assertSame(2, substr_count($html, 'class="coord-panel coord-ficha '));
        $posSolicitante = strpos($html, 'Ficha del solicitante');
        $posFormacion = strpos($html, 'Formación académica');
        $posSolicitud = strpos($html, 'Datos de solicitud');
        $this->assertTrue($posSolicitante < $posFormacion && $posFormacion < $posSolicitud);
        $this->assertStringContainsString('class="coord-divisor"', $html);
        foreach (['Nombre completo', 'CURP', 'Correo electrónico', 'Institución Tres', 'Facultad Tres', 'Campus', 'Zona ANFECA',
                  'Nivel de origen', 'Título de origen', 'Último grado', 'Título del último grado',
                  'Folio de solicitud', 'Tipo de trámite', 'Tipo de personal', 'Área de conocimiento', 'Fecha de envío', 'Compromiso de entrega'] as $texto) {
            $this->assertStringContainsString($texto, $html, $texto);
        }

        // Cuadrícula: primer grupo de 6 en 3 columnas × 2 (columna por columna) y estados de carga.
        $this->assertSame(1, substr_count($html, 'class="coord-documentos-grupo"'));
        $this->assertSame(3, substr_count($html, 'class="coord-documentos-columna"'));
        preg_match_all('/data-documento="([a-z0-9_]+)"/', $html, $m);
        $this->assertSame(['titulo_origen_archivo', 'cedula_origen_archivo', 'titulo_ultimo_grado_archivo',
            'cedula_ultimo_grado_archivo', 'constancia_antiguedad_archivo', 'comprobante_pago_archivo'], $m[1]);
        $this->assertStringContainsString('coord-documento cargado', $html);
        $this->assertStringContainsString('coord-documento no-cargado', $html);
        $this->assertStringNotContainsString('Validado', $html, 'Cargado no equivale a validado.');
        // 6.2.4: fichas 50/50 y leyenda de revisión una sola vez para toda la sección.
        $this->assertSame(1, substr_count($html, 'Revisión documental: pendiente (fase 6.3)'));
        $this->assertStringNotContainsString('class="coord-documentos-adicionales"', $html, 'Primera vez: sin adicionales aplicables.');
        $this->assertStringContainsString('No aplica', $html, 'Los documentos de artículo 13/14 no aplican a primera vez.');
        $this->assertStringContainsString("/coordinacion/solicitudes/{$this->sol['ZF3']}/documentos/titulo_origen_archivo", $html);
        $this->assertStringNotContainsString("/documentos/cedula_origen_archivo\"", $html, 'Sin enlace para documentos no cargados.');
    }

    public function test_refrendo_por_articulo_14_muestra_documentos_adicionales_en_un_segundo_grupo(): void
    {
        $id = $this->sol['ZF4'];
        DB::table('datos_refrendo')->insert(['solicitud_id' => $id, 'aplica_articulo_14' => true, 'inciso_articulo_14' => '14.1']);
        $ruta = "anfeca/documentos/solicitudes/{$id}/sol14.pdf";
        Storage::disk('local')->put($ruta, '%PDF-1.4 ficticio art14');
        DB::table('documentos_probatorios_solicitud')->where('solicitud_id', $id)->update(['solicitud_articulo_14_archivo' => $ruta]);

        $html = $this->actingAs($this->coord['z4'])->get("/coordinacion/solicitudes/{$id}")->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'class="coord-documentos-grupo"'), 'Los 6 principales en un grupo.');
        $this->assertStringContainsString('Documentos adicionales — Artículo 13/14', $html);
        $this->assertStringContainsString('class="coord-documentos-adicionales" style="--columnas: 2"', $html, 'Dos adicionales en dos columnas.');
        $this->assertStringContainsString('data-documento="solicitud_articulo_14_archivo"', $html);
        $this->assertStringContainsString('data-documento="constancia_articulo_14_archivo"', $html);
        $this->assertStringContainsString('Sí, inciso 14.1', $html);
        $this->assertStringNotContainsString('coord-documentos-no-aplican', $html);

        $this->actingAs($this->coord['z4'])->get("/coordinacion/solicitudes/{$id}/documentos/solicitud_articulo_14_archivo")->assertOk();
        $this->actingAs($this->coord['z3'])->get("/coordinacion/solicitudes/{$id}/documentos/solicitud_articulo_14_archivo")->assertNotFound();
    }

    // ------------------------------------- prototipo aislado de revisión (6.3.0)

    public function test_prototipo_de_revision_desactivado_por_omision(): void
    {
        $this->assertFalse(config('anfeca_coordinacion.prototipos'));
        $this->actingAs($this->coord['nacional'])->get('/coordinacion/prototipos/revision-documental')->assertNotFound();
    }

    public function test_prototipo_de_revision_aislado_y_sin_persistencia(): void
    {
        config(['anfeca_coordinacion.prototipos' => true]);
        $tablas = ['solicitudes_certificacion', 'documentos_probatorios_solicitud', 'formaciones_academicas_solicitud', 'datos_solicitantes', 'users'];
        $antes = collect($tablas)->mapWithKeys(fn ($t) => [$t => DB::table($t)->orderBy('id')->get()->toArray()]);

        $this->actingAs($this->coord['z3'])->get('/coordinacion/prototipos/revision-documental')->assertOk()
            ->assertSee('PROTOTIPO DEMOSTRATIVO')
            ->assertSee('id="vue-prototipo-revision"', false)
            ->assertSee('<header class="encabezado">', false);
        $this->actingAs($this->solicitante)->get('/coordinacion/prototipos/revision-documental')->assertForbidden();
        $this->post('/logout');
        $this->get('/coordinacion/prototipos/revision-documental')->assertRedirect(route('login'));

        foreach ($tablas as $t) {
            $this->assertEquals($antes[$t], DB::table($t)->orderBy('id')->get()->toArray(), "Sin cambios en {$t}");
        }

        // El componente no hace peticiones de red ni usa almacenamiento persistente.
        $vue = file_get_contents(resource_path('js/components/RevisionDocumentalPrototipo.vue'));
        foreach (['fetch(', 'axios', 'XMLHttpRequest', 'localStorage', 'sessionStorage', 'indexedDB'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $vue, $prohibido);
        }
        foreach (['Por revisar', 'Validado', 'Con observaciones', 'No válido', 'No aplica'] as $estado) {
            $this->assertStringContainsString($estado, $vue);
        }
    }
}
