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

