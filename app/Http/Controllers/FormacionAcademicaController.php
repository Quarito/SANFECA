<?php

namespace App\Http\Controllers;

use App\Models\FormacionAcademica;
use App\Models\SolicitudCertificacion;
use App\Models\SeleccionCertificacion;
use App\Models\FormacionAcademicaSolicitud;
use App\Services\ConsultaAntecedentesService;
use App\Models\InstitucionAfiliada;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class FormacionAcademicaController extends Controller
{
    private function solicitudActiva(int $userId, string $certificacion, string $tipo): ?SolicitudCertificacion
    {
        return SolicitudCertificacion::where("user_id", $userId)
            ->where("certificacion", $certificacion)
            ->where("tipo_tramite", $tipo)
            ->where("estado", "borrador")
            ->latest("id")
            ->first();
    }

    private function verificarSolicitud(Request $request, ConsultaAntecedentesService $servicio)
    {
        $userId = $request->user()->id;
        $certificacion = SeleccionCertificacion::where("user_id", $userId)->value("certificacion");
        if (!$certificacion) return null;
        $tipo = $servicio->determinarTipoTramite($userId);
        if (!in_array($tipo, ["inicial", "refrendo"], true)) return null;
        return $this->solicitudActiva($userId, $certificacion, $tipo);
    }

    public function create(Request $request, ConsultaAntecedentesService $servicio)
    {
        $solicitud = $this->verificarSolicitud($request, $servicio);
        if (!$solicitud) return redirect()->route("registro.certificacion")->with("error", "No existe una solicitud válida para continuar.");
        if ($solicitud->tipo_tramite === "refrendo" && !$solicitud->datosRefrendo) return redirect()->route("registro.refrendo")->with("error", "Primero debe completar los datos de refrendo.");
        $formacion = $solicitud->formacionAcademica;

        return view('registro.formacion-academica', [
            'solicitud' => $solicitud,
            'formacion' => $formacion,
            'instituciones' => InstitucionAfiliada::orderBy(
                'numero_afiliacion'
            )->get(),
        ]);
    }

    
	public function store(Request $request, ConsultaAntecedentesService $servicio)
        {
            $solicitud = $this->verificarSolicitud($request, $servicio);
            if (!$solicitud) return redirect()->route("registro.certificacion")->with("error", "No existe una solicitud válida para continuar.");
            if ($solicitud->tipo_tramite === "refrendo" && !$solicitud->datosRefrendo) return redirect()->route("registro.refrendo")->with("error", "Primero debe completar los datos de refrendo.");
            $formacion = $solicitud->formacionAcademica;

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
	                        'anfeca/documentos/solicitudes/' . $solicitud->id,
	                        'local'
	                    );
	            }
	        }

	        FormacionAcademicaSolicitud::updateOrCreate(
	            ['solicitud_id' => $solicitud->id],
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
	        ->route('registro.documentos')
	        ->with(
	            'success',
	            'Formación académica guardada correctamente.'
	        );
	}

}

