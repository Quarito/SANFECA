<?php

namespace App\Http\Controllers;

use App\Models\FormacionAcademica;
use App\Models\InstitucionAfiliada;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class FormacionAcademicaController extends Controller
{
    public function create(Request $request)
    {
        $formacion = FormacionAcademica::where(
            'user_id',
            $request->user()->id
        )->first();

        return view('registro.formacion-academica', [
            'formacion' => $formacion,
            'instituciones' => InstitucionAfiliada::orderBy(
                'numero_afiliacion'
            )->get(),
        ]);
    }

    
	public function store(Request $request)
	{
	    $formacion = FormacionAcademica::where(
	        'user_id',
	        $request->user()->id
	    )->first();

	    $documentos = [
	        'titulo_origen_archivo',
	        'cedula_origen_archivo',
	        'titulo_ultimo_grado_archivo',
	        'cedula_ultimo_grado_archivo',
	    ];

	    $reglas = [
	        'nivel_origen' => [
	            'required',
	            Rule::in(['Licenciatura']),
	        ],
	        'titulo_origen' => [
	            'required', 'string', 'max:200',
	        ],
	        'nivel_ultimo_grado' => [
	            'required',
	            Rule::in([
	                'Licenciatura',
	                'Especialidad',
	                'Maestría',
	                'Doctorado',
	            ]),
	        ],
	        'titulo_ultimo_grado' => [
	            'required', 'string', 'max:200',
	        ],
	        'institucion_afiliada_id' => [
	            'required',
	            'exists:instituciones_afiliadas,id',
	        ],
	    ];

	    foreach ($documentos as $campo) {
	        $reglas[$campo] = [
	            empty($formacion?->{$campo})
	                ? 'required'
	                : 'nullable',
	            'file',
	            'mimes:pdf,jpg,jpeg,png',
	            'max:5120',
	        ];
	    }

	    $datos = $request->validate($reglas);

	    // Las rutas se generan en el servidor,
	    // no se aceptan directamente del formulario.
	    foreach ($documentos as $campo) {
	        unset($datos[$campo]);
	    }

	    $nuevosArchivos = [];

	    try {
	        foreach ($documentos as $campo) {
	            if ($request->hasFile($campo)) {
	                $nuevosArchivos[$campo] = $request
	                    ->file($campo)
	                    ->store(
	                        'anfeca/documentos/' . $request->user()->id,
	                        'local'
	                    );
	            }
	        }

	        FormacionAcademica::updateOrCreate(
	            ['user_id' => $request->user()->id],
	            array_merge($datos, $nuevosArchivos)
	        );
	    } catch (\Throwable $e) {
	        // Si falla la operación, limpiar archivos recién subidos.
	        foreach ($nuevosArchivos as $ruta) {
	            Storage::disk('local')->delete($ruta);
	        }

	        report($e);

	        return back()
	            ->withInput()
	            ->withErrors([
	                'documentos' =>
	                    'Ocurrió un problema al guardar los documentos.',
	            ]);
	    }

	    return redirect()
	        ->route('registro.formacion')
	        ->with(
	            'success',
	            'Formación académica guardada correctamente.'
	        );
	}

}

