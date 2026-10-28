<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\DatosSolicitante;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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

        // Consultar únicamente al usuario vinculado a ambos datos.
        $solicitante = DatosSolicitante::query()
            ->whereRaw('UPPER(curp) = ?', [$curp])
            ->whereRaw('LOWER(correo_electronico) = ?', [$email])
            ->first();

        if ($solicitante) {
            $usuario = User::find($solicitante->user_id);

            if (
                $usuario &&
                Str::lower($usuario->email) === $email
            ) {
                Password::broker()->sendResetLink([
                    'email' => $usuario->email,
                ]);
            }
        }
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
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8),
            ],
        ]);

        $resultado = Password::broker()->reset(
            $request->only(
                'email',
                'password',
                'password_confirmation',
                'token'
            ),
            function (User $usuario, string $password) {
                $usuario->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($resultado !== Password::PASSWORD_RESET) {
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
