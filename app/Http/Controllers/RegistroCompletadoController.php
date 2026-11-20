<?php

namespace App\Http\Controllers;

use App\Services\Pdm\Espejo\EspejoPdm;
use App\Models\DatosSolicitante;
use App\Models\SeleccionCertificacion;
use App\Models\SolicitudCertificacion;
use App\Services\ConsultaAntecedentesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RegistroCompletadoController extends Controller
{
    private const ACADEMICOS = [
        'titulo_origen_archivo',
        'cedula_origen_archivo',
        'titulo_ultimo_grado_archivo',
        'cedula_ultimo_grado_archivo',
    ];

    private const PROBATORIOS = [
        'constancia_antiguedad_archivo',
        'comprobante_pago_archivo',
    ];
private function tipoTramiteValido(
    SolicitudCertificacion $solicitud,
    ConsultaAntecedentesService $servicio
): bool {
    $tipoDetectado = $servicio->determinarTipoTramite(
        $solicitud->user_id
    );

    if ($solicitud->tipo_tramite === 'inicial') {
        return $tipoDetectado === 'inicial';
    }

    if ($solicitud->tipo_tramite !== 'refrendo') {
        return false;
    }

    // Compatibilidad con solicitudes anteriores.
    if ($solicitud->certificado_historico_id === null) {
        return $tipoDetectado === 'refrendo';
    }

    // Validar el certificado histórico asociado.
    $certificado = \App\Models\CertificadoHistorico::find(
        $solicitud->certificado_historico_id
    );

    if (!$certificado) {
        return false;
    }

    $validado = $servicio->validarCertificadoAnterior(
        $solicitud->user_id,
        $solicitud->certificacion,
        (string) $certificado->numero_certificado
    );

    return $validado !== null
        && (int) $validado->id === (int) $certificado->id;
}
private function obtenerSolicitud(
    Request $request,
    ConsultaAntecedentesService $servicio
): ?SolicitudCertificacion {
    $userId = $request->user()->id;

    $certificacion = SeleccionCertificacion::where(
        'user_id',
        $userId
    )->value('certificacion');

    if ($certificacion) {
        $tipo = $servicio->determinarTipoTramite($userId);

        $borradores = SolicitudCertificacion::where('user_id', $userId)
            ->where('certificacion', $certificacion)
            ->where('estado', 'borrador')
            ->latest('id')
            ->get();

        foreach ($borradores as $solicitud) {
            if ($solicitud->tipo_tramite === 'refrendo') {
                // Si tiene certificado asociado, debe validarse siempre.
                if ($solicitud->certificado_historico_id !== null) {
                    $certificado = \App\Models\CertificadoHistorico::find(
                        $solicitud->certificado_historico_id
                    );

                    if (!$certificado) {
                        continue;
                    }

                    $validado = $servicio->validarCertificadoAnterior(
                        $userId,
                        $certificacion,
                        (string) $certificado->numero_certificado
                    );

                    if (
                        $validado !== null
                        && (int) $validado->id === (int) $certificado->id
                    ) {
                        return $solicitud;
                    }

                    continue;
                }

                // Compatibilidad con refrendos anteriores sin asociación.
                if ($tipo === 'refrendo') {
                    return $solicitud;
                }
            }

            if (
                $solicitud->tipo_tramite === 'inicial'
                && $tipo === 'inicial'
            ) {
                return $solicitud;
            }
        }
    }

    // Permitir consultar una solicitud enviada anteriormente.
    return SolicitudCertificacion::where('user_id', $userId)
        ->where('estado', 'enviado')
        ->latest('id')
        ->first();
}

    private function requisitos(
        SolicitudCertificacion $solicitud
    ): array {
        $datos = DatosSolicitante::where(
            'user_id',
            $solicitud->user_id
        )->first();

        $formacion = $solicitud->formacionAcademica;
        $documentos = $solicitud->documentosProbatorios;
	// Identificar refrendos que aplican al artículo 14.
	$datosRefrendo = $solicitud->datosRefrendo;

	$esArticulo14 = (
	    $solicitud->tipo_tramite === 'refrendo'
	    && $datosRefrendo !== null
	    && (int) $datosRefrendo->aplica_articulo_14 === 1
	    && in_array(
	        $datosRefrendo->inciso_articulo_14,
	        ['13.1', '14', '14.1', '14.2', '14.3'],
	        true
	    )
	);

        $resultado = [
            'datos_personales' => $datos !== null
                && (bool) $datos->datos_autorizados,
            'certificacion' => in_array(
                $solicitud->certificacion,
                ['ANFECA', 'CACSLA', 'CACECA'],
                true
            ),
            'refrendo' => $solicitud->tipo_tramite === 'inicial'
                || (
                    $solicitud->tipo_tramite === 'refrendo'
                    && $solicitud->datosRefrendo !== null
                ),
            'formacion_academica' => $formacion !== null,
            'documentos_probatorios' => $documentos !== null,
        ];

        // Todos los trámites requieren datos académicos completos.
        $camposAcademicos = [
            'nivel_origen',
            'titulo_origen',
            'nivel_ultimo_grado',
            'titulo_ultimo_grado',
            'institucion_afiliada_id',
        ];

        foreach ($camposAcademicos as $campo) {
            $valor = $formacion?->{$campo};

            if ($valor === null || trim((string) $valor) === '') {
                $resultado['formacion_academica'] = false;
            }
        }

        // Todos los trámites requieren los cuatro documentos
        // académicos, incluidos los refrendos del artículo 14.
	    foreach (self::ACADEMICOS as $campo) {
	        $ruta = $formacion?->{$campo};

	        if (
	            !$ruta
	            || !Storage::disk('local')->exists($ruta)
	        ) {
	            $resultado['formacion_academica'] = false;
	        }
	    }

        // VALIDACION_ART14_97184
        // Artículo 14 exige cuatro documentos probatorios.
        $camposProbatorios = self::PROBATORIOS;

        if ($esArticulo14) {
            $camposProbatorios = array_merge(
                $camposProbatorios,
                [
                    'solicitud_articulo_14_archivo',
                    'constancia_articulo_14_archivo',
                ]
            );
        }

        foreach ($camposProbatorios as $campo) {
            $ruta = $documentos?->{$campo};

            if (
                !$ruta
                || !Storage::disk('local')->exists($ruta)
            ) {
                $resultado['documentos_probatorios'] = false;
            }
        }

        return $resultado;
    }

    public function index(
        Request $request,
        ConsultaAntecedentesService $servicio
    ) {
        $solicitud = $this->obtenerSolicitud($request, $servicio);

        if (!$solicitud) {
            return redirect()
                ->route('registro.certificacion')
                ->with('error', 'No se encontró una solicitud para revisar.');
        }

        // BLOQUEO_FINALIZACION_COMPROMISO_20261119
        // Las solicitudes históricas enviadas conservan su acceso.
        if (
            $solicitud->estado === 'borrador'
            && !$solicitud->compromiso_aceptado
        ) {
            return redirect()
                ->route('registro.compromiso')
                ->with(
                    'error',
                    'Debe aceptar el compromiso de entrega de documentación antes de acceder a Finalización.'
                );
        }

        $requisitos = $this->requisitos($solicitud);

        return view('registro.completado', [
            'solicitud' => $solicitud,
            'requisitos' => $requisitos,
            'registroCompleto' => !in_array(false, $requisitos, true),
            'enviada' => $solicitud->estado === 'enviado',
        ]);
    }

    public function confirmar(
        Request $request,
        ConsultaAntecedentesService $servicio
    ) {
        $solicitud = $this->obtenerSolicitud($request, $servicio);

        if (!$solicitud) {
            return redirect()
                ->route('registro.certificacion')
                ->with('error', 'No existe una solicitud válida.');
        }

        if ($solicitud->estado === 'enviado') {
            return redirect()
                ->route('registro.completado')
                ->with('success', 'La solicitud ya fue enviada.');
        }

        if (!$this->tipoTramiteValido($solicitud, $servicio)) {
    return redirect()
        ->route('registro.completado')
        ->with(
            'error',
            'Los antecedentes requieren una nueva revisión antes del envío.'
        );
}

        $requisitos = $this->requisitos($solicitud);

        if (in_array(false, $requisitos, true)) {
            return redirect()
                ->route('registro.completado')
                ->with('error', 'Debe completar todos los requisitos antes del envío.');
        }

        // VALIDACION_COMPROMISO_ANFECA_20261119
        if (!$solicitud->compromiso_aceptado) {
            return redirect()
                ->route('registro.compromiso')
                ->with(
                    'error',
                    'Debe aceptar el compromiso de entrega de documentación antes de enviar su solicitud.'
                );
        }

        $envioRealizado = DB::transaction(function () use ($solicitud, $servicio) {
            $actual = SolicitudCertificacion::whereKey($solicitud->id)
                ->where('user_id', $solicitud->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($actual->estado !== 'borrador') {
                return false;
            }

		if (
		    !$this->tipoTramiteValido($actual, $servicio)
		    || in_array(false, $this->requisitos($actual), true)
		) {
		    throw \Illuminate\Validation\ValidationException::withMessages([
		        'solicitud' => 'La solicitud requiere una nueva revisión antes del envío.',
		    ]);
		}

            // VALIDACION_COMPROMISO_ANFECA_20261119
            if (!$actual->compromiso_aceptado) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'compromiso_aceptado' =>
                        'Debe aceptar el compromiso antes de confirmar el envío.',
                ]);
            }

            // Conservar los datos de certificación al momento del envío.
            $seleccion = \App\Models\SeleccionCertificacion::where(
                'user_id',
                $actual->user_id
            )->where(
                'certificacion',
                $actual->certificacion
            )->first();

            if (
                !$seleccion
                || !filled($seleccion->tipo_personal)
                || !filled($seleccion->area_certificacion)
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'solicitud' => 'Debe revisar la selección de certificación antes del envío.',
                ]);
            }

            $actual->update([
                'estado' => 'enviado',
                'fecha_envio' => now(),
                'tipo_personal' => $seleccion->tipo_personal,
                'area_certificacion' => $seleccion->area_certificacion,
            ]);

            // F3: espejo del envío (evento «Enviada»).
            app(EspejoPdm::class)->solicitud($actual->id);

            return true;
        });

        if (!$envioRealizado) {
            return redirect()
                ->route('registro.completado')
                ->with('success', 'La solicitud ya fue enviada.');
        }

        return redirect()
            ->route('registro.completado')
            ->with('success', 'Solicitud enviada correctamente.')
            ->with('descargar_acuse_solicitud_id', $solicitud->id);
    }
}
