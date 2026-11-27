<?php

namespace App\Http\Controllers;

use App\Services\Anfeca\DocumentosPdm;
use App\Services\Anfeca\ExpedienteSolicitante;
use App\Services\Anfeca\SolicitudActiva;
use App\Services\Anfeca\SolicitudVista as SolicitudCertificacion;
use App\Services\Anfeca\Tramites;
use App\Services\Formacion\RequisitosFormacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Finalización: revisa requisitos sobre las 36 tablas y registra el evento «Enviada», que bloquea toda
 * modificación posterior de la solicitud.
 */
class RegistroCompletadoController extends Controller
{
    private const PROBATORIOS = [
        'constancia_antiguedad_archivo',
        'comprobante_pago_archivo',
    ];
public function __construct(
    private readonly SolicitudActiva $activa,
    private readonly Tramites $tramites,
    private readonly DocumentosPdm $documentosPdm,
    private readonly ExpedienteSolicitante $expediente,
) {
}

/** El borrador (con su certificado revalidado si es refrendo) o la última solicitud enviada. */
private function obtenerSolicitud(Request $request): ?SolicitudCertificacion
{
    return $this->activa->paraConsulta($request->user());
}

    private function requisitos(
        SolicitudCertificacion $solicitud
    ): array {
        $datos = $this->expediente->datos($solicitud->user_id);

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
                array_keys((array) config('anfeca_8088.certificaciones')),
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

        // Todos los trámites requieren datos académicos completos (centralizado: en el escenario «solo
        // licenciatura» el último grado es la licenciatura y no se exigen campos ni documentos duplicados).
        $camposAcademicos = RequisitosFormacion::campos($formacion);

        foreach ($camposAcademicos as $campo) {
            $valor = $formacion?->{$campo};

            if ($valor === null || trim((string) $valor) === '') {
                $resultado['formacion_academica'] = false;
            }
        }

        // «Solo licenciatura» no basta si el área de certificación exige posgrado (p. ej., cambió después de guardar).
        if (RequisitosFormacion::faltaPosgradoObligatorio($formacion, $solicitud->area_certificacion)) {
            $resultado['formacion_academica'] = false;
        }

        // Documentos académicos aplicables (centralizado; incluidos los refrendos del artículo 14).
	    foreach (RequisitosFormacion::documentos($formacion) as $campo) {
	        $ruta = $formacion?->{$campo};

	        if (
	            !$ruta
	            || !$this->documentosPdm->disco()->exists($ruta)
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
                || !$this->documentosPdm->disco()->exists($ruta)
            ) {
                $resultado['documentos_probatorios'] = false;
            }
        }

        return $resultado;
    }

    public function index(Request $request)
    {
        $solicitud = $this->obtenerSolicitud($request);

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

    public function confirmar(Request $request)
    {
        $solicitud = $this->obtenerSolicitud($request);

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

        $envioRealizado = DB::transaction(function () use ($solicitud) {
            $actual = $this->tramites->bloquear($solicitud->id);

            if (!$actual || $actual->estado !== 'borrador') {
                return false;
            }

            if (in_array(false, $this->requisitos($actual), true)) {
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

            if (!filled($actual->tipo_personal) || !filled($actual->area_certificacion)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'solicitud' => 'Debe revisar la selección de certificación antes del envío.',
                ]);
            }

            // Evento «Enviada»: a partir de aquí la solicitud queda bloqueada.
            $this->tramites->registrarEvento($actual->id, 'EN');

            return true;
        });

        if (!$envioRealizado) {
            return redirect()
                ->route('registro.completado')
                ->with('success', 'La solicitud ya fue enviada.');
        }

        return redirect()
            ->route('registro.completado')
            ->with('success', 'Su solicitud fue registrada correctamente.')
            ->with('descargar_acuse_solicitud_id', $solicitud->id);
    }
}
