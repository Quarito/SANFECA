<?php

namespace App\Http\Controllers;

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

    private function obtenerSolicitud(
        Request $request,
        ConsultaAntecedentesService $servicio
    ): ?SolicitudCertificacion {
        $userId = $request->user()->id;

        // Permite consultar una solicitud ya enviada.
        $enviada = SolicitudCertificacion::where('user_id', $userId)
            ->where('estado', 'enviado')
            ->latest('id')
            ->first();

        $certificacion = SeleccionCertificacion::where(
            'user_id',
            $userId
        )->value('certificacion');

        $tipo = $servicio->determinarTipoTramite($userId);

        if (
            $certificacion
            && in_array($tipo, ['inicial', 'refrendo'], true)
        ) {
            $borrador = SolicitudCertificacion::where('user_id', $userId)
                ->where('certificacion', $certificacion)
                ->where('tipo_tramite', $tipo)
                ->where('estado', 'borrador')
                ->latest('id')
                ->first();

            if ($borrador) {
                return $borrador;
            }
        }

        return $enviada;
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

        foreach (self::ACADEMICOS as $campo) {
            $ruta = $formacion?->{$campo};

            if (
                !$ruta
                || !Storage::disk('local')->exists($ruta)
            ) {
                $resultado['formacion_academica'] = false;
            }
        }

        foreach (self::PROBATORIOS as $campo) {
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

        if ($servicio->determinarTipoTramite($request->user()->id) !== $solicitud->tipo_tramite) {
            return redirect()->route('registro.completado')->with('error', 'Los antecedentes requieren una nueva revisión antes del envío.');
        }

        $requisitos = $this->requisitos($solicitud);

        if (in_array(false, $requisitos, true)) {
            return redirect()
                ->route('registro.completado')
                ->with('error', 'Debe completar todos los requisitos antes del envío.');
        }

        DB::transaction(function () use ($solicitud, $servicio) {
            $actual = SolicitudCertificacion::whereKey($solicitud->id)
                ->where('user_id', $solicitud->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($actual->estado !== 'borrador') {
                return;
            }

		if (
		    $servicio->determinarTipoTramite($actual->user_id)
		        !== $actual->tipo_tramite
		    || in_array(false, $this->requisitos($actual), true)
		) {
		    throw \Illuminate\Validation\ValidationException::withMessages([
		        'solicitud' => 'La solicitud requiere una nueva revisión antes del envío.',
		    ]);
		}

            $actual->update([
                'estado' => 'enviado',
                'fecha_envio' => now(),
            ]);
        });

        return redirect()
            ->route('registro.completado')
            ->with('success', 'Solicitud enviada correctamente.');
    }
}
