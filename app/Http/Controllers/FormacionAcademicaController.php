<?php

namespace App\Http\Controllers;

use App\Services\Pdm\Espejo\EspejoPdm;
use App\Models\FormacionAcademica;
use App\Models\SolicitudCertificacion;
use App\Models\SeleccionCertificacion;
use App\Models\FormacionAcademicaSolicitud;
use App\Services\ConsultaAntecedentesService;
use App\Services\Catalogos\CatalogoLicenciaturas;
use App\Services\Catalogos\CatalogoPosgrados;
use App\Services\Catalogos\TituloOrigen;
use App\Services\Catalogos\TituloUltimoGrado;
use App\Services\Formacion\RequisitosFormacion;
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

        $catalogoPosgrados = app(CatalogoPosgrados::class)->estado();
        // Catálogo de licenciaturas (bandera ANFECA_CATALOGO_LICENCIATURAS) y casilla de posgrado (ANFECA_DECLARACION_POSGRADO).
        $tituloOrigen = app(TituloOrigen::class);
        $catalogoLicenciaturas = app(CatalogoLicenciaturas::class)->estado();
        $catalogoLicenciaturas['aplica'] = $catalogoLicenciaturas['estado'] === 'activo' && $tituloOrigen->usaCatalogo($solicitud, $formacion);

        return view('registro.formacion-academica', [
            'catalogoPosgrados' => $catalogoPosgrados,
            'catalogoLicenciaturas' => $catalogoLicenciaturas,
            'declaracionPosgrado' => RequisitosFormacion::declaracionActiva($formacion),
            'posgradoObligatorio' => RequisitosFormacion::exigePosgrado($solicitud->area_certificacion),
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

            // Casilla «Cuento con estudios de posgrado concluidos»: escenario 1 (solo licenciatura) o 2 (con posgrado).
            $declaracion = RequisitosFormacion::declaracionActiva($formacion);
            // Si el formulario no envía la casilla (p. ej., interfaz anterior tras una reversión de código), rige la
            // declaración ya guardada: nunca se reinterpreta ni se pierde.
            $declarada = RequisitosFormacion::declaracion($formacion);
            $valorDeclaracion = $request->input('cuenta_con_posgrado', $declarada === null ? null : ($declarada ? '1' : '0'));
            // Normalizado a texto (un 0 numérico no debe tomarse como escenario 2; un arreglo lo rechaza la validación).
            $valorDeclaracion = is_scalar($valorDeclaracion) ? (string) $valorDeclaracion : null;
            $sinPosgrado = $declaracion && $valorDeclaracion === '0';

	    // Documentos académicos aplicables (centralizado): en el escenario 1 solo los de licenciatura; nunca se
	    // piden de nuevo ni se copian los mismos archivos como «último grado».
		$documentos = $sinPosgrado
		    ? RequisitosFormacion::DOCUMENTOS_ORIGEN
		    : [...RequisitosFormacion::DOCUMENTOS_ORIGEN, ...RequisitosFormacion::DOCUMENTOS_ULTIMO];

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

            // Catálogo de posgrados (bandera ANFECA_CATALOGO_POSGRADOS): reglas adicionales del último grado.
            $tituloUltimoGrado = app(TituloUltimoGrado::class);
            $tituloOrigen = app(TituloOrigen::class);
            if ($declaracion) {
                // Escenario 1 o 2 (y áreas que exigen posgrado): reglas centralizadas en RequisitosFormacion.
                $reglas = array_merge($reglas, RequisitosFormacion::reglasDeclaracion($sinPosgrado, $declarada, $solicitud->area_certificacion));
            }
            if (!$sinPosgrado) {
                $reglas = array_merge($reglas, $tituloUltimoGrado->reglas($request));
            }
            $reglas = array_merge($reglas, $tituloOrigen->reglas($solicitud, $formacion));
            $datos = $request->validate($reglas, array_merge(
                $tituloUltimoGrado->mensajes(),
                $tituloOrigen->mensajes(),
                RequisitosFormacion::mensajesDeclaracion($solicitud->area_certificacion)
            ));

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

            if ($sinPosgrado) {
                // Escenario 1: el último grado es la licenciatura (derivado). No se escriben ni borran columnas de
                // posgrado: si existían de un guardado anterior se CONSERVAN, ya no vigentes (cuenta_con_posgrado = false).
                foreach (['nivel_ultimo_grado', 'titulo_ultimo_grado', 'titulo_ultimo_grado_otro'] as $campo) {
                    unset($datos[$campo]);
                }
            } else {
                // El título personalizado del último grado
                // solo se conserva cuando se selecciona Otro.
                if (($datos['titulo_ultimo_grado'] ?? null) !== 'Otro') {
                    $datos['titulo_ultimo_grado_otro'] = null;
                }

                // Título específico del último grado: desde el catálogo del servidor o captura manual; con la
                // bandera desactivada protege los títulos ya registrados (sin pérdida silenciosa).
                $datos = $tituloUltimoGrado->aplicar($request, $datos, $formacion);
            }
            if ($declaracion) {
                $datos['cuenta_con_posgrado'] = !$sinPosgrado;
            }

            // Nombre exacto de la licenciatura (catálogo de licenciaturas o captura manual; área derivada).
            $datos = $tituloOrigen->aplicar($datos, $solicitud, $formacion);

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
            if ($e instanceof \Illuminate\Database\QueryException
                && ($controlado = \App\Services\Pdm\ErrorValidacionPdm::desde($e)) !== null) {
                // F4/F5: dato no registrable en el PDM (disparadores anfeca_compat).
                throw $controlado;
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
                    'Formación académica guardada. Para autorizarla, marca la confirmación y selecciona «Autorizar y continuar».'
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

            foreach (RequisitosFormacion::campos($formacion) as $campo) {
                if (blank($formacion->{$campo})) {
                    return redirect()
                        ->route('registro.formacion')
                        ->with(
                            'error',
                            'Completa todos los datos académicos antes de autorizar.'
                        );
                }
            }

            if (RequisitosFormacion::faltaPosgradoObligatorio($formacion, $solicitud->area_certificacion)) {
                // El área de certificación cambió después de guardar «solo licenciatura».
                return redirect()
                    ->route('registro.formacion')
                    ->with('error', RequisitosFormacion::mensajePosgradoObligatorio($solicitud->area_certificacion));
            }

            foreach (RequisitosFormacion::documentos($formacion) as $campo) {
                $ruta = $formacion->{$campo};

                if (
                    blank($ruta)
                    || !Storage::disk('local')->exists($ruta)
                ) {
                    return redirect()
                        ->route('registro.formacion')
                        ->with(
                            'error',
                            RequisitosFormacion::soloLicenciatura($formacion)
                                ? 'Debes contar con el título y la cédula de licenciatura antes de autorizar.'
                                : 'Debes contar con los cuatro documentos académicos antes de autorizar.'
                        );
                }
            }

            $formacion->formacion_completada_at = now();
            $formacion->save();

            // F3: espejo del evento «Formación académica autorizada».
            app(EspejoPdm::class)->solicitud($solicitud->id);

            // «Autorizar y continuar»: tras confirmar el servidor la autorización,
            // se avanza a Documentos probatorios.
            return redirect()
                ->route('registro.documentos')
                ->with('success', 'Formación académica autorizada.');
        });
    }

}

