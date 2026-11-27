<?php

namespace App\Http\Controllers\Auth;

use App\Services\Anfeca\Autenticacion\RecuperacionCuentas;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class RecuperacionPasswordController extends Controller
{
    public function mostrarSolicitud()
    {
        return view('auth.recuperar-password');
    }

    public function enviarEnlace(Request $request)
    {
        $datos = $request->validate([
            'curp' => ['required', 'string', 'size:18'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $curp = Str::upper(trim($datos['curp']));
        $email = Str::lower(trim($datos['email']));

        // Solo la cuenta vinculada a ambos datos recibe el enlace.
        app(RecuperacionCuentas::class)->enviar($curp, $email);

	return redirect()->route('password.sent');

    }

    public function mostrarNuevaPassword(Request $request, string $token)
    {
        return view('auth.nueva-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function guardarNuevaPassword(Request $request)
    {
        $datos = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(12),
            ],
        ]);

        return $this->resultado(app(RecuperacionCuentas::class)->restablecer($datos));
    }

    private function resultado(bool $restablecida)
    {
        if (! $restablecida) {
            return back()
                ->withErrors([
                    'email' => 'El enlace es inválido o ha expirado.',
                ]);
        }

        return redirect()
            ->route('login')
            ->with(
                'status',
                'Contraseña actualizada. Ya puedes iniciar sesión.'
            );
    }
}
