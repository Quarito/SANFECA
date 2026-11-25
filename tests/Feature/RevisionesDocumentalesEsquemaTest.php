<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Fase 6.3.1: restricciones de la migración AUTORIZADA de revisiones_documentales.
 *
 * Aplica la migración solo sobre bases desechables:
 * - SQLite en memoria (phpunit.xml);
 * - PostgreSQL bd_anfeca_desechable_pruebas_pg (phpunit.revision-pg.xml, pod sin red).
 * Nunca la aplica sobre la base del ambiente 8086.
 */
class RevisionesDocumentalesEsquemaTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refreshDatabaseLaravel;
    }

    private const MIGRACION = 'database/migrations/2026_11_26_000000_create_revisiones_documentales_table.php';
    private const SHA = 'a3f1c2d4e5b6978877665544332211ffeeddccbbaa99887766554433221100aa';

    private object $migracion;
    private int $solicitud;
    private int $borrador;
    private User $nacional;
    private User $solicitante;

    public function refreshDatabase(): void
    {
        $motor = config('database.default');
        $base = config("database.connections.{$motor}.database");
        $permitido = ($motor === 'sqlite' && $base === ':memory:')
            || ($motor === 'pgsql' && $base === 'bd_anfeca_desechable_pruebas_pg');
        if (!$permitido) {
            throw new RuntimeException("SEGURIDAD: las restricciones de revisiones_documentales solo se prueban en bases desechables ({$motor}:{$base}).");
        }
        $this->refreshDatabaseLaravel();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // La tabla ya existe: la crean las migraciones ordinarias (RefreshDatabase). Se conserva la
        // instancia para probar down()/up() de forma aislada.
        $this->migracion = require base_path(self::MIGRACION);

        $this->solicitante = User::create(['name' => 'Solicitante ficticio', 'email' => 'sol@example.test', 'password' => Hash::make('ContrasenaFicticia1')]);
        $this->nacional = new User();
        $this->nacional->forceFill(['name' => 'Coordinación ficticia', 'email' => 'coord@anfeca.test',
            'password' => Hash::make('ContrasenaFicticia1'), 'rol' => 'coordinador_nacional'])->save();

        $base = ['user_id' => $this->solicitante->id, 'tipo_tramite' => 'inicial', 'certificacion' => 'ANFECA',
            'tipo_personal' => 'Profesoras y profesores de Tiempo Completo', 'area_certificacion' => 'Administración',
            'created_at' => now(), 'updated_at' => now()];
        $this->solicitud = DB::table('solicitudes_certificacion')->insertGetId($base + ['estado' => 'enviado', 'fecha_envio' => now(), 'compromiso_aceptado' => true]);
        $this->borrador = DB::table('solicitudes_certificacion')->insertGetId($base + ['estado' => 'borrador']);
    }

    private function fila(array $cambios = []): array
    {
        return array_merge([
            'solicitud_id' => $this->solicitud,
            'documento_clave' => 'comprobante_pago_archivo',
            'archivo_estado' => 'cargado',
            'version_ruta' => "anfeca/documentos/solicitudes/{$this->solicitud}/AbCdEf123.pdf",
            'version_sha256' => self::SHA,
            'version_bytes' => 18342,
            'resultado' => 'validado',
            'observaciones' => null,
            'motivo_cambio' => null,
            'revision_anterior_id' => null,
            'coordinador_id' => $this->nacional->id,
            'coordinador_nombre' => $this->nacional->name,
            'coordinador_rol' => 'coordinador_nacional',
            'solicitud_estado' => 'enviado',
            'clave_idempotencia' => (string) Str::uuid(),
        ], $cambios);
    }

    private function insertar(array $cambios = []): int
    {
        return DB::table('revisiones_documentales')->insertGetId($this->fila($cambios));
    }

    private function rechaza(array $cambios, string $caso): void
    {
        try {
            DB::transaction(fn () => $this->insertar($cambios));
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail("Debió rechazarse: {$caso}");
    }

    // -------------------------------------------------------------- ubicación segura

    public function test_migracion_autorizada_en_la_ruta_ordinaria_y_aplicable_por_path(): void
    {
        $archivos = array_keys(app(Migrator::class)->getMigrationFiles([database_path('migrations')]));
        $this->assertContains('2026_11_26_000000_create_revisiones_documentales_table', $archivos);
        $this->assertSame('2026_11_26_000000_create_revisiones_documentales_table', end($archivos), 'Es la última migración: --path la aplica sola.');
        $this->assertDirectoryDoesNotExist(base_path('database/migrations_propuestas'));
        $this->assertTrue(DB::table('migrations')->where('migration', '2026_11_26_000000_create_revisiones_documentales_table')->exists());
        $this->assertSame(1, collect($archivos)->filter(fn ($m) => str_contains($m, 'revisiones_documentales'))->count());
    }

    // ------------------------------------------------------------ valores permitidos

    public function test_inserciones_validas_por_estado_de_archivo(): void
    {
        $this->insertar();
        $this->insertar(['documento_clave' => 'titulo_origen_archivo', 'archivo_estado' => 'no_cargado', 'version_ruta' => null,
            'version_sha256' => null, 'version_bytes' => null, 'resultado' => 'no_valido', 'observaciones' => 'El documento no fue cargado por el solicitante.']);
        $this->insertar(['documento_clave' => 'cedula_origen_archivo', 'archivo_estado' => 'no_localizado', 'version_sha256' => null,
            'version_bytes' => null, 'resultado' => 'con_observaciones', 'observaciones' => 'Registrado, pero el archivo no se localiza.']);
        $this->assertSame(3, DB::table('revisiones_documentales')->count());
        $this->assertSame(3, DB::table('revisiones_documentales_vigentes')->count());
        $this->assertNotNull(DB::table('revisiones_documentales')->value('revisado_en'));
    }

    public function test_resultados_y_claves_no_permitidos(): void
    {
        $obs = ['observaciones' => 'Observación suficientemente larga.'];
        $this->rechaza(['resultado' => 'no_aplica'] + $obs, '«No aplica» se calcula, no se guarda');
        $this->rechaza(['resultado' => 'por_revisar'] + $obs, '«Por revisar» es ausencia de revisión');
        $this->rechaza(['resultado' => 'VALIDADO'], 'mayúsculas');
        $this->rechaza(['documento_clave' => 'curp_archivo'], 'documento inexistente');
        $this->rechaza(['coordinador_rol' => 'solicitante'], 'rol no coordinador');
        $this->rechaza(['solicitud_estado' => 'borrador'], 'copia de estado distinta de enviado');
        $this->rechaza(['coordinador_nombre' => '   '], 'nombre vacío');
        $this->assertSame(0, DB::table('revisiones_documentales')->count());
    }

    public function test_documentos_sin_ruta_o_sin_huella(): void
    {
        $sinArchivo = ['archivo_estado' => 'no_cargado', 'version_ruta' => null, 'version_sha256' => null, 'version_bytes' => null];
        $this->rechaza($sinArchivo, 'validar un documento no cargado');
        $this->rechaza(['archivo_estado' => 'no_localizado', 'version_sha256' => null, 'version_bytes' => null], 'validar un archivo no localizado');
        $this->rechaza(['version_sha256' => null], 'cargado sin huella');
        $this->rechaza(['version_ruta' => null], 'cargado sin ruta');
        $this->rechaza(['archivo_estado' => 'no_cargado', 'resultado' => 'no_valido', 'observaciones' => 'Observación suficiente.'], 'no cargado con ruta y huella');
        $this->rechaza(['version_sha256' => 'abc'], 'huella con formato inválido');
        $this->rechaza(['version_sha256' => strtoupper(self::SHA)], 'huella en mayúsculas');
        $this->rechaza(['version_ruta' => '/etc/passwd'], 'ruta fuera de anfeca/');
        $this->rechaza(['version_ruta' => 'anfeca/../.env'], 'ruta con ..');
        $this->rechaza(['version_bytes' => -1], 'tamaño negativo');
        $this->assertSame(0, DB::table('revisiones_documentales')->count());
    }

    public function test_observaciones_obligatorias_en_resultados_desfavorables(): void
    {
        foreach (['con_observaciones', 'no_valido'] as $r) {
            $this->rechaza(['resultado' => $r], "{$r} sin observación");
            $this->rechaza(['resultado' => $r, 'observaciones' => '   corta  '], "{$r} con observación menor de 10 caracteres");
        }
        $this->rechaza(['resultado' => 'no_valido', 'observaciones' => str_repeat('x', 1001)], 'observación mayor de 1000');
        $this->insertar(['resultado' => 'no_valido', 'observaciones' => 'El comprobante no corresponde al periodo.']);
        $this->assertSame(1, DB::table('revisiones_documentales')->count());
    }

    // ----------------------------------------------------------- reglas entre filas

    public function test_solo_solicitudes_enviadas_y_rol_real_del_usuario(): void
    {
        $this->rechaza(['solicitud_id' => $this->borrador, 'version_ruta' => "anfeca/documentos/solicitudes/{$this->borrador}/x.pdf"], 'solicitud en borrador');
        $this->rechaza(['coordinador_id' => $this->solicitante->id, 'coordinador_nombre' => 'Suplantación'], 'usuario solicitante con rol declarado de coordinación');
        $this->rechaza(['solicitud_id' => 999999], 'solicitud inexistente');
        $this->rechaza(['coordinador_id' => 999999], 'usuario inexistente');
        $this->assertSame(0, DB::table('revisiones_documentales')->count());
    }

    public function test_cadena_lineal_cambio_de_resultado_y_vigente(): void
    {
        $r1 = $this->insertar(['resultado' => 'con_observaciones', 'observaciones' => 'La imagen del comprobante es ilegible.']);
        $this->rechaza(['revision_anterior_id' => $r1], 'cambio de resultado sin motivo');
        $this->rechaza(['revision_anterior_id' => $r1, 'motivo_cambio' => 'corto'], 'motivo menor de 10 caracteres');
        $this->rechaza(['motivo_cambio' => 'Motivo sin revisión anterior.'], 'motivo sin revisión anterior');
        $r2 = $this->insertar(['revision_anterior_id' => $r1, 'motivo_cambio' => 'Se verificó el original con la institución.']);

        // Sin bifurcaciones: una sola primera revisión por documento y un solo sucesor por revisión.
        $this->rechaza([], 'segunda primera revisión del mismo documento');
        $this->rechaza(['revision_anterior_id' => $r1, 'motivo_cambio' => 'Intento concurrente sobre la misma base.'], 'segundo sucesor de r1 (concurrencia)');
        // La anterior debe ser del mismo documento y de la misma solicitud.
        $this->rechaza(['documento_clave' => 'titulo_origen_archivo', 'revision_anterior_id' => $r2], 'anterior de otro documento');

        // Mismo resultado sobre una versión nueva: el motivo es opcional.
        $r3 = $this->insertar(['revision_anterior_id' => $r2, 'version_sha256' => str_repeat('b', 64)]);
        $vigentes = DB::table('revisiones_documentales_vigentes')->pluck('id')->all();
        $this->assertSame([$r3], $vigentes);
        $this->assertSame(3, DB::table('revisiones_documentales')->count());
    }

    public function test_la_fecha_la_fija_la_base_de_datos(): void
    {
        $id = $this->insertar(['revisado_en' => '2000-01-01 00:00:00']);
        $fecha = \Illuminate\Support\Carbon::parse(DB::table('revisiones_documentales')->where('id', $id)->value('revisado_en'));
        if (DB::getDriverName() === 'pgsql') {
            // PostgreSQL (normativo): el disparador ignora la fecha enviada por el cliente.
            $this->assertTrue($fecha->greaterThan(now()->subHour()));
        } else {
            $this->markTestSkipped('En SQLite la fecha del cliente no se reemplaza; la regla es exclusiva de PostgreSQL.');
        }
    }

    public function test_reintentos_con_la_misma_clave_no_duplican(): void
    {
        $clave = (string) Str::uuid();
        $this->insertar(['clave_idempotencia' => $clave]);
        $this->rechaza(['clave_idempotencia' => $clave, 'documento_clave' => 'titulo_origen_archivo'], 'reintento con la misma clave');
        $this->assertSame(1, DB::table('revisiones_documentales')->count());
    }

    // --------------------------------------------------------- protección del historial

    public function test_historial_inmutable_sin_update_delete_ni_truncate(): void
    {
        $id = $this->insertar();
        $antes = (array) DB::table('revisiones_documentales')->find($id);

        foreach ([
            'UPDATE' => fn () => DB::table('revisiones_documentales')->where('id', $id)->update(['resultado' => 'no_valido', 'observaciones' => 'Intento de alterar el historial.']),
            'DELETE' => fn () => DB::table('revisiones_documentales')->where('id', $id)->delete(),
            'DELETE masivo' => fn () => DB::table('revisiones_documentales')->delete(),
        ] as $op => $accion) {
            try {
                DB::transaction($accion);
                $this->fail("{$op} debió rechazarse");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        if (DB::getDriverName() === 'pgsql') {
            try {
                DB::transaction(fn () => DB::statement('TRUNCATE public.revisiones_documentales'));
                $this->fail('TRUNCATE debió rechazarse');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
            // PUBLIC no conserva privilegios; el rol propietario queda protegido por los disparadores.
            $publico = DB::selectOne("select count(*) n from information_schema.role_table_grants
                where table_name = 'revisiones_documentales' and grantee = 'PUBLIC'");
            $this->assertSame(0, (int) $publico->n);
        }
        $this->assertSame($antes, (array) DB::table('revisiones_documentales')->find($id));
    }

    public function test_sin_eliminacion_en_cascada_de_solicitudes_ni_usuarios(): void
    {
        $this->insertar();
        foreach ([
            'solicitud' => fn () => DB::table('solicitudes_certificacion')->where('id', $this->solicitud)->delete(),
            'coordinador' => fn () => DB::table('users')->where('id', $this->nacional->id)->delete(),
        ] as $caso => $accion) {
            try {
                DB::transaction($accion);
                $this->fail("Eliminar {$caso} con revisiones debió rechazarse");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(1, DB::table('revisiones_documentales')->count());
    }

    public function test_reversion_se_niega_con_historial_y_procede_vacia(): void
    {
        $this->insertar();
        try {
            $this->migracion->down();
            $this->fail('down() debió negarse con historial');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('historial', $e->getMessage());
        }
        $this->assertSame(1, DB::table('revisiones_documentales')->count());
    }

    public function test_reversion_de_tabla_vacia(): void
    {
        $this->migracion->down();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('revisiones_documentales'));
        $this->migracion->up();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('revisiones_documentales'));
    }

    // ------------------------------------------------------- integridad de la solicitud

    public function test_revisar_no_modifica_la_solicitud_original(): void
    {
        $antes = (array) DB::table('solicitudes_certificacion')->find($this->solicitud);
        $r1 = $this->insertar(['resultado' => 'no_valido', 'observaciones' => 'El documento no es legible en ninguna página.']);
        $this->insertar(['revision_anterior_id' => $r1, 'motivo_cambio' => 'Se recibió aclaración de la institución.']);
        $this->assertSame($antes, (array) DB::table('solicitudes_certificacion')->find($this->solicitud));
    }
}
