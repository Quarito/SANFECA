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
        // F4/F5: en modo principal/exclusivo la petición opera sobre las vistas anfeca_compat.
        $middleware->append(\App\Http\Middleware\AplicarModoPdm::class);
})
        ->withExceptions(function (Exceptions $exceptions): void {
        // F4/F5: los disparadores del PDM informan datos no registrables como
        // 'ANFECA_VALIDACION|campo|mensaje' → error de validación en el campo (la transacción ya se revirtió).
        $exceptions->map(\Illuminate\Database\QueryException::class,
            fn (\Illuminate\Database\QueryException $e) => \App\Services\Pdm\ErrorValidacionPdm::desde($e) ?? $e);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
