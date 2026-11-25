<?php

namespace Tests\Pg;

use App\Models\RevisionDocumental;
use App\Models\User;
use App\Services\Coordinacion\DocumentosAplicables;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Concurrencia REAL en PostgreSQL 18 desechable: procesos PHP independientes, conexiones distintas,
 * transacciones confirmadas (sin RefreshDatabase). Solo bd_anfeca_desechable_pruebas_pg.
 */
class RevisionDocumentalConcurrenciaPgTest extends TestCase
{
    private const PROPUESTA = 'database/migrations_propuestas/2026_11_26_000000_create_revisiones_documentales_table.php';

    private int $solicitud;
    private array $coord = [];
    private array $archivos = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql' || DB::connection()->getDatabaseName() !== 'bd_anfeca_desechable_pruebas_pg') {
            throw new RuntimeException('SEGURIDAD: la concurrencia real solo se prueba en bd_anfeca_desechable_pruebas_pg.');
        }
        Artisan::call('migrate:fresh', ['--force' => true]);
        (require base_path(self::PROPUESTA))->up();

        $zona = DB::table('zonas')->insertGetId(['siglas' => 'ZPG', 'descripcion' => 'Zona ficticia PG']);
        $inst = DB::table('instituciones_afiliadas')->insertGetId(['numero_afiliacion' => 'IPG', 'nombre' => 'Institución ficticia PG', 'zona_id' => $zona]);
        $sol = User::create(['name' => 'Solicitante ficticio PG', 'email' => 'sol.pg@example.test', 'password' => Hash::make('ContrasenaFicticia1')]);
        $this->solicitud = DB::table('solicitudes_certificacion')->insertGetId([
            'user_id' => $sol->id, 'tipo_tramite' => 'inicial', 'certificacion' => 'ANFECA', 'estado' => 'enviado',
            'tipo_personal' => 'Profesoras y profesores de Tiempo Completo', 'area_certificacion' => 'Administración',
            'fecha_envio' => now(), 'compromiso_aceptado' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('formaciones_academicas_solicitud')->insert(['solicitud_id' => $this->solicitud, 'nivel_origen' => 'Licenciatura',
            'titulo_origen' => 'Administración', 'institucion_afiliada_id' => $inst]);
        DB::table('documentos_probatorios_solicitud')->insert(['solicitud_id' => $this->solicitud,
            'comprobante_pago_archivo' => $this->archivo('pago_v1', 'versión 1')]);

        for ($i = 1; $i <= 6; $i++) {
            $u = new User();
            $u->forceFill(['name' => "Coordinación PG {$i}", 'email' => "coord.pg{$i}@anfeca.test",
                'password' => Hash::make('ContrasenaCoordinacion1'), 'rol' => 'coordinador_nacional'])->save();
            $this->coord[$i] = $u->id;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->archivos as $ruta) {
            Storage::disk('local')->delete($ruta);   // solo los archivos ficticios creados por esta prueba
        }
        parent::tearDown();
    }

    private function archivo(string $nombre, string $contenido): string
    {
        $ruta = "anfeca/documentos/solicitudes/{$this->solicitud}/".Str::random(8)."_{$nombre}.pdf";
        if (!isset($this->solicitud)) {
            $ruta = 'anfeca/documentos/solicitudes/pg/'.Str::random(8)."_{$nombre}.pdf";
        }
        Storage::disk('local')->put($ruta, "%PDF-1.4 ficticio PG {$contenido}");
        $this->archivos[] = $ruta;

        return $ruta;
    }

    private function token(string $campo): string
    {
        $s = \App\Models\SolicitudCertificacion::findOrFail($this->solicitud);

        return app(DocumentosAplicables::class)->version($s, $campo)->token();
    }

    /** Lanza procesos simultáneos y devuelve sus resultados. */
    private function simultaneos(array $peticiones): array
    {
        $inicio = microtime(true) + 2.0;
        $entorno = array_merge(getenv(), [
            'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => DB::connection()->getDatabaseName(),
            'DB_HOST' => (string) config('database.connections.pgsql.host'), 'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_USERNAME' => (string) config('database.connections.pgsql.username'), 'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
            'APP_ENV' => 'testing', 'ANFECA_PDM_MODO' => 'apagado',
        ]);
        $procesos = [];
        foreach ($peticiones as $i => $p) {
            $p += ['solicitud' => $this->solicitud, 'inicio' => $inicio + ($p['retraso'] ?? 0), 'modo' => 'revisar'];
            $procesos[$i] = proc_open([PHP_BINARY, base_path('tests/Pg/proceso_revision.php'), base64_encode(json_encode($p))],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos, base_path(), $entorno);
            $procesos[$i] = [$procesos[$i], $tubos];
        }
        $salidas = [];
        foreach ($procesos as $i => [$proceso, $tubos]) {
            $salida = stream_get_contents($tubos[1]);
            $error = stream_get_contents($tubos[2]);
            proc_close($proceso);
            $salidas[$i] = json_decode($salida, true) ?? ['resultado' => 'error:'.$salida.$error];
        }

        return $salidas;
    }

    private function datos(int $i, ?int $vigente, string $resultado = 'no_valido', ?string $motivo = null, ?string $clave = null, ?string $version = null): array
    {
        return ['resultado' => $resultado, 'observaciones' => $resultado === 'validado' ? null : "Observación concurrente del proceso {$i}.",
            'motivo_cambio' => $motivo, 'revision_vigente_id' => $vigente, 'version' => $version ?? $this->token('comprobante_pago_archivo'),
            'clave_idempotencia' => $clave ?? (string) Str::uuid()];
    }

    private function conteo(array $salidas): array
    {
        $c = array_count_values(array_column($salidas, 'resultado'));
        ksort($c);

        return $c;
    }

    public function test_primera_revision_simultanea_de_seis_coordinadores_no_bifurca(): void
    {
        $salidas = $this->simultaneos(array_map(fn ($i) => ['coordinador' => $this->coord[$i], 'campo' => 'comprobante_pago_archivo',
            'datos' => $this->datos($i, null, $i % 2 ? 'validado' : 'no_valido')], range(1, 6)));

        $this->assertSame(['conflicto:concurrencia' => 5, 'creada' => 1], $this->conteo($salidas), json_encode($salidas));
        $this->assertSame(1, RevisionDocumental::query()->where('solicitud_id', $this->solicitud)->count());
        $this->assertSame(1, RevisionDocumental::query()->where('solicitud_id', $this->solicitud)->whereNull('revision_anterior_id')->count());
    }

    public function test_seis_sucesores_simultaneos_de_la_misma_vigente_no_bifurcan(): void
    {
        $r1 = $this->simultaneos([['coordinador' => $this->coord[1], 'campo' => 'comprobante_pago_archivo', 'datos' => $this->datos(1, null, 'validado')]])[0]['id'];
        $salidas = $this->simultaneos(array_map(fn ($i) => ['coordinador' => $this->coord[$i], 'campo' => 'comprobante_pago_archivo',
            'datos' => $this->datos($i, $r1, 'no_valido', "Motivo de cambio del proceso {$i}.")], range(1, 6)));

        $this->assertSame(['conflicto:concurrencia' => 5, 'creada' => 1], $this->conteo($salidas), json_encode($salidas));
        $this->assertSame(1, RevisionDocumental::query()->where('revision_anterior_id', $r1)->count(), 'Un solo sucesor.');
        $this->assertSame(1, DB::table('revisiones_documentales_vigentes')->where('solicitud_id', $this->solicitud)->count());
    }

    public function test_reintentos_simultaneos_con_la_misma_clave_no_duplican(): void
    {
        $clave = (string) Str::uuid();
        $datos = $this->datos(1, null, 'validado', null, $clave);
        $salidas = $this->simultaneos(array_fill(0, 4, ['coordinador' => $this->coord[1], 'campo' => 'comprobante_pago_archivo', 'datos' => $datos]));

        $this->assertSame(['creada' => 1, 'reintento' => 3], $this->conteo($salidas), json_encode($salidas));
        $this->assertCount(1, array_unique(array_column($salidas, 'id')), 'Todos devuelven la misma revisión.');
        $this->assertSame(1, RevisionDocumental::query()->where('clave_idempotencia', $clave)->count());
    }

    public function test_misma_clave_con_contenido_distinto_en_paralelo(): void
    {
        $clave = (string) Str::uuid();
        $salidas = $this->simultaneos([
            ['coordinador' => $this->coord[1], 'campo' => 'comprobante_pago_archivo', 'datos' => $this->datos(1, null, 'validado', null, $clave)],
            ['coordinador' => $this->coord[1], 'campo' => 'comprobante_pago_archivo', 'datos' => $this->datos(1, null, 'no_valido', null, $clave)],
        ]);
        $this->assertSame(1, $this->conteo($salidas)['creada'] ?? 0, json_encode($salidas));
        $this->assertContains($this->conteo($salidas), [['conflicto:idempotencia' => 1, 'creada' => 1], ['conflicto:concurrencia' => 1, 'creada' => 1]]);
        $this->assertSame(1, RevisionDocumental::query()->where('solicitud_id', $this->solicitud)->count());
    }

    public function test_carrera_entre_sustitucion_del_archivo_y_guardado(): void
    {
        $tokenConsultado = $this->token('comprobante_pago_archivo');
        $nueva = $this->archivo('pago_v2', 'versión 2');
        // El «solicitante» bloquea la solicitud y cambia el archivo; la revisión llega 0.3 s después.
        $salidas = $this->simultaneos([
            'carga' => ['modo' => 'carga', 'campo' => 'comprobante_pago_archivo', 'ruta_nueva' => $nueva, 'retener' => 1.5],
            'revision' => ['coordinador' => $this->coord[1], 'campo' => 'comprobante_pago_archivo', 'retraso' => 0.3,
                'datos' => $this->datos(1, null, 'validado', null, null, $tokenConsultado)],
        ]);

        $this->assertSame('cargado', $salidas['carga']['resultado']);
        $this->assertSame('conflicto:version', $salidas['revision']['resultado'], 'No se valida una versión distinta de la consultada.');
        $this->assertGreaterThanOrEqual($salidas['carga']['fin'], $salidas['revision']['fin'], 'La revisión esperó al bloqueo de la solicitud.');
        $this->assertSame(0, RevisionDocumental::query()->count());
    }

    public function test_historial_protegido_frente_a_sesiones_concurrentes(): void
    {
        $r1 = $this->simultaneos([['coordinador' => $this->coord[1], 'campo' => 'comprobante_pago_archivo', 'datos' => $this->datos(1, null, 'validado')]])[0]['id'];
        foreach (["update revisiones_documentales set resultado = 'no_valido' where id = {$r1}", "delete from revisiones_documentales where id = {$r1}",
                  'truncate revisiones_documentales', "delete from solicitudes_certificacion where id = {$this->solicitud}"] as $sql) {
            try {
                DB::statement($sql);
                $this->fail("Debió rechazarse: {$sql}");
            } catch (\Illuminate\Database\QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('validado', DB::table('revisiones_documentales')->where('id', $r1)->value('resultado'));
    }
}
