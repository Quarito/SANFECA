<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class LimitarFlujoPdmOficial
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::getDefaultDriver() !== 'pdm') {
            return $next($request);
        }

        abort_unless(app()->environment('local', 'testing'), 503, 'El guard PDM oficial requiere un entorno aislado.');
        abort_unless($request->is('/') || $request->routeIs(
            'login', 'login.submit', 'logout', 'registro.create', 'registro.datos', 'registro.datos.guardar',
            'panel', 'coordinacion.solicitudes', 'coordinacion.api.solicitudes',
            'coordinacion.solicitudes.ver', 'coordinacion.solicitudes.documento',
            'password.request', 'password.email', 'password.sent', 'password.reset', 'password.update', 'mis-datos.edit'
        ), 503, 'Este paso todavía no está habilitado para el modelo PowerDesigner oficial.');

        return app(AuthenticateSession::class)->handle($request, $next);
    }
}
