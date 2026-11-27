<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarDatosAutorizados
{
    public function handle(Request $request, Closure $next): Response
    {
        $autorizado = app(\App\Services\Solicitantes\EstadoSolicitante::class)->datosAutorizados($request->user()->id);

        if (!$autorizado) {
            return redirect()
                ->route('mis-datos.edit')
                ->with(
                    'error',
                    'Debes revisar y autorizar tus datos personales antes de continuar.'
                );
        }

        return $next($request);
    }
}
