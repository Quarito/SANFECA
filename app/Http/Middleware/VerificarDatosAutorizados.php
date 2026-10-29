<?php

namespace App\Http\Middleware;

use App\Models\DatosSolicitante;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarDatosAutorizados
{
    public function handle(Request $request, Closure $next): Response
    {
        $autorizado = DatosSolicitante::where(
            'user_id',
            $request->user()->id
        )
        ->where('datos_autorizados', true)
        ->exists();

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
