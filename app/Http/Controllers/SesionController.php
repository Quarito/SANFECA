<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ControlInactividad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Control de sesión: estado (solo lectura), continuar (renovación explícita de una sesión vigente),
 * cierre por inactividad y pantalla de sesión finalizada. El vencimiento lo aplica ControlInactividad.
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

    /** Fin de la cuenta regresiva sin respuesta del usuario (POST con CSRF). */
    public function cerrar(Request $request)
    {
        $autenticado = Auth::check();
        if ($autenticado) {
            Auth::guard('web')->logout();
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('sesion.finalizada')->with('sesion_motivo', $autenticado ? 'inactividad' : 'formulario');
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
