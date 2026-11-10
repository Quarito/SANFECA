<?php

namespace App\Http\Controllers;

use App\Models\SeleccionCertificacion;
use App\Models\SolicitudCertificacion;
use App\Services\ConsultaAntecedentesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

    public function store(
        Request $request,
        ConsultaAntecedentesService $consultaAntecedentes
    ) {
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
        ]);

        $userId = $request->user()->id;

        try {
        $tipoTramite = DB::transaction(function () use (
            $userId,
            $datos,
            $consultaAntecedentes
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
                $datos
            );

            $tipo = $consultaAntecedentes
                ->determinarTipoTramite($userId);

            if (!in_array($tipo, ['inicial', 'refrendo'], true)) {
                throw new \RuntimeException('revision_pendiente');
            }

            SolicitudCertificacion::firstOrCreate([
                'user_id' => $userId,
                'certificacion' => $datos['certificacion'],
                'tipo_tramite' => $tipo,
                'estado' => 'borrador',
            ]);

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

