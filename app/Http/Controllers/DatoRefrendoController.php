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

	$certificacion = SeleccionCertificacion::where(
	    'user_id',
	    $userId
	)->value('certificacion');

	$certificadoValidado = $certificacion
	    ? $this->certificadoValidadoEnSesion(
	        $request,
	        $consultaAntecedentes,
	        $userId,
	        $certificacion
	    )
	    : null;

	$tipoTramite = $consultaAntecedentes
	    ->determinarTipoTramite($userId);

	if ($tipoTramite !== 'refrendo' && !$certificadoValidado) {
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

	$certificacion = SeleccionCertificacion::where(
	    'user_id',
	    $userId
	)->value('certificacion');

	$certificadoValidado = $certificacion
	    ? $this->certificadoValidadoEnSesion(
	        $request,
	        $consultaAntecedentes,
	        $userId,
	        $certificacion
	    )
	    : null;

	$tipoTramite = $consultaAntecedentes
	    ->determinarTipoTramite($userId);

	if ($tipoTramite !== 'refrendo' && !$certificadoValidado) {
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

        // Artículo 14: omitir Formación académica.
	if ((int) $datos['aplica_articulo_14'] === 1) {
	    return redirect()
	        ->route('registro.documentos')
	        ->with(
	            'success',
	            'Datos de refrendo guardados correctamente. Continúe con Documentos probatorios.'
	        );
	}

	// Trámite ordinario: continuar con Formación académica.
	return redirect()
	    ->route('registro.formacion')
	    ->with('success', 'Datos de refrendo guardados correctamente.');
    }

}
