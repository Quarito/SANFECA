<?php

namespace Tests\Feature;

use App\Models\DatosSolicitante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\TestCase;

/**
 * Correcciones de seguridad P4, P5, P6 y V8 (usuarios ficticios, SQLite en memoria).
 */
class SeguridadAnfecaTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refreshDatabaseLaravel;
    }

    public function refreshDatabase(): void
    {
        if (
            !app()->environment('testing') ||
            config('database.default') !== 'sqlite' ||
            config('database.connections.sqlite.database') !== ':memory:' ||
            DB::connection()->getDriverName() !== 'sqlite'
        ) {
            throw new RuntimeException('SEGURIDAD: estas pruebas requieren SQLite en memoria.');
        }

        $this->refreshDatabaseLaravel();
    }

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login-ip:127.0.0.1');
    }

    private function usuarioConDatos(string $correo = 'seguridad@example.test'): User
    {
        $user = User::create([
            'name' => 'Persona Ficticia',
            'email' => $correo,
            'password' => Hash::make('ContrasenaFicticia1'),
        ]);

        DatosSolicitante::create([
            'user_id' => $user->id,
            'curp' => 'FICT800101HDFRRN01', 'rfc' => 'FICT800101AB1',
            'nombres' => 'Persona', 'apellido_paterno' => 'Ficticia', 'genero' => 'Femenino',
            'calle' => 'Calle', 'numero_exterior' => '1', 'codigo_postal' => '01000',
            'entidad_federativa' => 'Ciudad de México', 'colonia' => 'Centro', 'municipio' => 'Cuauhtémoc',
            'correo_electronico' => $correo, 'telefono_celular' => '5500000000',
        ]);

        return $user;
    }

    public function test_p4_registro_redirige_al_formulario_de_datos_personales(): void
    {
        $this->get('/registro')->assertRedirect('/registro/datos-personales');
    }

    public function test_p5_cuenta_con_datos_no_puede_registrarse_de_nuevo(): void
    {
        $user = $this->usuarioConDatos();

        $this->actingAs($user)->get('/registro/datos-personales')
            ->assertRedirect(route('mis-datos.edit'));

        $this->actingAs($user)->post('/registro/datos-personales', [
            'curp' => 'FICT800101HDFRRN02', 'rfc' => 'FICT800101AB2', 'nombres' => 'Otra',
            'apellido_paterno' => 'Persona', 'genero' => 'Femenino', 'calle' => 'C', 'numero_exterior' => '1',
            'codigo_postal' => '01000', 'entidad_federativa' => 'X', 'colonia' => 'X', 'municipio' => 'X',
            'correo_electronico' => 'otra@example.test', 'telefono_celular' => '5511111111',
        ])->assertRedirect(route('mis-datos.edit'))->assertSessionHas('error');

        $this->assertSame(1, DatosSolicitante::where('user_id', $user->id)->count());
    }

    public function test_p6_recuperacion_exige_12_caracteres(): void
    {
        $user = User::create([
            'name' => 'Persona Ficticia', 'email' => 'recupera@example.test',
            'password' => Hash::make('ContrasenaFicticia1'),
        ]);

        $token = Password::broker()->createToken($user);
        $this->post('/restablecer', [
            'token' => $token, 'email' => 'recupera@example.test',
            'password' => 'Corta12345', 'password_confirmation' => 'Corta12345',
        ])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('ContrasenaFicticia1', $user->fresh()->password));

        $token = Password::broker()->createToken($user);
        $this->post('/restablecer', [
            'token' => $token, 'email' => 'recupera@example.test',
            'password' => 'NuevaContrasena12', 'password_confirmation' => 'NuevaContrasena12',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NuevaContrasena12', $user->fresh()->password));
    }

    public function test_v8_login_conserva_limite_por_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => "x{$i}@example.test", 'password' => 'incorrecta'])
                ->assertStatus(302);
        }

        $this->post('/login', ['email' => 'x9@example.test', 'password' => 'incorrecta'])
            ->assertStatus(429);
    }

    public function test_v8_login_limita_por_cuenta_aunque_cambie_la_ip(): void
    {
        $this->usuarioConDatos('objetivo@example.test');

        // 15 intentos desde 3 IP distintas (5 por IP, dentro del límite por IP).
        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $ip) {
            for ($i = 0; $i < 5; $i++) {
                $this->withServerVariables(['REMOTE_ADDR' => $ip])
                    ->post('/login', ['email' => 'objetivo@example.test', 'password' => 'incorrecta'])
                    ->assertStatus(302);
            }
        }

        // Desde una IP nueva, la cuenta ya está limitada.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.4'])
            ->post('/login', ['email' => 'objetivo@example.test', 'password' => 'ContrasenaFicticia1'])
            ->assertStatus(429);
        $this->assertGuest();
    }

    public function test_login_valido_sigue_funcionando(): void
    {
        $this->usuarioConDatos('valido@example.test');

        $this->post('/login', ['email' => 'valido@example.test', 'password' => 'ContrasenaFicticia1'])
            ->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_volcados_y_capturas_no_estan_versionados(): void
    {
        $gitignore = file_get_contents(base_path('.gitignore'));

        $this->assertStringContainsString('*.dump', $gitignore);
        $this->assertStringContainsString('/respaldos_demo/', $gitignore);
    }
}
