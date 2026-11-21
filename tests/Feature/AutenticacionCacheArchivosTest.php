<?php

namespace Tests\Feature;

use App\Models\DatosSolicitante;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Regresión del HTTP 500 en el inicio de sesión (2026-11-21):
 * con 'permission' => 0664 en la caché de archivos, FileStore aplicaba ese modo
 * también a los subdirectorios que crea (p. ej. d9/a0), sin bit de ejecución;
 * el RateLimiter (throttle:login) fallaba con fopen()/fileperms().
 *
 * Usa la configuración REAL del almacén «file», pero en un directorio temporal
 * propio (nunca storage/framework/cache del proyecto). SQLite en memoria.
 */
class AutenticacionCacheArchivosTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refreshDatabaseLaravel;
    }

    private string $directorio;

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

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutVite();

        $this->directorio = sys_get_temp_dir().'/anfeca_cache_prueba_'.bin2hex(random_bytes(6));
        mkdir($this->directorio, 0770, true);

        // Almacén de archivos con la configuración vigente del proyecto, en ruta temporal.
        config(['cache.stores.file_prueba' => array_merge(
            config('cache.stores.file'),
            ['path' => $this->directorio.'/data', 'lock_path' => $this->directorio.'/locks']
        )]);

        // El limitador del login (throttle:login) usa ese almacén de archivos.
        $anterior = $this->app->make(RateLimiter::class);
        $limitador = new RateLimiter(Cache::store('file_prueba'));
        foreach (['login', 'recuperacion', 'restablecer'] as $nombre) {
            if ($definicion = $anterior->limiter($nombre)) {
                $limitador->for($nombre, $definicion);
            }
        }
        $this->app->instance(RateLimiter::class, $limitador);
        \Illuminate\Support\Facades\RateLimiter::clearResolvedInstances();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->directorio);
        parent::tearDown();
    }

    /** @return list<string> directorios del almacén sin permiso de recorrido para su propietario */
    private function directoriosNoRecorribles(): array
    {
        $malos = [];
        $iterador = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directorio, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterador as $ruta => $info) {
            if ($info->isDir() && (fileperms($ruta) & 0100) === 0) {
                $malos[] = substr($ruta, strlen($this->directorio));
            }
        }

        return $malos;
    }

    public function test_la_configuracion_de_cache_no_aplica_modo_de_archivo_a_directorios(): void
    {
        $this->assertArrayNotHasKey('permission', config('cache.stores.file'),
            "FileStore aplica 'permission' también a los subdirectorios; con 0664 quedan sin bit de ejecución.");
    }

    public function test_el_limitador_escribe_y_lee_en_la_cache_de_archivos(): void
    {
        $limitador = $this->app->make(RateLimiter::class);

        foreach (range(1, 3) as $i) {
            $limitador->hit('anfeca-regresion-'.$i);
            $limitador->hit('anfeca-regresion-'.$i);
            $this->assertSame(2, $limitador->attempts('anfeca-regresion-'.$i));
        }

        $this->assertSame([], $this->directoriosNoRecorribles());
        $this->assertNotEmpty(glob($this->directorio.'/data/*/*/*'), 'Se escribieron entradas en el almacén de archivos.');
    }

    public function test_inicio_de_sesion_real_sin_datos_personales_alta_cierre_y_nuevo_ingreso(): void
    {
        $user = User::create([
            'name' => 'Persona Ficticia C',
            'email' => 'ficticia.c@example.test',
            'password' => Hash::make('ContrasenaFicticiaC1'),
        ]);
        $this->assertSame(0, DatosSolicitante::where('user_id', $user->id)->count());

        // Intento fallido: el limitador escribe en caché y responde 302 con error (no 500).
        $this->from('/login')->post('/login', ['email' => 'ficticia.c@example.test', 'password' => 'incorrecta'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();

        // Autenticación real.
        $this->post('/login', ['email' => 'ficticia.c@example.test', 'password' => 'ContrasenaFicticiaC1'])
            ->assertRedirect(route('mis-datos.edit'));
        $this->assertAuthenticatedAs($user);

        // Sin Datos personales: se ofrece el formulario inicial, sin errores.
        $this->get('/mis-datos')->assertRedirect(route('registro.datos'));
        $this->get('/registro/datos-personales')->assertOk();
        $this->get('/registro/certificacion')->assertRedirect(route('mis-datos.edit'));

        // Nueva captura para la MISMA cuenta (sin pedir contraseña nueva).
        $this->post('/registro/datos-personales', [
            'curp' => 'ZZTC000101MDFXXX09', 'rfc' => 'ZZTC000101AB9', 'nombres' => 'Persona',
            'apellido_paterno' => 'Ficticia', 'genero' => 'Femenino', 'calle' => 'Calle Ficticia',
            'numero_exterior' => '10', 'codigo_postal' => '01000', 'entidad_federativa' => 'Ciudad de México',
            'colonia' => 'Centro', 'municipio' => 'Cuauhtémoc', 'correo_electronico' => 'ficticia.c@example.test',
            'telefono_celular' => '5511111111',
        ])->assertSessionHasNoErrors()->assertRedirect(route('registro.certificacion'));
        $this->assertSame(1, DB::table('users')->where('email', 'ficticia.c@example.test')->count(), 'No se duplica la cuenta.');
        $this->assertSame(1, DatosSolicitante::where('user_id', $user->id)->count());

        // Cierre de sesión y nuevo ingreso.
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post('/login', ['email' => 'ficticia.c@example.test', 'password' => 'ContrasenaFicticiaC1'])
            ->assertRedirect(route('mis-datos.edit'));
        $this->get('/mis-datos')->assertOk();

        $this->assertSame([], $this->directoriosNoRecorribles(), 'El limitador del login no deja directorios inaccesibles.');
        $this->assertNotEmpty(glob($this->directorio.'/data/*/*/*'), 'throttle:login escribió en la caché de archivos.');
    }
}
