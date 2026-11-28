<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ControlInactividad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Control de sesión: estado (solo lectura), continuar (renovación explícita de una sesión vigente) y pantalla de
 * sesión finalizada. El cierre por inactividad lo aplica únicamente ControlInactividad al cumplirse session.lifetime:
 * el navegador nunca cierra la sesión antes de tiempo.
 */
class SesionController extends Controller
{
    /** Consulta sin renovar: ControlInactividad no registra actividad para esta ruta. */
    public function estado(Request $request): JsonResponse
    {
        return $this->respuesta($request);
    }

    /**
     * Renovación explícita (POST con CSRF). Solo llega aquí una sesión vigente: si ya venció, el middleware
     * la invalidó y respondió 401. La actividad ya quedó registrada por el middleware.
     */
    public function continuar(Request $request): JsonResponse
    {
        return $this->respuesta($request);
    }

    public function finalizada(Request $request)
    {
        // Una cuenta con sesión vigente no tiene nada que hacer aquí.
        if (Auth::check()) {
            return redirect('/');
        }

        return view('sesion.finalizada', ['motivo' => session('sesion_motivo', 'inactividad')]);
    }

    private function respuesta(Request $request): JsonResponse
    {
        return response()->json([
            'vigente' => true,
            'autenticado' => Auth::check(),
            'restante' => ControlInactividad::restante($request),
        ])->header('Cache-Control', 'no-store, private');
    }
}
