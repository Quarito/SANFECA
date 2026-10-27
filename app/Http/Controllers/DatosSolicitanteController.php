<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\DatosSolicitante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DatosSolicitanteController extends Controller
{
    public function create(Request $request)
{
    $datos = $request->user()
        ? DatosSolicitante::where(
            'user_id',
            $request->user()->id
        )->first()
        : null;

    return view('registro.datos-personales', [
        'datos' => $datos,
    ]);
}

    public function store(Request $request)
    {
        // Convertir CURP y RFC a mayúsculas.
        $request->merge([
            'curp' => strtoupper(trim($request->input('curp', ''))),
            'rfc' => strtoupper(trim($request->input('rfc', ''))),
        ]);

        $datos = $request->validate([
            'curp' => [
                'required', 'string', 'size:18',
                'regex:/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[A-Z0-9][0-9]$/',
                'unique:datos_solicitantes,curp'
            ],
            'rfc' => [
                'required', 'string', 'min:12', 'max:13',
                'unique:datos_solicitantes,rfc'
            ],
            'cedula_profesional' => ['nullable', 'string', 'max:30'],
            'nombres' => ['required', 'string', 'max:150'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'calle' => ['required', 'string', 'max:200'],
            'numero_exterior' => ['required', 'string', 'max:20'],
            'numero_interior' => ['nullable', 'string', 'max:20'],
            'codigo_postal' => ['required', 'digits:5'],
            'entidad_federativa' => ['required', 'string', 'max:100'],
            'colonia' => ['required', 'string', 'max:150'],
            'municipio' => ['required', 'string', 'max:150'],
            'correo_electronico' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')
            ],
            'telefono_particular' => ['nullable', 'string', 'max:20'],
            'telefono_celular' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($datos) {
            $user = User::create([
                'name' => trim(
                    $datos['nombres'] . ' ' .
                    $datos['apellido_paterno'] . ' ' .
                    ($datos['apellido_materno'] ?? '')
                ),
                'email' => $datos['correo_electronico'],
                'password' => Hash::make($datos['password']),
            ]);

            unset($datos['password']);

            $datos['user_id'] = $user->id;

            DatosSolicitante::create($datos);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('registro.certificacion');
    }
}

