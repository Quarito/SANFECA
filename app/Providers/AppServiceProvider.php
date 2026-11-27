<?php

namespace App\Providers;

use App\Models\Cuenta;
use App\Services\Anfeca\Autenticacion\ProveedorCuentas;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Modelo físico = exclusivamente las 36 tablas del SQL oficial. Ningún comando de Laravel puede crear
        // (migrate → tabla migrations), vaciar ni volcar estructuras de la base institucional.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Console\Events\CommandStarting::class, function ($evento) {
            $prohibidos = ['migrate', 'migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'migrate:install',
                'db:wipe', 'schema:dump', 'session:table', 'cache:table', 'queue:table', 'queue:failed-table', 'queue:batches-table'];
            if (in_array($evento->command, $prohibidos, true)) {
                throw new \RuntimeException("«{$evento->command}» está deshabilitado: la base solo admite las 36 tablas del SQL oficial PowerDesigner.");
            }
        });

        Auth::provider('anfeca-cuentas', fn ($app, array $config) => new ProveedorCuentas($app['hash'], $config['model']));

        // V8: además del límite por IP (igual al anterior throttle:N,1),
        // se limita por cuenta para frenar intentos desde varias IP.
        $correo = fn (Request $r) => mb_strtolower(trim((string) $r->input('email')));

        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by('login-ip:'.$r->ip()),
            Limit::perHour(15)->by('login-correo:'.$correo($r)),
        ]);

        RateLimiter::for('recuperacion', fn (Request $r) => [
            Limit::perMinute(3)->by('recuperacion-ip:'.$r->ip()),
            Limit::perHour(5)->by('recuperacion-correo:'.$correo($r)),
        ]);

        // Módulo de coordinación: perfiles con ROL de coordinación y privilegio de consulta (ROL_PRIVILEGIO).
        Gate::define('acceder-coordinacion', fn (Cuenta $u) => $u->esCoordinador() && $u->tienePrivilegio('consultar_solicitudes'));

        Gate::policy(\App\Services\Anfeca\SolicitudVista::class, \App\Policies\SolicitudCertificacionPolicy::class);

        RateLimiter::for('restablecer', fn (Request $r) => [
            Limit::perMinute(5)->by('restablecer-ip:'.$r->ip()),
            Limit::perHour(10)->by('restablecer-correo:'.$correo($r)),
        ]);
    }
}
