<?php

namespace App\Http\Controllers;

use App\Models\SeleccionCertificacion;
use App\Models\SolicitudCertificacion;
use App\Services\ConsultaAntecedentesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\CertificadoHistorico;
use App\Models\DatosSolicitante;


class SeleccionCertificacionController extends Controller
{
    public function create(
    Request $request,
    ConsultaAntecedentesService $consultaAntecedentes
) {
    $seleccion = SeleccionCertificacion::where(
        'user_id',
        $request->user()->id
    )->first();

    $resultadoAntecedentes = $consultaAntecedentes->consultar(
        $request->user()->id
    );

    $resultadoAntecedentes['certificacion_consultada'] =
        $seleccion?->certificacion;

    return view('registro.certificacion', [
        'seleccion' => $seleccion,
        'resultadoAntecedentes' => $resultadoAntecedentes,
    ]);
}

public function consultarCertificadoAnterior(Request $request)
{
    $datos = $request->validate([
        'numero_certificado' => [
            'required',
            'string',
            'max:100',
        ],
    ]);

    $userId = $request->user()->id;

    $solicitante = DatosSolicitante::where('user_id', $userId)
        ->first();

    if (
        !$solicitante ||
        !$solicitante->datos_autorizados ||
        !trim((string) $solicitante->curp)
    ) {
        return response()->json([
            'mensaje' => 'Complete y autorice sus datos personales antes de consultar.',
        ], 422);
    }

    $numero = trim($datos['numero_certificado']);

    $certificado = CertificadoHistorico::where(
        'numero_certificado',
        $numero
    )->first();

    // Respuesta genérica para evitar revelar datos de terceros.
    $mensajeNoDisponible =
        'No fue posible verificar un certificado con los datos proporcionados.';

    if (!$certificado) {
        return response()->json([
            'encontrado' => false,
            'mensaje' => $mensajeNoDisponible,
        ]);
    }

    $curpUsuario = mb_strtoupper(
        trim((string) $solicitante->curp)
    );

    $curpHistorica = mb_strtoupper(
        trim((string) $certificado->curp_historica)
    );

    if (
        $curpHistorica === '' ||
        $curpUsuario !== $curpHistorica ||
        (
            $certificado->user_id !== null &&
            (int) $certificado->user_id !== (int) $userId
        )
    ) {
        return response()->json([
            'encontrado' => false,
            'mensaje' => $mensajeNoDisponible,
        ]);
    }

    $refrendos = [];

    for ($i = 1; $i <= 7; $i++) {
        $campo = 'refrendo_' . $i;
        $anio = trim((string) ($certificado->{$campo} ?? ''));

        if ($anio !== '') {
            $refrendos[] = [
                'numero' => $i,
                'anio' => $anio,
            ];
        }
    }

    return response()->json([
        'encontrado' => true,
        'mensaje' => 'Certificado verificado correctamente.',
        'historial' => [
            'certificacion' => $certificado->certificacion,
            'primera_vez' => $certificado->primera_vez,
            'refrendos' => $refrendos,
        ],
    ]);
}


