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

        $reglas = [
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
        ];

	// Si la identidad está autorizada,
	// validar únicamente domicilio y contacto.
	if ($identidadAutorizada) {
	    $reglas = array_intersect_key(
	        $reglas,
	        array_flip($camposActualizables)
	    );
	}

	// Validar los campos permitidos según el estado del usuario.
	$datos = $request->validate($reglas);

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
    /*
    |--------------------------------------------------------------------------
    | CONSULTAR DATOS DEL USUARIO REGISTRADO
    |--------------------------------------------------------------------------
    */

    public function edit(Request $request)
    {
        $datos = DatosSolicitante::where(
            'user_id',
            $request->user()->id
        )->first();

        if (!$datos) {
            return redirect()
                ->route('panel')
                ->with(
                    'error',
                    'No se encontraron datos personales registrados.'
                );
        }

        return view('solicitante.mis-datos', [
            'datos' => $datos,
        ]);
    }
/*
|--------------------------------------------------------------------------
| ACTUALIZAR DATOS PERSONALES
|--------------------------------------------------------------------------
*/

public function update(Request $request)
{
    $user = $request->user();

    $solicitante = DatosSolicitante::where(
        'user_id',
        $user->id
    )->firstOrFail();

// Identificar si los datos personales ya fueron autorizados.
$identidadAutorizada = (bool) $solicitante->datos_autorizados;

// Campos que pueden actualizarse aun con identidad autorizada.
$camposActualizables = [
    'calle',
    'numero_exterior',
    'numero_interior',
    'colonia',
    'municipio',
    'entidad_federativa',
    'codigo_postal',
    'telefono_celular',
    'telefono_particular',
];

// Cuando la identidad ya está autorizada,
// ignorar cualquier intento de modificar sus campos protegidos.
if ($identidadAutorizada) {
    $request->merge([
        'curp' => $solicitante->curp,
        'rfc' => $solicitante->rfc,
        'cedula_profesional' => $solicitante->cedula_profesional,
        'nombres' => $solicitante->nombres,
        'apellido_paterno' => $solicitante->apellido_paterno,
        'apellido_materno' => $solicitante->apellido_materno,
	'correo_electronico' => $solicitante->correo_electronico,    
]);
}

    // Normalizar información
    $request->merge([
        'curp' => strtoupper(trim($request->input('curp', ''))),
        'rfc' => strtoupper(trim($request->input('rfc', ''))),
        'correo_electronico' => strtolower(
            trim($request->input('correo_electronico', ''))
        ),
    ]);

    $datos = $request->validate([
        'curp' => [
            'required',
            'string',
            'size:18',
            'regex:/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[A-Z0-9][0-9]$/',
            Rule::unique('datos_solicitantes', 'curp')
                ->ignore($solicitante->id),
        ],
        'rfc' => [
            'required',
            'string',
            'min:12',
            'max:13',
            Rule::unique('datos_solicitantes', 'rfc')
                ->ignore($solicitante->id),
        ],
        'cedula_profesional' => [
            'nullable', 'string', 'max:30'
        ],
        'nombres' => [
            'required', 'string', 'max:150'
        ],
        'apellido_paterno' => [
            'required', 'string', 'max:100'
        ],
        'apellido_materno' => [
            'nullable', 'string', 'max:100'
        ],
        'calle' => [
            'required', 'string', 'max:200'
        ],
        'numero_exterior' => [
            'required', 'string', 'max:20'
        ],
        'numero_interior' => [
            'nullable', 'string', 'max:20'
        ],
        'codigo_postal' => [
            'required', 'digits:5'
        ],
        'entidad_federativa' => [
            'required', 'string', 'max:100'
        ],
        'colonia' => [
            'required', 'string', 'max:150'
        ],
        'municipio' => [
            'required', 'string', 'max:150'
        ],
        'correo_electronico' => [
            'required',
            'email',
            'max:255',
            Rule::unique('users', 'email')
                ->ignore($user->id),
            Rule::unique('datos_solicitantes', 'correo_electronico')
                ->ignore($solicitante->id),
        ],
        'telefono_particular' => [
            'nullable', 'string', 'max:20'
        ],
        'telefono_celular' => [
            'nullable', 'string', 'max:20'
        ],
    ]);

    DB::transaction(function () use (
    $user,
    $solicitante,
    $datos,
    $identidadAutorizada,
    $camposActualizables
) {
    if ($identidadAutorizada) {

        // Solo actualizar domicilio y contacto.
        $datosPermitidos = array_intersect_key(
            $datos,
            array_flip($camposActualizables)
        );

        $solicitante->update($datosPermitidos);

        

    } else {

        // Usuario pendiente de autorizar: puede corregir sus datos.
        $solicitante->update($datos);

        $user->update([
            'name' => trim(
                $datos['nombres'] . ' ' .
                $datos['apellido_paterno'] . ' ' .
                ($datos['apellido_materno'] ?? '')
            ),
            'email' => $datos['correo_electronico'],
        ]);
    }
});

    return redirect()
        ->route('mis-datos.edit')
        ->with(
            'success',
            'Datos actualizados correctamente.'
        );
}
/*
|--------------------------------------------------------------------------
| AUTORIZACIÓN DE DATOS PERSONALES
|--------------------------------------------------------------------------
*/

public function autorizar(Request $request)
{
    // Confirmación obligatoria
    $request->validate([
        'confirmacion_datos' => ['required', 'accepted'],
    ], [
        'confirmacion_datos.required' =>
            'Debes confirmar que tus datos son correctos.',
        'confirmacion_datos.accepted' =>
            'Debes aceptar la confirmación de tus datos.',
    ]);

    // Buscar exclusivamente los datos del usuario autenticado
    $datos = DatosSolicitante::where(
        'user_id',
        $request->user()->id
    )->firstOrFail();

    // Registrar autorización
    $datos->datos_autorizados = true;
    $datos->fecha_autorizacion = now();
    $datos->save();

    return redirect()
        ->route('registro.certificacion')
        ->with('success', 'Tus datos han sido autorizados correctamente.');
}
}

