<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
	 $middleware->alias([
        'datos.autorizados' => \App\Http\Middleware\VerificarDatosAutorizados::class,
        // Módulo de coordinación: separación estricta de perfiles.
        'coordinacion' => \App\Http\Middleware\AccesoCoordinacion::class,
        'solo.solicitantes' => \App\Http\Middleware\SoloSolicitantes::class,
    ]);
})
        ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