    public function store(
        Request $request,
        ConsultaAntecedentesService $consultaAntecedentes
    ) {
        // Invalidar cualquier certificado validado en una solicitud anterior.
        $request->session()->forget('certificado_anterior_validado');

        $datos = $request->validate([
            'certificacion' => [
                'required',
                Rule::in(['ANFECA', 'CACSLA', 'CACECA']),
            ],
            'tipo_personal' => [
                'required', 'string', 'max:100',
            ],
            'area_certificacion' => [
                'required', 'string', 'max:150',
            ],
		'numero_certificado_anterior' => [
  		  'nullable', 'string', 'max:100',
],

        ]);

        $userId = $request->user()->id;

	// Proteger los refrendos existentes en borrador.
	$refrendoExistente = SolicitudCertificacion::with('certificadoHistorico')
	    ->where('user_id', $userId)
	    ->where('certificacion', $datos['certificacion'])
	    ->where('tipo_tramite', 'refrendo')
	    ->where('estado', 'borrador')
	    ->whereNotNull('certificado_historico_id')
	    ->first();

	if ($refrendoExistente) {
	    $certificacionHistorica =
	        $refrendoExistente->certificadoHistorico?->certificacion;

	    if (
	        !$certificacionHistorica ||
	        $datos['certificacion'] !== $certificacionHistorica
	    ) {
	        return back()
	            ->withErrors([
	                'certificacion' =>
	                    'La certificación de un refrendo existente no puede modificarse.',
	            ])
	            ->withInput();
	    }

	    $numeroAnterior = trim(
	        (string) ($datos['numero_certificado_anterior'] ?? '')
	    );

	    if (
	        $numeroAnterior !== '' &&
	        $numeroAnterior !== trim(
	            (string) $refrendoExistente->certificadoHistorico?->numero_certificado
	        )
	    ) {
	        return back()
	            ->withErrors([
	                'numero_certificado_anterior' =>
	                    'El número proporcionado no corresponde al certificado vinculado al refrendo.',
	            ])
	            ->withInput();
	    }
	}

	// Recuperar el número ingresado en el formulario.
	$numeroCertificado = trim(
	    (string) ($datos['numero_certificado_anterior'] ?? '')
	);

	// Recuperar el certificado histórico del refrendo existente,
	// pero volver a verificar su pertenencia al usuario.
	$certificadoValidado = null;

	if ($numeroCertificado === '' && $refrendoExistente) {
	    $certificadoAnterior =
	        $refrendoExistente->certificadoHistorico;

	    if ($certificadoAnterior) {
	        $certificadoValidado = $consultaAntecedentes
	            ->validarCertificadoAnterior(
	                $userId,
	                $datos['certificacion'],
	                $certificadoAnterior->numero_certificado
	            );
	    }

	    if (!$certificadoValidado) {
	        return back()
	            ->withErrors([
	                'numero_certificado_anterior' =>
	                    'No fue posible verificar el certificado histórico vinculado al refrendo.',
	            ])
	            ->withInput();
	    }
	}

	
	// Validar únicamente cuando se proporcionó un número.
	if ($numeroCertificado !== '') {
	    $certificadoValidado = $consultaAntecedentes
	        ->validarCertificadoAnterior(
	            $userId,
	            $datos['certificacion'],
	            $numeroCertificado
	        );

	    if (!$certificadoValidado) {
	        return back()
	            ->withErrors([
	                'numero_certificado_anterior' =>
	                    'No fue posible validar el certificado anterior. Verifique el número y la certificación seleccionada.',
	            ])
	            ->withInput();
	    }
	}

	try {
        $tipoTramite = DB::transaction(function () use (
            $userId,
            $datos,
            $consultaAntecedentes,
	    $certificadoValidado        
) {
            $solicitudesEnviadas = SolicitudCertificacion::where(
                'user_id', $userId
            )->where('certificacion', $datos['certificacion'])
             ->where('estado', '!=', 'borrador')
             ->exists();

            if ($solicitudesEnviadas) {
                return 'solicitud_existente';
            }

            SeleccionCertificacion::updateOrCreate(
		    ['user_id' => $userId],
		    [
		        'certificacion' => $datos['certificacion'],
		        'tipo_personal' => $datos['tipo_personal'],
		        'area_certificacion' => $datos['area_certificacion'],
		    ]
		);

            $tipo = $certificadoValidado
		    ? 'refrendo'
		    : $consultaAntecedentes->determinarTipoTramite($userId);

		// No permitir un refrendo sin certificado histórico validado.
		if ($tipo === 'refrendo' && $certificadoValidado === null) {
		    throw new \RuntimeException('revision_pendiente');
		}

		if (!in_array($tipo, ['inicial', 'refrendo'], true)) {
		    throw new \RuntimeException('revision_pendiente');
		}

		$solicitud = SolicitudCertificacion::firstOrCreate([
		    'user_id' => $userId,
		    'certificacion' => $datos['certificacion'],
		    'tipo_tramite' => $tipo,
		    'estado' => 'borrador',
		]);

		// Conservar la vinculación histórica existente.
		// Solo actualizarla cuando se haya validado un certificado.
		if ($certificadoValidado !== null) {
		    $solicitud->update([
		        'certificado_historico_id' => $certificadoValidado->id,
		    ]);
		}

            return $tipo;
        });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'revision_pendiente') {
                throw $e;
            }

            return redirect()
                ->route('registro.certificacion')
                ->with(
                    'error',
                    'Se encontraron antecedentes que requieren revisión antes de continuar.'
                );
        }

	// Eliminar cualquier referencia anterior para evitar reutilizarla.
	$request->session()->forget('certificado_anterior_validado');

	if ($tipoTramite === 'refrendo' && $certificadoValidado) {
	    $request->session()->put(
	        'certificado_anterior_validado',
	        [
	            'id' => $certificadoValidado->id,
	            'user_id' => $userId,
	            'certificacion' => $datos['certificacion'],
	        ]
	    );
	}

        if ($tipoTramite === 'solicitud_existente') {
            return redirect()
                ->route('registro.certificacion')
                ->with(
                    'error',
                    'Ya existe una solicitud registrada para esta certificación. Se requiere revisión antes de iniciar otro trámite.'
                );
        }

        if ($tipoTramite === 'refrendo') {
            return redirect()
                ->route('registro.refrendo')
                ->with('success', 'Se identificó automáticamente un trámite de refrendo.');
        }

        return redirect()
            ->route('registro.formacion')
            ->with('success', 'Se identificó automáticamente un trámite de primera vez.');
    }
}

