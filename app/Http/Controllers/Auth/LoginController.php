<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    // Mostrar formulario
    public function show()
    {
        return view('auth.login');
    }

    // Procesar inicio de sesión
    public function login(Request $request)
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($datos)) {
            throw ValidationException::withMessages([
                'email' => 'Usuario o contraseña incorrectos.',
            ]);
        }

        $request->session()->regenerate();

        if (Auth::getDefaultDriver() === 'pdm') {
            $guard = Auth::guard('pdm');
            $request->session()->put('password_hash_pdm', $guard->hashPasswordForCookie($guard->user()->getAuthPassword()));

            return redirect()->route('mis-datos.edit');
        }

        // Perfiles administrativos: módulo de coordinación (nunca el flujo de solicitantes).
        if ($request->user()->esCoordinador()) {
            return redirect()->route('coordinacion.inicio');
        }

        // Priorizar cualquier solicitud pendiente del usuario.
        $tieneBorrador = \App\Models\SolicitudCertificacion::query()
            ->where('user_id', $request->user()->id)
            ->where('estado', 'borrador')
            ->exists();

        if ($tieneBorrador) {
            return redirect()->route('mis-datos.edit');
        }

        // Si solamente tiene trámites enviados, mostrar su conclusión.
        $tieneSolicitudEnviada = \App\Models\SolicitudCertificacion::query()
            ->where('user_id', $request->user()->id)
            ->where('estado', 'enviado')
            ->exists();

        if ($tieneSolicitudEnviada) {
            return redirect()->route('registro.completado');
        }

        // Usuario sin solicitudes: conservar el flujo original.
        return redirect()->route('mis-datos.edit');
    }

    // Cerrar sesión
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

