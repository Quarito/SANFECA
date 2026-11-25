<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Zonas\ResolucionZona;
use App\Services\Zonas\ZonasAnfeca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Bloque B · Clasificación y alcance por zona.
 * Clasificación VISUAL (ZonasAnfeca) frente a AUTORIZACIÓN (zona_id + coordinador_zona).
 */
class ZonasAnfecaTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refreshDatabaseLaravel;
    }

    private array $inst = [];
    private array $sol = [];
    private array $coord = [];
    private array $zona = [];

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

        // Catálogo normalizado con nombres oficiales (como lo propondría la normalización).
        $this->zona[3] = DB::table('zonas')->insertGetId(['siglas' => 'Z3', 'descripcion' => 'Zona 3 - Centro']);
        $this->zona[6] = DB::table('zonas')->insertGetId(['siglas' => 'Z6', 'descripcion' => 'Zona 6 - Sur']);

        $alta = fn (string $k, array $datos) => $this->inst[$k] = DB::table('instituciones_afiliadas')->insertGetId($datos + ['numero_afiliacion' => "A{$k}", 'nombre' => "Institución {$k}"]);
        $alta('NORMAL', ['zona_id' => $this->zona[3], 'zona_anfeca' => 'Zona 3 - Centro', 'sede' => 'Zacatecas']);
        $alta('TEXTO', ['zona_id' => null, 'zona_anfeca' => 'Zona 6 - Sur', 'sede' => 'Chiapas']);
        $alta('TEXTO4', ['zona_id' => null, 'zona_anfeca' => 'Zona 4 - Centro Occidente', 'sede' => 'Guanajuato']);
        $alta('VACIA', ['zona_id' => null, 'zona_anfeca' => null]);
        $alta('CONTRA', ['zona_id' => $this->zona[6], 'zona_anfeca' => 'Zona 3 - Centro']);
        $alta('AMBIGUA', ['zona_id' => null, 'zona_anfeca' => 'Zona 3 - Sur']);
        $alta('ENTIDAD', ['zona_id' => null, 'zona_anfeca' => 'Zona 6 - Sur', 'sede' => 'Jalisco']);

        $n = 0;
        foreach (['NORMAL', 'TEXTO', 'TEXTO4', 'VACIA', 'CONTRA', 'AMBIGUA', 'ENTIDAD', null] as $k) {
            $n++;
            $u = User::create(['name' => "Sol {$n}", 'email' => "sol{$n}@example.test", 'password' => Hash::make('ContrasenaFicticia1')]);
            $id = DB::table('solicitudes_certificacion')->insertGetId(['user_id' => $u->id, 'tipo_tramite' => 'inicial', 'certificacion' => 'ANFECA',
                'estado' => 'enviado', 'fecha_envio' => now(), 'compromiso_aceptado' => true, 'created_at' => now(), 'updated_at' => now()]);
            if ($k !== null) {
                DB::table('formaciones_academicas_solicitud')->insert(['solicitud_id' => $id, 'nivel_origen' => 'Licenciatura', 'institucion_afiliada_id' => $this->inst[$k]]);
            }
            $this->sol[$k ?? 'SIN_INSTITUCION'] = $id;
        }

        foreach (['nacional' => ['coordinador_nacional', []], 'z3' => ['coordinador_zona', [3]], 'z6' => ['coordinador_zona', [6]], 'sin' => ['coordinador_zona', []]] as $k => [$rol, $zonas]) {
            $u = new User();
            $u->forceFill(['name' => "Coord {$k}", 'email' => "c.{$k}@anfeca.test", 'password' => Hash::make('ContrasenaCoordinacion1'), 'rol' => $rol])->save();
            foreach ($zonas as $z) {
                DB::table('coordinador_zona')->insert(['user_id' => $u->id, 'zona_id' => $this->zona[$z]]);
            }
            $this->coord[$k] = $u;
        }
    }

    private function bandeja(string $quien, array $p = [])
    {
        return $this->actingAs($this->coord[$quien])->getJson('/coordinacion/api/solicitudes?'.http_build_query($p + ['por_pagina' => 50, 'orden' => 'folio', 'direccion' => 'asc']));
    }

    private function zonasDe(string $quien, array $p = []): array
    {
        return collect($this->bandeja($quien, $p)->assertOk()->json('datos'))->mapWithKeys(fn ($r) => [$r['folio'] => $r['zona']])->all();
    }

    public function test_resolucion_por_origen(): void
    {
        $z = app(ZonasAnfeca::class);
        $casos = [
            'NORMAL' => ['z3', 'Zona 3 - Centro', ResolucionZona::NORMALIZADA],
            'TEXTO' => ['z6', 'Zona 6 - Sur', ResolucionZona::DATO_INSTITUCIONAL],
            'TEXTO4' => ['z4', 'Zona 4 - Centro Occidente', ResolucionZona::DATO_INSTITUCIONAL],
            'VACIA' => ['sin_zona', 'Sin clasificar', ResolucionZona::SIN_CLASIFICAR],
            'CONTRA' => ['z6', 'Zona 6 - Sur (contradicción)', ResolucionZona::CONTRADICTORIA],
            'AMBIGUA' => ['sin_zona', 'Sin clasificar', ResolucionZona::CONTRADICTORIA],
        ];
        foreach ($casos as $k => [$clave, $etiqueta, $origen]) {
            $r = $z->deInstitucion($this->inst[$k]);
            $this->assertSame([$clave, $etiqueta, $origen], [$r->clave, $r->etiqueta, $r->origen], $k);
        }
        $this->assertNotEmpty($z->deInstitucion($this->inst['CONTRA'])->advertencias);
        $this->assertStringContainsString('pertenece oficialmente a la zona 4', implode(' ', $z->deInstitucion($this->inst['ENTIDAD'])->advertencias));
        $this->assertSame('sin_zona', $z->deInstitucion(null)->clave);
        $this->assertCount(7, $z->oficiales());
    }

    public function test_pantallas_muestran_la_zona_resuelta_y_nunca_pendiente(): void
    {
        $zonas = $this->zonasDe('nacional');
        $this->assertSame('Zona 6 - Sur', $zonas[$this->sol['TEXTO']]);
        $this->assertSame('Zona 4 - Centro Occidente', $zonas[$this->sol['TEXTO4']]);
        $this->assertSame('Sin clasificar', $zonas[$this->sol['VACIA']]);
        $this->assertSame('Sin clasificar', $zonas[$this->sol['SIN_INSTITUCION']]);

        $ind = $this->actingAs($this->coord['nacional'])->getJson('/coordinacion/api/indicadores')->assertOk()->json();
        $dist = collect($ind['distribucion']['zona'])->keyBy('etiqueta');
        // La contradicción conserva la zona de su zona_id (coherente con la autorización regional).
        $this->assertSame(3, $dist['Zona 6 - Sur']['valor'], 'TEXTO + ENTIDAD + CONTRA');
        $this->assertSame(3, $dist['Sin clasificar']['valor'], 'VACIA + AMBIGUA + sin institución');
        $this->assertSame(8, collect($ind['distribucion']['zona'])->sum('valor'), 'Sin duplicidades');
        foreach ($ind['recientes'] as $r) {
            $this->assertNotSame('Pendiente de clasificación', $r['zona']);
        }

        $html = $this->actingAs($this->coord['nacional'])->get("/coordinacion/solicitudes/{$this->sol['TEXTO']}")->assertOk()->getContent();
        $this->assertStringContainsString('Zona 6 - Sur', $html);
        $this->assertStringContainsString('dato institucional', $html);
        $this->assertStringNotContainsString('Pendiente de clasificación', $html);
        $this->assertStringContainsString('⚠', $this->actingAs($this->coord['nacional'])->get("/coordinacion/solicitudes/{$this->sol['CONTRA']}")->getContent());
    }

    public function test_filtros_por_zona_resuelta(): void
    {
        $this->assertEqualsCanonicalizing([$this->sol['TEXTO'], $this->sol['ENTIDAD'], $this->sol['CONTRA']], array_keys($this->zonasDe('nacional', ['zona' => 'z6'])));
        $this->assertEqualsCanonicalizing([$this->sol['VACIA'], $this->sol['AMBIGUA'], $this->sol['SIN_INSTITUCION']], array_keys($this->zonasDe('nacional', ['zona' => 'sin_zona'])));
        $this->assertEqualsCanonicalizing([$this->sol['NORMAL']], array_keys($this->zonasDe('nacional', ['zona' => 'z3'])));
        $this->bandeja('nacional', ['zona' => 'z9'])->assertStatus(422);
        $this->bandeja('nacional', ['zona' => "' or 1=1"])->assertStatus(422);
    }

    public function test_autorizacion_regional_solo_por_zona_id_y_asignacion(): void
    {
        // Regional 3: solo la institución con zona_id = Z3; nunca las que solo tienen texto «Zona 3».
        $this->assertSame([$this->sol['NORMAL']], array_keys($this->zonasDe('z3')));
        // Regional 6: zona_id = Z6 (la contradictoria); NO la de texto «Zona 6 - Sur» sin zona_id.
        $this->assertSame([$this->sol['CONTRA']], array_keys($this->zonasDe('z6')));
        // Coordinador sin asignación: nada.
        $this->assertSame([], array_keys($this->zonasDe('sin')));

        // Filtros fuera de alcance y «sin clasificar» para regionales → 403.
        $this->bandeja('z3', ['zona' => 'z6'])->assertForbidden();
        $this->bandeja('z3', ['zona' => 'sin_zona'])->assertForbidden();
        $this->bandeja('z3', ['zona' => 'z3'])->assertOk();

        // Rutas directas, documentos e indicadores respetan el alcance.
        foreach (['TEXTO', 'VACIA', 'AMBIGUA', 'SIN_INSTITUCION', 'CONTRA'] as $k) {
            $this->actingAs($this->coord['z3'])->get("/coordinacion/solicitudes/{$this->sol[$k]}")->assertNotFound();
        }
        $this->actingAs($this->coord['sin'])->get("/coordinacion/solicitudes/{$this->sol['NORMAL']}")->assertNotFound();
        $this->actingAs($this->coord['nacional'])->get("/coordinacion/solicitudes/{$this->sol['SIN_INSTITUCION']}")->assertOk();
        $ind = $this->actingAs($this->coord['z3'])->getJson('/coordinacion/api/indicadores')->json();
        $this->assertSame(1, collect($ind['distribucion']['zona'])->sum('valor'));
        $this->assertSame([1], array_column($ind['recientes'], 'num'));
    }

    private function opcionesZona(string $quien): array
    {
        $html = $this->actingAs($this->coord[$quien])->get('/coordinacion/solicitudes')->assertOk()->getContent();
        preg_match('/data-opciones="([^"]*)"/', $html, $m);

        return array_column(json_decode(html_entity_decode($m[1]), true)['zonas'], 'etiqueta');
    }

    public function test_opciones_de_filtro_por_perfil(): void
    {
        $nacional = $this->opcionesZona('nacional');
        foreach (['Zona 1 - Noroeste', 'Zona 7 - Ciudad de México', 'Sin clasificar'] as $t) {
            $this->assertContains($t, $nacional);
        }
        $this->assertCount(8, $nacional, '7 oficiales + Sin clasificar');
        $this->assertSame(['Zona 3 - Centro'], $this->opcionesZona('z3'));
        $this->assertSame([], $this->opcionesZona('sin'));
    }

    public function test_consistencia_entre_pantallas(): void
    {
        $bandeja = $this->zonasDe('nacional');
        $recientes = collect($this->actingAs($this->coord['nacional'])->getJson('/coordinacion/api/indicadores')->json('recientes'))
            ->mapWithKeys(fn ($r) => [$r['folio'] => $r['zona']]);
        foreach ($recientes as $folio => $zona) {
            $this->assertSame($bandeja[$folio], $zona, "folio {$folio}");
        }
    }
}
