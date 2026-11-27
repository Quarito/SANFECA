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
        // Control de inactividad (usa la sesión existente; sin tablas ni servicios).
        $middleware->appendToGroup('web', \App\Http\Middleware\ControlInactividad::class);
})
        ->withExceptions(function (Exceptions $exceptions): void {
        // HTTP 419 (token CSRF vencido): respuesta comprensible, sin detalles técnicos. Conserva el código 419;
        // la vista amigable es resources/views/errors/419.blade.php. La validación CSRF no se modifica.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || !$request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Su sesión terminó o la página expiró.',
                'mensaje' => 'Su sesión terminó o la página expiró. Vuelva a cargar la página o inicie sesión nuevamente.',
                'sesion' => 'expirada',
                'redireccion' => route('sesion.finalizada'),
            ], 419)->header(\App\Http\Middleware\ControlInactividad::ENCABEZADO, 'expirada');
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
