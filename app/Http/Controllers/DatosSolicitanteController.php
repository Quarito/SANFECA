<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\DatosSolicitante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Rules\NombreConMayusculasYMinusculas;

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
            'nombres' => ['bail', 'required', 'string', 'max:150', new NombreConMayusculasYMinusculas()],
            'apellido_paterno' => ['bail', 'required', 'string', 'max:100', new NombreConMayusculasYMinusculas()],
            'apellido_materno' => ['bail', 'nullable', 'string', 'max:100', new NombreConMayusculasYMinusculas()],
            'genero' => [
                'required',
                'string',
                Rule::in([
                    'Femenino',
                    'Masculino',
                ])
            ],
'password' => ['required', 'string', 'min:12', 'confirmed'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'calle' => ['bail', 'required', 'string', 'max:200', new NombreConMayusculasYMinusculas()],
            'numero_exterior' => ['required', 'string', 'max:20'],
            'numero_interior' => ['nullable', 'string', 'max:20'],
            'codigo_postal' => ['required', 'digits:5'],
            'entidad_federativa' => ['bail', 'required', 'string', 'max:100', new NombreConMayusculasYMinusculas()],
            'colonia' => ['bail', 'required', 'string', 'max:150', new NombreConMayusculasYMinusculas()],
            'municipio' => ['bail', 'required', 'string', 'max:150', new NombreConMayusculasYMinusculas()],
            'correo_electronico' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')
            ],
            'telefono_particular' => [
	    'nullable',
	    'required_without:telefono_celular',
	    'digits:10'
	],
	'telefono_celular' => [
	    'nullable',
	    'required_without:telefono_particular',
	    'digits:10'
	],
	];



	// Validar los campos permitidos según el estado del usuario.
	$datos = $request->validate($reglas, [
    'telefono_particular.required_without' =>
        'Debes proporcionar al menos un teléfono: particular o celular.',

    'telefono_celular.required_without' =>
        'Debes proporcionar al menos un teléfono: particular o celular.',

    'telefono_particular.digits' =>
        'El teléfono particular debe contener exactamente 10 dígitos.',

    'telefono_celular.digits' =>
        'El teléfono celular debe contener exactamente 10 dígitos.',
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
//Fallback: Regla para evitar que un usuario con identidad autorizada no pueda cambiar sus datos personales
//si tuviera un nombre o apellido en mayúsculas y minúsculas: No se le aplicará la regla de validación.
$reglasFormatoNombre = $identidadAutorizada
    ? []
    : [new NombreConMayusculasYMinusculas()];

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
        'genero' => $solicitante->genero,
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
            'bail', 'required', 'string', 'max:150', ...$reglasFormatoNombre
        ],
        'apellido_paterno' => [
            'bail', 'required', 'string', 'max:100', ...$reglasFormatoNombre
        ],
        'apellido_materno' => [
            'bail', 'nullable', 'string', 'max:100', ...$reglasFormatoNombre
        ],
        'genero' => [
            Rule::requiredIf(!$identidadAutorizada),
            'nullable',
            'string',
            Rule::in(['Femenino', 'Masculino']),
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
        'telefono_particular' => ['nullable', 'required_without:telefono_celular', 'digits:10'],
'telefono_celular' => ['nullable', 'required_without:telefono_particular', 'digits:10'],
], [
    'telefono_particular.required_without' =>
        'Debes proporcionar al menos un teléfono: particular o celular.',

    'telefono_celular.required_without' =>
        'Debes proporcionar al menos un teléfono: particular o celular.',

    'telefono_particular.digits' =>
        'El teléfono particular debe contener exactamente 10 dígitos.',

    'telefono_celular.digits' =>
        'El teléfono celular debe contener exactamente 10 dígitos.',
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
    // Buscar los datos del usuario autenticado.
    $datos = DatosSolicitante::where(
        'user_id',
        $request->user()->id
    )->firstOrFail();

    // Evitar modificar una autorización existente.
    if ($datos->datos_autorizados) {
        return redirect()
            ->route('registro.certificacion');
    }

    // Validar la confirmación del usuario.
    $request->validate([
        'confirmacion_datos' => ['required', 'accepted'],
    ], [
        'confirmacion_datos.required' =>
            'Debes confirmar que tus datos son correctos.',
        'confirmacion_datos.accepted' =>
            'Debes aceptar la confirmación de tus datos.',
    ]);

    // Validar que el expediente esté completo.
    $validador = \Illuminate\Support\Facades\Validator::make(
        $datos->getAttributes(),
        [
            'curp' => [
                'required',
                'string',
                'size:18',
                'regex:/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[A-Z0-9][0-9]$/',
            ],
            'rfc' => ['required', 'string', 'min:12', 'max:13'],
            'nombres' => ['bail', 'required', 'string', 'max:150', new NombreConMayusculasYMinusculas()],
            'apellido_paterno' => ['bail', 'required', 'string', 'max:100', new NombreConMayusculasYMinusculas()],
            'calle' => ['bail', 'required', 'string', 'max:200', new NombreConMayusculasYMinusculas()],
            'numero_exterior' => ['required', 'string'],
            'colonia' => ['bail', 'required', 'string', 'max:150', new NombreConMayusculasYMinusculas()],
            'municipio' => ['bail', 'required', 'string', 'max:150', new NombreConMayusculasYMinusculas()],
            'entidad_federativa' => ['bail', 'required', 'string', 'max:100', new NombreConMayusculasYMinusculas()],
            'codigo_postal' => ['required', 'digits:5'],
            'correo_electronico' => ['required', 'email'],
            'telefono_particular' => ['nullable', 'required_without:telefono_celular', 'digits:10'],
            'telefono_celular' => ['nullable', 'required_without:telefono_particular', 'digits:10'],

        ],
        [
            'curp.required' =>
                'Debes registrar tu CURP antes de autorizar tus datos.',
        ]
    );

    if ($validador->fails()) {
        return redirect()
            ->route('mis-datos.edit')
            ->withErrors($validador)
            ->with(
                'error',
                'Revisa el formato y completa los datos personales obligatorios antes de autorizar.'
            );
    }

    // Registrar la autorización.
    $datos->datos_autorizados = true;
    $datos->fecha_autorizacion = now();
    $datos->save();

    return redirect()
        ->route('registro.certificacion')
        ->with(
            'success',
            'Tus datos han sido autorizados correctamente.'
        );
}

}

