<?php

namespace Tests\Feature;

use App\Models\InstitucionAfiliada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Migración 2026_11_20_190000: «escuela_facultad» idempotente y reversión no destructiva.
 * Solo SQLite en memoria.
 */
class MigracionEscuelaFacultadTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refreshDatabaseLaravel;
    }

    private const ARCHIVO = 'migrations/2026_11_20_190000_add_escuela_facultad_to_instituciones_afiliadas.php';

    public function refreshDatabase(): void
    {
        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
        ) {
            throw new RuntimeException('SEGURIDAD: esta prueba requiere SQLite en memoria.');
        }

        $this->refreshDatabaseLaravel();
    }

    private function migracion(): object
    {
        return require database_path(self::ARCHIVO);
    }

    private function columna(): ?array
    {
        return collect(Schema::getColumns('instituciones_afiliadas'))
            ->firstWhere('name', 'escuela_facultad');
    }

    public function test_base_con_columna_existente_no_duplica_ni_pierde_datos(): void
    {
        $this->assertNotNull($this->columna(), 'Tras migrar, la columna debe existir.');

        $id = DB::table('instituciones_afiliadas')->insertGetId([
            'numero_afiliacion' => 'F1', 'nombre' => 'Institución Ficticia',
            'escuela_facultad' => 'Facultad Ficticia',
        ]);

        $m = $this->migracion();
        $m->up();
        $m->up();
        $this->assertSame('Facultad Ficticia', DB::table('instituciones_afiliadas')->where('id', $id)->value('escuela_facultad'));

        // Reversión no destructiva: conserva columna y datos.
        $m->down();
        $this->assertNotNull($this->columna());
        $this->assertSame('Facultad Ficticia', DB::table('instituciones_afiliadas')->where('id', $id)->value('escuela_facultad'));
    }

    public function test_base_sin_columna_la_agrega_como_varchar_255_nullable(): void
    {
        Schema::table('instituciones_afiliadas', fn ($t) => $t->dropColumn('escuela_facultad'));
        $this->assertNull($this->columna());

        $this->migracion()->up();

        $c = $this->columna();
        $this->assertNotNull($c);
        $this->assertTrue($c['nullable']);
        $this->assertStringContainsString('varchar', strtolower($c['type']));

        DB::table('instituciones_afiliadas')->insert(['numero_afiliacion' => 'F2', 'nombre' => 'Sin facultad']);
        $this->assertNull(DB::table('instituciones_afiliadas')->where('numero_afiliacion', 'F2')->value('escuela_facultad'));
    }

    public function test_la_migracion_no_incluye_direccion(): void
    {
        $codigo = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', file_get_contents(database_path(self::ARCHIVO)));
        $this->assertStringNotContainsString('direccion', $codigo);
        $this->assertFalse(Schema::hasColumn('instituciones_afiliadas', 'direccion'));
    }

    public function test_modelo_y_vista_condicional(): void
    {
        $this->assertContains('escuela_facultad', (new InstitucionAfiliada())->getFillable());

        $vue = file_get_contents(resource_path('js/components/FormacionAcademica.vue'));
        $this->assertStringContainsString('v-if="institucionSeleccionada.escuela_facultad?.trim()"', $vue);
    }
}
