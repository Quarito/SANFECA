<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las cuentas de coordinación no utilizan el flujo de solicitantes
 * (no capturan datos personales ni crean solicitudes): se envían a su módulo.
 * Invitados y solicitantes continúan sin cambios.
 */
class SoloSolicitantes
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->esCoordinador()) {
            return redirect()->route('coordinacion.inicio');
        }

        return $next($request);
    }
}
