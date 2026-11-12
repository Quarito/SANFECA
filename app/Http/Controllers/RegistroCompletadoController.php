<?php

namespace App\Http\Controllers;

use App\Models\CertificadoHistorico;
use App\Models\DatosSolicitante;
use App\Models\SeleccionCertificacion;
use App\Models\SolicitudCertificacion;
use App\Services\AcuseSolicitudService;
use App\Services\ConsultaAntecedentesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
        $certificado = CertificadoHistorico::find(
            $solicitud->certificado_historico_id
        );

        if (! $certificado) {
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
                        $certificado = CertificadoHistorico::find(
                            $solicitud->certificado_historico_id
                        );

                        if (! $certificado) {
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

        $omiteFormacion = (
            $solicitud->tipo_tramite === 'refrendo'
            && $datosRefrendo !== null
            && (int) $datosRefrendo->aplica_articulo_14 === 1
            && in_array(
                $datosRefrendo->inciso_articulo_14,
                ['14.1', '14.2', '14.3', '14.4'],
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
            'formacion_academica' => $omiteFormacion || $formacion !== null,
            'documentos_probatorios' => $documentos !== null,
        ];

        // Los refrendos por artículo 14 no requieren
        // volver a presentar documentación académica.
        if (! $omiteFormacion) {
            foreach (self::ACADEMICOS as $campo) {
                $ruta = $formacion?->{$campo};

                if (
                    ! $ruta
                    || ! Storage::disk('local')->exists($ruta)
                ) {
                    $resultado['formacion_academica'] = false;
                }
            }
        }

        foreach (self::PROBATORIOS as $campo) {
            $ruta = $documentos?->{$campo};

            if (
                ! $ruta
                || ! Storage::disk('local')->exists($ruta)
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
        $solicitud = $request->filled('solicitud')
            ? SolicitudCertificacion::where('user_id', $request->user()->id)
                ->findOrFail($request->integer('solicitud'))
            : $this->obtenerSolicitud($request, $servicio);

        if (! $solicitud) {
            return redirect()
                ->route('registro.certificacion')
                ->with('error', 'No se encontró una solicitud para revisar.');
        }

        $requisitos = $this->requisitos($solicitud);

        return view('registro.completado', [
            'solicitud' => $solicitud,
            'requisitos' => $requisitos,
            'registroCompleto' => ! in_array(false, $requisitos, true),
            'enviada' => $solicitud->estado === 'enviado',
            'acuseDisponible' => $solicitud->acuse()->exists(),
        ]);
    }

    public function confirmar(
        Request $request,
        ConsultaAntecedentesService $servicio,
        AcuseSolicitudService $acuses
    ) {
        $request->validate(['solicitud_id' => ['required', 'integer']]);
        $solicitud = SolicitudCertificacion::where('user_id', $request->user()->id)
            ->findOrFail($request->integer('solicitud_id'));

        if (! $solicitud) {
            return redirect()
                ->route('registro.certificacion')
                ->with('error', 'No existe una solicitud válida.');
        }

        if ($solicitud->estado === 'enviado') {
            return redirect()
                ->route('registro.completado', ['solicitud' => $solicitud->id])
                ->with('success', 'La solicitud ya fue enviada.');
        }

        if (! $this->tipoTramiteValido($solicitud, $servicio)) {
            return redirect()
                ->route('registro.completado', ['solicitud' => $solicitud->id])
                ->with(
                    'error',
                    'Los antecedentes requieren una nueva revisión antes del envío.'
                );
        }

        $requisitos = $this->requisitos($solicitud);

        if (in_array(false, $requisitos, true)) {
            return redirect()
                ->route('registro.completado', ['solicitud' => $solicitud->id])
                ->with('error', 'Debe completar todos los requisitos antes del envío.');
        }

        DB::transaction(function () use ($solicitud, $servicio, $acuses) {
            $actual = SolicitudCertificacion::whereKey($solicitud->id)
                ->where('user_id', $solicitud->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($actual->estado !== 'borrador') {
                return;
            }

            if (
                ! $this->tipoTramiteValido($actual, $servicio)
                || in_array(false, $this->requisitos($actual), true)
            ) {
                throw ValidationException::withMessages([
                    'solicitud' => 'La solicitud requiere una nueva revisión antes del envío.',
                ]);
            }

            $actual->update([
                'estado' => 'enviado',
                'fecha_envio' => now(),
            ]);

            $acuses->registrar($actual);
        });

        return redirect()
            ->route('registro.completado', ['solicitud' => $solicitud->id])
            ->with('success', 'Solicitud enviada correctamente.');
    }
}
