<?php

namespace App\Http\Controllers;

use App\Services\Anfeca\FormacionPdm;
use App\Services\Anfeca\Instituciones;
use App\Services\Anfeca\SolicitudActiva;
use App\Services\Anfeca\SolicitudVista;
use App\Services\Anfeca\Tramites;
use App\Services\Catalogos\CatalogoLicenciaturas;
use App\Services\Catalogos\CatalogoPosgrados;
use App\Services\Catalogos\TituloOrigen;
use App\Services\Catalogos\TituloUltimoGrado;
use App\Services\Formacion\RequisitosFormacion;
use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

/**
 * Formación académica sobre NIVEL_PROFESIONAL, ESTUDIO_PROFESIONAL, PERSONA_ESTUDIO_PROFESIONAL, PERSONA
 * (institución) y SOLICITUD_DOCUMENTO (historial de títulos y cédulas). Reglas de validación sin cambios.
 */
class FormacionAcademicaController extends Controller
{
    public function __construct(
        private readonly SolicitudActiva $activa,
        private readonly FormacionPdm $formaciones,
        private readonly Tramites $tramites,
        private readonly Instituciones $instituciones,
    ) {
    }

    private function verificarSolicitud(Request $request): ?SolicitudVista
    {
        return $this->activa->borrador($request->user());
    }

    // BLOQUEO DEFINITIVO DE FORMACION ACADEMICA
    private function formacionEstaCompletada(?Fluent $formacion): bool
    {
        return $formacion !== null
            && $formacion->formacion_completada_at !== null;
    }



public function create(Request $request)
    {
        $solicitud = $this->verificarSolicitud($request);
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
            'instituciones' => $this->instituciones->lista(),
        ]);
    }

    
	public function store(Request $request)
        {
            $solicitud = $this->verificarSolicitud($request);
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
		            && Storage::disk(config('anfeca_8088.disco'))->exists($ruta);

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
	            'exists:institucion,inst_id_institucion',
	        ],
	    ];

		foreach ($documentos as $campo) {
		    $ruta = $formacion?->{$campo};

		    $archivoExiste = !empty($ruta)
		        && Storage::disk(config('anfeca_8088.disco'))->exists($ruta);

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

	    // Los archivos se registran como SOLICITUD_DOCUMENTO dentro de la misma transacción.
	    $nuevosArchivos = [];
	    foreach ($documentos as $campo) {
	        if ($request->hasFile($campo)) {
	            $nuevosArchivos[$campo] = $request->file($campo);
	        }
	    }
	    $rutasCreadas = [];

	    try {
	        $guardadoBloqueado = DB::transaction(function () use ($solicitud, $datos, $nuevosArchivos, $sinPosgrado, &$rutasCreadas) {
                $solicitudBloqueada = $this->tramites->bloquear($solicitud->id);

                if (!$solicitudBloqueada || $solicitudBloqueada->estado !== 'borrador') {
                    return true;
                }

                if ($this->tramites->tieneEvento($solicitud->id, 'FA')) {
                    return true;
                }

                $antes = Storage::disk(config('anfeca_8088.disco'))->allFiles(config('anfeca_8088.carpeta').'/'.$solicitud->id);
                try {
                    $this->formaciones->guardar($solicitud->persona_id, $solicitud->id, $datos, $nuevosArchivos, $sinPosgrado);
                } finally {
                    $rutasCreadas = array_diff(Storage::disk(config('anfeca_8088.disco'))->allFiles(config('anfeca_8088.carpeta').'/'.$solicitud->id), $antes);
                }

                return false;
            });

            if ($guardadoBloqueado) {
                return redirect()
                    ->route('registro.formacion')
                    ->with(
                        'error',
                        'La formación académica ya fue autorizada y no puede modificarse.'
                    );
            }
	    } catch (\Throwable $e) {
	        // Si falla la operación (la transacción ya se revirtió), se retiran los archivos recién escritos.
	        foreach ($rutasCreadas as $ruta) {
	            Storage::disk(config('anfeca_8088.disco'))->delete($ruta);
	        }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
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
                    'Formación académica guardada. Para autorizarla, marca la confirmación y selecciona «Autorizar y continuar».'
                );
	}


    /*
    |--------------------------------------------------------------------------
    | AUTORIZACIÓN DE FORMACIÓN ACADÉMICA
    |--------------------------------------------------------------------------
    */
    public function autorizar(Request $request)
    {
        $solicitud = $this->verificarSolicitud($request);

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
            $this->tramites->bloquear($solicitud->id);

            $formacion = $this->formaciones->cargar($solicitud->persona_id, $solicitud->id);

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
                    || !Storage::disk(config('anfeca_8088.disco'))->exists($ruta)
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

            // Evento «Formación académica autorizada» (ESTADO_SOLICITUD).
            $this->tramites->registrarEvento($solicitud->id, 'FA');

            // «Autorizar y continuar»: tras confirmar el servidor la autorización,
            // se avanza a Documentos probatorios.
            return redirect()
                ->route('registro.documentos')
                ->with('success', 'Formación académica autorizada.');
        });
    }

}

