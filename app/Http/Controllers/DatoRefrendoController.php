<?php

namespace App\Http\Controllers;

use App\Models\SolicitudCertificacion;
use App\Models\SeleccionCertificacion;
use App\Models\DatoRefrendo;
use App\Services\ConsultaAntecedentesService;
use Illuminate\Http\Request;
use App\Models\CertificadoHistorico;

class DatoRefrendoController extends Controller

{
	private function certificadoValidadoEnSesion(
	    Request $request,
	    ConsultaAntecedentesService $consultaAntecedentes,
	    int $userId,
	    string $certificacion
	): ?CertificadoHistorico {

	    // Buscar primero el refrendo en borrador del usuario.
	    $solicitud = SolicitudCertificacion::with('certificadoHistorico')
	        ->where('user_id', $userId)
	        ->where('certificacion', $certificacion)
	        ->where('tipo_tramite', 'refrendo')
	        ->where('estado', 'borrador')
	        ->latest('id')
	        ->first();

	    // Si existe un certificado histórico vinculado,
	    // debe validarse nuevamente antes de utilizarlo.
	    if ($solicitud && $solicitud->certificado_historico_id !== null) {
	        $certificado = $solicitud->certificadoHistorico;

	        if (!$certificado) {
	            return null;
	        }

	        $validado = $consultaAntecedentes->validarCertificadoAnterior(
	            $userId,
	            $certificacion,
	            (string) $certificado->numero_certificado
	        );

	        return $validado && $validado->id === $certificado->id
	            ? $validado
	            : null;
	    }

	    // Si el borrador existe sin certificado vinculado,
	    // no utilizar una referencia de sesión para sustituirlo.
	    if ($solicitud) {
	        return null;
	    }

	    // Para un refrendo nuevo, consultar la sesión.
	    $referencia = $request->session()->get(
	        'certificado_anterior_validado'
	    );

	    if (!is_array($referencia)) {
	        return null;
	    }

	    if (
	        (int) ($referencia['user_id'] ?? 0) !== $userId ||
	        ($referencia['certificacion'] ?? '') !== $certificacion
	    ) {
	        return null;
	    }

	    $certificado = CertificadoHistorico::find(
	        $referencia['id'] ?? null
	    );

	    if (!$certificado) {
	        return null;
	    }

	    $validado = $consultaAntecedentes->validarCertificadoAnterior(
	        $userId,
	        $certificacion,
	        (string) $certificado->numero_certificado
	    );

	    return $validado && $validado->id === $certificado->id
	        ? $validado
	        : null;
	}
    public function create(
        Request $request,
        ConsultaAntecedentesService $consultaAntecedentes
    ) {
        $userId = $request->user()->id;

        // PASO 1002: recuperar exclusivamente el borrador activo.
        $solicitud = SolicitudCertificacion::where('user_id', $userId)
            ->where('estado', 'borrador')
            ->latest('id')
            ->first();

        if (
            !$solicitud ||
            $solicitud->tipo_tramite !== 'refrendo'
        ) {
            return redirect()
                ->route('registro.certificacion')
                ->with(
                    'error',
                    'No existe un refrendo activo. Primero debe guardar su selección de certificación.'
                );
        }

        $certificacion = $solicitud->certificacion;

        // Validar nuevamente el certificado histórico vinculado.
        $certificadoValidado = $this->certificadoValidadoEnSesion(
            $request,
            $consultaAntecedentes,
            $userId,
            $certificacion
        );

        if (!$certificadoValidado) {
            return redirect()
                ->route('registro.certificacion')
                ->with(
                    'error',
                    'No fue posible validar el certificado histórico del refrendo.'
                );
        }

        $datosRefrendo = $solicitud->datosRefrendo;
        
	$datosAutomaticos = $consultaAntecedentes
    		->obtenerDatosRefrendo(
        	$userId,
    		$certificadoValidado
    		);

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

	// PASO 1004: guardar únicamente el refrendo activo.
        $solicitud = SolicitudCertificacion::where('user_id', $userId)
            ->where('estado', 'borrador')
            ->latest('id')
            ->first();

        if (
            !$solicitud ||
            $solicitud->tipo_tramite !== 'refrendo'
        ) {
            return redirect()
                ->route('registro.certificacion')
                ->with(
                    'error',
                    'No existe una solicitud de refrendo activa.'
                );
        }

        $certificacion = $solicitud->certificacion;

        $certificadoValidado = $this->certificadoValidadoEnSesion(
            $request,
            $consultaAntecedentes,
            $userId,
            $certificacion
        );

        if (!$certificadoValidado) {
            return redirect()
                ->route('registro.certificacion')
                ->with(
                    'error',
                    'No fue posible validar el certificado histórico del refrendo.'
                );
        }

        $datosAutomaticos = $consultaAntecedentes
	    ->obtenerDatosRefrendo(
	        $userId,
	        $certificadoValidado
	    );

        if (!$datosAutomaticos) {
            return redirect()
                ->route('registro.certificacion')
                ->with('error', 'No fue posible verificar el refrendo.');
        }

        $datos = $request->validate([
            'aplica_articulo_14' => [
                'required',
                'boolean',
            ],
            'inciso_articulo_14' => [
                'nullable',
                'required_if:aplica_articulo_14,1',
                'in:13.1,14.1,14.2,14.3',
            ],
        ]);

        // Cuando responde No, el inciso no aplica.
        if ((int) $datos['aplica_articulo_14'] === 0) {
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

	// ETAPA 1: todos los refrendos, incluido artículo 14, continúan a Formación académica.
	return redirect()
	    ->route('registro.formacion')
	    ->with('success', 'Datos de refrendo guardados correctamente.');
    }

}
