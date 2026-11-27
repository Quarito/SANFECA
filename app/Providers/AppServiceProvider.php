<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Services\Solicitantes\EstadoSolicitante::class, fn () => config('anfeca_pdm36.activo')
            ? new \App\Services\Solicitantes\EstadoSolicitantePdm36() : new \App\Services\Solicitantes\EstadoSolicitantePublic());
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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

        // Módulo de coordinación: solo perfiles administrativos (users.rol).
        // Sin tipo concreto: el usuario puede ser App\Models\User o App\Auth\UsuarioPdm36 (modelo de 36 tablas).
        Gate::define('acceder-coordinacion', fn ($u) => $u->esCoordinador());
        \Illuminate\Support\Facades\Auth::provider('pdm36', fn ($app, array $c) => new \App\Auth\ProveedorUsuarioPdm36($app['hash'], \App\Auth\UsuarioPdm36::class));

        RateLimiter::for('restablecer', fn (Request $r) => [
            Limit::perMinute(5)->by('restablecer-ip:'.$r->ip()),
            Limit::perHour(10)->by('restablecer-correo:'.$correo($r)),
        ]);
    }
}
