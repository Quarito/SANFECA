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

	    private function verificarSolicitud(
	    Request $request,
	    ConsultaAntecedentesService $servicio
	): ?SolicitudCertificacion {
	    $userId = $request->user()->id;

	    $certificacion = SeleccionCertificacion::where(
	        'user_id',
	        $userId
	    )->value('certificacion');

	    if (!$certificacion) {
	        return null;
	    }

	    $tipo = $servicio->determinarTipoTramite($userId);

	    // Conservar el comportamiento de las solicitudes iniciales.
	    if ($tipo === 'inicial') {
	        return $this->solicitudActiva(
	            $userId,
	            $certificacion,
	            'inicial'
	        );
	    }

	    // Buscar un refrendo en borrador.
	    $solicitud = $this->solicitudActiva(
	        $userId,
	        $certificacion,
	        'refrendo'
	    );

	    if (!$solicitud) {
	        return null;
	    }

	    // Compatibilidad con refrendos anteriores.
	    if ($solicitud->certificado_historico_id === null) {
	        return $tipo === 'refrendo' ? $solicitud : null;
	    }

	    // Validar el certificado histórico asociado.
	    $certificado = \App\Models\CertificadoHistorico::find(
	        $solicitud->certificado_historico_id
	    );

	    if (!$certificado) {
	        return null;
	    }

	    $validado = $servicio->validarCertificadoAnterior(
	        $userId,
	        $certificacion,
	        (string) $certificado->numero_certificado
	    );

	    if (
	        !$validado
	        || (int) $validado->id !== (int) $certificado->id
	    ) {
	        return null;
	    }

	    return $solicitud;
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

		$esRefrendo = $solicitud->tipo_tramite === 'refrendo';

	    // Los cuatro documentos académicos se verifican
		// tanto en certificación inicial como en refrendo.
		$documentos = [
		    'titulo_origen_archivo',
		    'cedula_origen_archivo',
		    'titulo_ultimo_grado_archivo',
		    'cedula_ultimo_grado_archivo',
		];

		// Durante un refrendo, los documentos de origen
		// solo pueden cargarse cuando no existen físicamente.
		if ($esRefrendo) {
		    $documentosOrigen = [
		        'titulo_origen_archivo',
		        'cedula_origen_archivo',
		    ];

		    foreach ($documentosOrigen as $campo) {
		        $ruta = $formacion?->{$campo};

		        $archivoExiste = !empty($ruta)
		            && Storage::disk('local')->exists($ruta);

		        if ($archivoExiste && $request->hasFile($campo)) {
		            return back()
		                ->withInput()
		                ->withErrors([
		                    $campo =>
		                        'Este documento de origen ya está registrado y no puede sustituirse durante un refrendo.',
		                ]);
		        }
		    }
		}

	    $reglas = [
	        'nivel_origen' => $esRefrendo
		    ? ['prohibited']
		    : ['required', Rule::in(['Licenciatura'])],
	        'titulo_origen' => $esRefrendo
		    ? ['prohibited']
		    : ['required', 'string', 'max:200'],

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
		    $ruta = $formacion?->{$campo};

		    $archivoExiste = !empty($ruta)
		        && Storage::disk('local')->exists($ruta);

		    $reglas[$campo] = [
		        $archivoExiste ? 'nullable' : 'required',
		        'file',
		        'mimes:pdf,jpg,jpeg,png',
		        'max:5120',
		    ];
		}

	    $datos = $request->validate($reglas);

		if ($esRefrendo) {
		    unset($datos['nivel_origen'], $datos['titulo_origen']);

		    if ($formacion === null || empty($formacion->titulo_origen)) {
		        return back()->withErrors([
		            'titulo_origen' =>
		                'No existe un título académico de origen registrado para este refrendo.',
		        ]);
		    }
		}

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

