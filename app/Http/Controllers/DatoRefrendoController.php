<?php

namespace App\Http\Controllers;

use App\Models\SolicitudCertificacion;
use App\Models\SeleccionCertificacion;
use App\Models\DatoRefrendo;
use App\Services\ConsultaAntecedentesService;
use Illuminate\Http\Request;

class DatoRefrendoController extends Controller
{
    public function create(
        Request $request,
        ConsultaAntecedentesService $consultaAntecedentes
    ) {
        $userId = $request->user()->id;

        $tipoTramite = $consultaAntecedentes
            ->determinarTipoTramite($userId);

        if ($tipoTramite !== 'refrendo') {
            return redirect()
                ->route('registro.certificacion')
                ->with(
                    'error',
                    'No es posible acceder a los datos de refrendo. Verifique sus antecedentes de certificación.'
                );
        }

        $certificacion = SeleccionCertificacion::where('user_id', $userId)
            ->value('certificacion');

        if (!$certificacion) {
            return redirect()->route('registro.certificacion')
                ->with('error', 'Primero debe seleccionar una certificación.');
        }

        $solicitud = SolicitudCertificacion::where('user_id', $userId)
            ->where('certificacion', $certificacion)
            ->where('tipo_tramite', 'refrendo')
            ->where('estado', 'borrador')
            ->latest('id')
            ->first();

        if (!$solicitud) {
            return redirect()
                ->route('registro.certificacion')
                ->with(
                    'error',
                    'Primero debe guardar su selección de certificación para iniciar el refrendo.'
                );
        }

        $datosRefrendo = $solicitud->datosRefrendo;
        $datosAutomaticos = $consultaAntecedentes
            ->obtenerDatosRefrendo($userId);

        return view('registro.refrendo', [
            'solicitud' => $solicitud,
            'datosRefrendo' => $datosRefrendo,
            'datosAutomaticos' => $datosAutomaticos,
        ]);
    }

    public function store(
        Request $request,
        ConsultaAntecedentesService $consultaAntecedentes
    ) {
        $userId = $request->user()->id;

        if ($consultaAntecedentes->determinarTipoTramite($userId) !== 'refrendo') {
            return redirect()
                ->route('registro.certificacion')
                ->with('error', 'El trámite no está autorizado como refrendo.');
        }

        $certificacion = SeleccionCertificacion::where('user_id', $userId)
            ->value('certificacion');

        if (!$certificacion) {
            return redirect()->route('registro.certificacion')
                ->with('error', 'Primero debe seleccionar una certificación.');
        }

        $solicitud = SolicitudCertificacion::where('user_id', $userId)
            ->where('certificacion', $certificacion)
            ->where('tipo_tramite', 'refrendo')
            ->where('estado', 'borrador')
            ->latest('id')
            ->first();

        if (!$solicitud) {
            return redirect()
                ->route('registro.certificacion')
                ->with('error', 'No se encontró una solicitud de refrendo válida.');
        }

        $datosAutomaticos = $consultaAntecedentes
            ->obtenerDatosRefrendo($userId);

        if (!$datosAutomaticos) {
            return redirect()
                ->route('registro.certificacion')
                ->with('error', 'No fue posible verificar el refrendo.');
        }

        $datos = $request->validate([
            'aplica_articulo_14' => ['required', 'boolean'],
            'inciso_articulo_14' => [
                'nullable',
                'required_if:aplica_articulo_14,1',
                'in:14.1,14.2,14.3,14.4',
            ],
        ]);

        if (!$datos['aplica_articulo_14']) {
            $datos['inciso_articulo_14'] = null;
        }

        $datos['refrendo_actual'] =
            $datosAutomaticos['refrendo_actual'];

        // Conservar un número oficial existente, si ya fue asignado.
        $registroExistente = DatoRefrendo::where(
            'solicitud_id',
            $solicitud->id
        )->first();

        $datos['numero_refrendo_asignado'] =
            $registroExistente?->numero_refrendo_asignado;

        DatoRefrendo::updateOrCreate(
            ['solicitud_id' => $solicitud->id],
            $datos
        );

        return redirect()
            ->route('registro.formacion')
            ->with('success', 'Datos de refrendo guardados correctamente.');
    }

}
