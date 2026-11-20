<?php

namespace App\Http\Controllers;

use App\Services\Pdm\Espejo\EspejoPdm;
use App\Models\SeleccionCertificacion;
use App\Models\SolicitudCertificacion;
use App\Services\ConsultaAntecedentesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AceptacionCompromisoController extends Controller
{
    private function obtenerBorrador(
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

        $borradores = SolicitudCertificacion::where(
            'user_id',
            $userId
        )
            ->where('certificacion', $certificacion)
            ->where('estado', 'borrador')
            ->latest('id')
            ->get();

        foreach ($borradores as $solicitud) {
            if ($solicitud->tipo_tramite !== $tipo) {
                continue;
            }

            if ($tipo === 'inicial') {
                return $solicitud;
            }

            if ($tipo !== 'refrendo') {
                continue;
            }

            if ($solicitud->certificado_historico_id === null) {
                return $solicitud;
            }

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
        }

        return null;
    }

    public function index(
        Request $request,
        ConsultaAntecedentesService $servicio
    ) {
        $solicitud = $this->obtenerBorrador(
            $request,
            $servicio
        );

        // Consulta de la aceptación de una solicitud ya enviada.
        if (!$solicitud) {
            $solicitud = SolicitudCertificacion::where(
                'user_id',
                $request->user()->id
            )
                ->where('estado', 'enviado')
                ->where('compromiso_aceptado', true)
                ->latest('id')
                ->first();
        }

        if (!$solicitud) {
            return redirect()
                ->route('registro.completado')
                ->with(
                    'error',
                    'No existe una solicitud en borrador válida.'
                );
        }

        return view('registro.aceptacion-compromiso', [
            'aceptado' => (bool) $solicitud->compromiso_aceptado,
            'aceptadoEn' => $solicitud->compromiso_aceptado_en
                ?->copy()
                ->timezone('America/Mexico_City')
                ->format('d/m/Y H:i'),
            'soloConsulta' => $solicitud->estado !== 'borrador',
        ]);
    }

    public function guardar(
        Request $request,
        ConsultaAntecedentesService $servicio
    ) {
        $request->validate([
            'compromiso_aceptado' => ['required', 'accepted'],
        ], [
            'compromiso_aceptado.accepted' =>
                'Debe aceptar el compromiso para continuar.',
            'compromiso_aceptado.required' =>
                'Debe aceptar el compromiso para continuar.',
        ]);

        $solicitud = $this->obtenerBorrador(
            $request,
            $servicio
        );

        if (!$solicitud) {
            return redirect()
                ->route('registro.completado')
                ->with(
                    'error',
                    'No existe una solicitud en borrador válida.'
                );
        }

        DB::transaction(function () use ($solicitud) {
            $actual = SolicitudCertificacion::whereKey(
                $solicitud->id
            )
                ->where('user_id', $solicitud->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($actual->estado !== 'borrador') {
                throw ValidationException::withMessages([
                    'solicitud' =>
                        'La solicitud ya no permite registrar la aceptación.',
                ]);
            }

            if (!$actual->compromiso_aceptado) {
                $actual->update([
                    'compromiso_aceptado' => true,
                    'compromiso_aceptado_en' => now(),
                ]);

                // F3: espejo del evento «Compromiso de entrega aceptado».
                app(EspejoPdm::class)->solicitud($actual->id);
            }
        });

        return redirect()
            ->route('registro.completado')
            ->with(
                'success',
                'Su compromiso fue registrado correctamente.'
            );
    }
}
