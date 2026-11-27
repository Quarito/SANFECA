<?php

namespace App\Http\Controllers;

use App\Services\Anfeca\ExpedienteSolicitante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Datos personales sobre PERSONA, USUARIO, DOMICILIO y COMUNICACION (36 tablas oficiales).
 * Las longitudes de validación corresponden a las columnas del modelo (p. ej. calle 170, número exterior 6,
 * número interior 4, apellidos 60, correo 150).
 */
class DatosSolicitanteController extends Controller
{
    private const MENSAJES = [
        'telefono_particular.required_without' => 'Debes proporcionar al menos un teléfono: particular o celular.',
        'telefono_celular.required_without' => 'Debes proporcionar al menos un teléfono: particular o celular.',
        'telefono_particular.digits' => 'El teléfono particular debe contener exactamente 10 dígitos.',
        'telefono_celular.digits' => 'El teléfono celular debe contener exactamente 10 dígitos.',
    ];

    public function __construct(private readonly ExpedienteSolicitante $expediente)
    {
    }

    /** Reglas del domicilio y teléfonos (únicos campos editables antes de autorizar). */
    private function reglasDomicilio(): array
    {
        return [
            'calle' => ['required', 'string', 'max:170'],
            'numero_exterior' => ['required', 'string', 'max:6'],
            'numero_interior' => ['nullable', 'string', 'max:4'],
            'codigo_postal' => ['required', 'digits:5'],
            'entidad_federativa' => ['required', 'string', 'max:85'],
            'colonia' => ['required', 'string', 'max:150'],
            'municipio' => ['required', 'string', 'max:150'],
            'telefono_particular' => ['nullable', 'required_without:telefono_celular', 'digits:10'],
            'telefono_celular' => ['nullable', 'required_without:telefono_particular', 'digits:10'],
        ];
    }

    public function create(Request $request)
    {
        // Una cuenta con datos personales no vuelve al alta.
        if ($request->user()) {
            return redirect()->route('mis-datos.edit');
        }

        return view('registro.datos-personales', ['datos' => null]);
    }

    public function store(Request $request)
    {
        if ($request->user()) {
            return redirect()->route('mis-datos.edit')->with('error', 'Ya existe un registro de datos personales para esta cuenta.');
        }

        $request->merge([
            'curp' => strtoupper(trim($request->input('curp', ''))),
            'rfc' => strtoupper(trim($request->input('rfc', ''))),
            'correo_electronico' => mb_strtolower(trim((string) $request->input('correo_electronico', ''))),
        ]);

        $datos = $request->validate([
            'curp' => ['required', 'string', 'size:18', 'regex:/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[A-Z0-9][0-9]$/'],
            'rfc' => ['required', 'string', 'min:12', 'max:13'],
            'cedula_profesional' => ['nullable', 'string', 'max:20'],
            'nombres' => ['required', 'string', 'max:150'],
            'apellido_paterno' => ['required', 'string', 'max:60'],
            'apellido_materno' => ['nullable', 'string', 'max:60'],
            'genero' => ['required', 'string', Rule::in(['Femenino', 'Masculino'])],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'correo_electronico' => ['required', 'email', 'max:150'],
        ] + $this->reglasDomicilio(), self::MENSAJES);

        // Unicidad verificada contra PERSONA, USUARIO y COMUNICACION (el SQL oficial no la impone).
        $errores = [];
        if ($this->expediente->curpRegistrada($datos['curp'])) {
            $errores['curp'] = 'La CURP ya está registrada.';
        }
        if ($this->expediente->rfcRegistrado($datos['rfc'])) {
            $errores['rfc'] = 'El RFC ya está registrado.';
        }
        if ($this->expediente->correoRegistrado($datos['correo_electronico'])) {
            $errores['correo_electronico'] = 'El correo electrónico ya está registrado.';
        }
        if ($errores) {
            return back()->withErrors($errores)->withInput($request->except(['password', 'password_confirmation']));
        }

        $password = $datos['password'];
        unset($datos['password']);
        $cuenta = $this->expediente->registrar($datos, $password);

        Auth::login($cuenta);
        $request->session()->regenerate();

        return redirect()->route('registro.certificacion');
    }

    public function edit(Request $request)
    {
        $datos = $this->expediente->datos($request->user());
        if (!$datos) {
            return redirect()->route('registro.datos');
        }

        return view('solicitante.mis-datos', ['datos' => $datos]);
    }

    /** Antes de autorizar solo se modifican domicilio completo y teléfonos; identidad y correo permanecen bloqueados. */
    public function update(Request $request)
    {
        $cuenta = $request->user();
        if ($cuenta->datosAutorizados()) {
            return redirect()->route('mis-datos.edit')->with('error', 'Los datos personales ya fueron autorizados y no pueden modificarse.');
        }

        $datos = $request->validate($this->reglasDomicilio(), self::MENSAJES);
        $this->expediente->actualizarDomicilioYTelefonos($cuenta, $datos);

        return redirect()->route('mis-datos.edit')->with('success', 'Datos actualizados correctamente.');
    }

    public function autorizar(Request $request)
    {
        $cuenta = $request->user();
        if ($cuenta->datosAutorizados()) {
            return redirect()->route('registro.certificacion');
        }

        $request->validate([
            'confirmacion_datos' => ['required', 'accepted'],
        ], [
            'confirmacion_datos.required' => 'Debes confirmar que tus datos son correctos.',
            'confirmacion_datos.accepted' => 'Debes aceptar la confirmación de tus datos.',
        ]);

        $datos = $this->expediente->datos($cuenta);
        $validador = Validator::make($datos?->toArray() ?? [], [
            'curp' => ['required', 'string', 'size:18', 'regex:/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[A-Z0-9][0-9]$/'],
            'rfc' => ['required', 'string', 'min:12', 'max:13'],
            'nombres' => ['required', 'string'],
            'apellido_paterno' => ['required', 'string'],
            'calle' => ['required', 'string'],
            'numero_exterior' => ['required', 'string'],
            'colonia' => ['required', 'string'],
            'municipio' => ['required', 'string'],
            'entidad_federativa' => ['required', 'string'],
            'codigo_postal' => ['required', 'digits:5'],
            'correo_electronico' => ['required', 'email'],
            'telefono_particular' => ['nullable', 'required_without:telefono_celular', 'digits:10'],
            'telefono_celular' => ['nullable', 'required_without:telefono_particular', 'digits:10'],
        ], ['curp.required' => 'Debes registrar tu CURP antes de autorizar tus datos.']);

        if ($validador->fails()) {
            return redirect()->route('mis-datos.edit')->withErrors($validador)
                ->with('error', 'Completa los datos personales obligatorios antes de autorizar.');
        }

        $this->expediente->autorizar($cuenta);

        return redirect()->route('registro.certificacion');
    }
}
