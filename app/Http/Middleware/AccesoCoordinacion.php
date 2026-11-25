<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe /coordinacion/* a cuentas con rol de coordinación (users.rol).
 * Los solicitantes y cualquier otro perfil reciben 403. Requiere 'auth' antes.
 */
class AccesoCoordinacion
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user() !== null && Gate::forUser($request->user())->allows('acceder-coordinacion'),
            403,
            'Acceso restringido a la coordinación de certificación académica.'
        );

        return $next($request);
    }
}
