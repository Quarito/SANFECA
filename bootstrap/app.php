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
        // Debe ejecutarse antes de la autenticación: así identifica la sesión vencida o perdida antes de que auth responda.
        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \App\Http\Middleware\ControlInactividad::class,
        );
})
        ->withExceptions(function (Exceptions $exceptions): void {
        // Respuestas de sesión: el cuerpo y el código originales de Laravel NO se modifican; solo se agrega la cabecera
        // X-Anfeca-Sesion cuando la causa es verificable, para que el aviso distinga cada caso:
        //   419 sin sesión autenticada → «expirada» · 419 con sesión vigente → «token» (la página expiró)
        //   401 de una sesión que existía y ya no está en el servidor → «finalizada» (ver ControlInactividad).
        // La página HTML del 419 es resources/views/errors/419.blade.php. La validación CSRF no se toca.
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $respuesta, \Throwable $e, Request $request) {
            $encabezado = \App\Http\Middleware\ControlInactividad::ENCABEZADO;
            if ($respuesta->getStatusCode() === 419 && !$respuesta->headers->has($encabezado)) {
                $respuesta->headers->set($encabezado, \Illuminate\Support\Facades\Auth::check() ? 'token' : 'expirada');
            } elseif ($e instanceof \Illuminate\Auth\AuthenticationException
                && $request->attributes->get(\App\Http\Middleware\ControlInactividad::SESION_PERDIDA) === true) {
                $respuesta->headers->set($encabezado, 'finalizada');
            }

            return $respuesta;
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
