<?php

namespace App\Http\Controllers;

use App\Services\Pdm\Espejo\EspejoPdm;
use App\Models\FormacionAcademica;
use App\Models\SolicitudCertificacion;
use App\Models\SeleccionCertificacion;
use App\Models\FormacionAcademicaSolicitud;
use App\Services\ConsultaAntecedentesService;
use App\Models\InstitucionAfiliada;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

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

    
    // BLOQUEO DEFINITIVO DE FORMACION ACADEMICA
    private function formacionEstaCompletada(?FormacionAcademicaSolicitud $formacion): bool
    {
        return $formacion !== null
            && $formacion->formacion_completada_at !== null;
    }



public function create(Request $request, ConsultaAntecedentesService $servicio)
    {
        $solicitud = $this->verificarSolicitud($request, $servicio);
        if (!$solicitud) return redirect()->route("registro.certificacion")->with("error", "No existe una solicitud válida para continuar.");
        if ($solicitud->tipo_tramite === "refrendo" && !$solicitud->datosRefrendo) return redirect()->route("registro.refrendo")->with("error", "Primero debe completar los datos de refrendo.");
        $formacion = $solicitud->formacionAcademica;

        
        $formacionBloqueada = $this->formacionEstaCompletada($formacion);

        return view('registro.formacion-academica', [
            'formacionBloqueada' => $formacionBloqueada,
            'solicitud' => $solicitud,
            'areaCertificacion' => $solicitud->area_certificacion,
            'titulosPermitidos' => config(
                'anfeca_equivalencias.areas.'
                . $solicitud->area_certificacion,
                []
            ),
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

		
            // BLOQUEO DEFINITIVO DE FORMACION ACADEMICA
            if ($this->formacionEstaCompletada($formacion)) {
                if ($request->except(['_token']) !== [] || $request->allFiles() !== []) {
                    return redirect()
                        ->route('registro.formacion')
                        ->with('error', 'La formación académica está finalizada y no puede modificarse.');
                }

                return redirect()->route('registro.documentos');
            }

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
	        'nivel_origen' => [
                    'required',
                    Rule::in(['Licenciatura']),
                ],
                'titulo_origen' => (
                    $esRefrendo && !empty($formacion?->titulo_origen)
                )
                    ? ['prohibited']
                    : [
                        'required',
                        'string',
                        Rule::in(
                            config(
                                'anfeca_equivalencias.areas.'
                                . $solicitud->area_certificacion,
                                []
                            )
                        ),
                    ],

                'titulo_origen_otro' => (
                    $esRefrendo && !empty($formacion?->titulo_origen)
                )
                    ? ['prohibited']
                    : [
                        'required_if:titulo_origen,Otro',
                        'prohibited_unless:titulo_origen,Otro',
                        'nullable',
                        'string',
                        'max:200',
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
    'required',
    'string',
    Rule::in([
        'Administración',
        'Contaduría',
        'Informática',
        'Negocios Internacionales',
        'Otro',
    ]),
],

'titulo_ultimo_grado_otro' => [
    'required_if:titulo_ultimo_grado,Otro',
    'prohibited_unless:titulo_ultimo_grado,Otro',
    'nullable',
    'string',
    'max:200',
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

            if ($esRefrendo && !empty($formacion?->titulo_origen)) {
                // Conservar ambos valores históricos del título.
                unset(
                    $datos['titulo_origen'],
                    $datos['titulo_origen_otro']
                );
            } else {
                // Limpiar el valor personalizado si ya no es Otro.
                if (($datos['titulo_origen'] ?? null) !== 'Otro') {
                    $datos['titulo_origen_otro'] = null;
                }
            }

            // El título personalizado del último grado
            // solo se conserva cuando se selecciona Otro.
            if (($datos['titulo_ultimo_grado'] ?? null) !== 'Otro') {
                $datos['titulo_ultimo_grado_otro'] = null;
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

	        $guardadoBloqueado = DB::transaction(function () use (
                $solicitud, $datos, $nuevosArchivos
            ) {
                $solicitudBloqueada = SolicitudCertificacion::query()
                    ->whereKey($solicitud->id)
                    ->where('user_id', $solicitud->user_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($solicitudBloqueada->estado !== 'borrador') {
                    return true;
                }

                $formacionActual = FormacionAcademicaSolicitud::where(
                    'solicitud_id',
                    $solicitud->id
                )->lockForUpdate()->first();

                if ($this->formacionEstaCompletada($formacionActual)) {
                    return true;
                }

                foreach ([
                    'titulo_ultimo_grado_archivo',
                    'cedula_ultimo_grado_archivo'
                ] as $campo) {
                    $anterior = $formacionActual?->{$campo};

                    if (
                        isset($nuevosArchivos[$campo]) &&
                        !empty($anterior) &&
                        $anterior !== $nuevosArchivos[$campo]
                    ) {
                        DB::table('historial_documentos_academicos')->insert([
                            'solicitud_id' => $solicitud->id,
                            'tipo_documento' => $campo,
                            'ruta_archivo' => $anterior,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                FormacionAcademicaSolicitud::updateOrCreate(
                    ['solicitud_id' => $solicitud->id],
                    array_merge($datos, $nuevosArchivos)
                );

                // F3: espejo de formación, documentos académicos e historial.
                app(EspejoPdm::class)->solicitud($solicitud->id);

                return false;
            });

            if ($guardadoBloqueado) {
                foreach ($nuevosArchivos as $ruta) {
                    Storage::disk('local')->delete($ruta);
                }

                return redirect()
                    ->route('registro.formacion')
                    ->with(
                        'error',
                        'La formación académica ya fue autorizada y no puede modificarse.'
                    );
            }
	    } catch (\Throwable $e) {
	        // Si falla la operación, limpiar archivos recién subidos.
	        foreach ($nuevosArchivos as $ruta) {
	            Storage::disk('local')->delete($ruta);
	        }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                // F3: error controlado del espejo PDM (la transacción ya se revirtió).
                throw $e;
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
                    'Formación académica guardada. Revisa tus datos y autorízalos para continuar.'
                );
	}


    /*
    |--------------------------------------------------------------------------
    | AUTORIZACIÓN DE FORMACIÓN ACADÉMICA
    |--------------------------------------------------------------------------
    */
    public function autorizar(
        Request $request,
        ConsultaAntecedentesService $servicio
    ) {
        $solicitud = $this->verificarSolicitud($request, $servicio);

        if (!$solicitud) {
            return redirect()
                ->route('registro.certificacion')
                ->with('error', 'No existe una solicitud válida.');
        }

        if (
            $solicitud->tipo_tramite === 'refrendo'
            && !$solicitud->datosRefrendo
        ) {
            return redirect()
                ->route('registro.refrendo')
                ->with('error', 'Primero debe completar los datos de refrendo.');
        }

        $request->validate([
            'confirmacion_formacion' => ['required', 'accepted'],
        ], [
            'confirmacion_formacion.required' =>
                'Debes confirmar que tu formación académica es correcta.',
            'confirmacion_formacion.accepted' =>
                'Debes aceptar la confirmación de tu formación académica.',
        ]);

        return DB::transaction(function () use ($solicitud) {
            SolicitudCertificacion::whereKey(
                $solicitud->id
            )->lockForUpdate()->firstOrFail();

            $formacion = FormacionAcademicaSolicitud::where(
                'solicitud_id',
                $solicitud->id
            )->lockForUpdate()->first();

            if (!$formacion) {
                return redirect()
                    ->route('registro.formacion')
                    ->with('error', 'Primero guarda tu formación académica.');
            }

            if ($this->formacionEstaCompletada($formacion)) {
                return redirect()->route('registro.documentos');
            }

            foreach ([
                'nivel_origen',
                'titulo_origen',
                'nivel_ultimo_grado',
                'titulo_ultimo_grado',
                'institucion_afiliada_id',
            ] as $campo) {
                if (blank($formacion->{$campo})) {
                    return redirect()
                        ->route('registro.formacion')
                        ->with(
                            'error',
                            'Completa todos los datos académicos antes de autorizar.'
                        );
                }
            }

            foreach ([
                'titulo_origen_archivo',
                'cedula_origen_archivo',
                'titulo_ultimo_grado_archivo',
                'cedula_ultimo_grado_archivo',
            ] as $campo) {
                $ruta = $formacion->{$campo};

                if (
                    blank($ruta)
                    || !Storage::disk('local')->exists($ruta)
                ) {
                    return redirect()
                        ->route('registro.formacion')
                        ->with(
                            'error',
                            'Debes contar con los cuatro documentos académicos antes de autorizar.'
                        );
                }
            }

            $formacion->formacion_completada_at = now();
            $formacion->save();

            // F3: espejo del evento «Formación académica autorizada».
            app(EspejoPdm::class)->solicitud($solicitud->id);

            return redirect()
                ->route('registro.documentos');
        });
    }

}

