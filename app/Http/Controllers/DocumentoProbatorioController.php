<?php

namespace App\Http\Controllers;

use App\Services\Pdm\Espejo\EspejoPdm;
use App\Models\DocumentoProbatorioSolicitud;
use App\Models\SeleccionCertificacion;
use App\Models\SolicitudCertificacion;
use App\Services\ConsultaAntecedentesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentoProbatorioController extends Controller
{
    private const ACADEMICOS = [
        'titulo_origen_archivo' => 'Título de Licenciatura',
        'cedula_origen_archivo' => 'Cédula de Licenciatura',
        'titulo_ultimo_grado_archivo' => 'Título de último grado',
        'cedula_ultimo_grado_archivo' => 'Cédula de último grado',
    ];

    private const NUEVOS = [
        'constancia_antiguedad_archivo' => 'Constancia de Antigüedad',
        'comprobante_pago_archivo' => 'Comprobante de Pago',
    ];

    // ARTICULO_14_DOCUMENTOS_97182
    private const ARTICULO_14 = [
        'solicitud_articulo_14_archivo' => 'Solicitud por artículo',
        'constancia_articulo_14_archivo' => 'Constancia probatoria de artículo',
    ];

    private function esArticulo14(SolicitudCertificacion $solicitud): bool
    {
        $datos = $solicitud->datosRefrendo;

        return $solicitud->tipo_tramite === 'refrendo'
            && $datos !== null
            && (int) $datos->aplica_articulo_14 === 1
            && in_array(
                $datos->inciso_articulo_14,
                ['13.1', '14', '14.1', '14.2', '14.3'],
                true
            );
    }

private function solicitudActiva(
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

    // Mantener el comportamiento original para trámites
    // cuyo tipo puede determinarse normalmente.
    if (in_array($tipo, ['inicial', 'refrendo'], true)) {
        $solicitud = SolicitudCertificacion::where('user_id', $userId)
            ->where('certificacion', $certificacion)
            ->where('tipo_tramite', $tipo)
            ->where('estado', 'borrador')
            ->latest('id')
            ->first();

        if ($solicitud) {
            return $solicitud;
        }
    }

    // Recuperar únicamente un refrendo con certificado
    // histórico asociado y validado.
    $solicitudes = SolicitudCertificacion::where('user_id', $userId)
        ->where('certificacion', $certificacion)
        ->where('tipo_tramite', 'refrendo')
        ->where('estado', 'borrador')
        ->whereNotNull('certificado_historico_id')
        ->latest('id')
        ->get();

    foreach ($solicitudes as $solicitud) {
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

        if ($validado && (int) $validado->id === (int) $certificado->id) {
            return $solicitud;
        }
    }

    return null;
}

    private function verificarSolicitud(
        Request $request,
        ConsultaAntecedentesService $servicio
    ) {
        $solicitud = $this->solicitudActiva($request, $servicio);

        if (!$solicitud) {
            return redirect()
                ->route('registro.certificacion')
                ->with('error', 'No existe una solicitud válida para continuar.');
        }

        if (
            $solicitud->tipo_tramite === 'refrendo'
            && !$solicitud->datosRefrendo
        ) {
            return redirect()
                ->route('registro.refrendo')
                ->with('error', 'Primero debe completar los datos de refrendo.');
        }

        // FORMACION_OBLIGATORIA_ETAPA_1_ANFECA
        // Aplica también a refrendos del artículo 14.
        $formacion = $solicitud->formacionAcademica;

        if (
            !$formacion
            || $formacion->formacion_completada_at === null
        ) {
            return redirect()
                ->route('registro.formacion')
                ->with(
                    'error',
                    'Primero debe guardar y autorizar su formación académica.'
                );
        }

        return $solicitud;
    }

    public function create(
        Request $request,
        ConsultaAntecedentesService $servicio
    ) {
        $resultado = $this->verificarSolicitud($request, $servicio);

        if (!$resultado instanceof SolicitudCertificacion) {
            return $resultado;
        }

        $solicitud = $resultado;
        $formacion = $solicitud->formacionAcademica;
        $documentos = $solicitud->documentosProbatorios;

        $academicos = [];

        foreach (self::ACADEMICOS as $campo => $nombre) {
            $ruta = $formacion?->{$campo};
            $disponible = !empty($ruta)
                && Storage::disk('local')->exists($ruta);

            $academicos[$campo] = [
                'nombre' => $nombre,
                'disponible' => $disponible,
                'url' => $disponible
                    ? route('registro.documentos.ver', $campo)
                    : null,
            ];
        }

        $nuevos = [];

        $esArticulo14 = $this->esArticulo14($solicitud);
        $camposDocumentos = $esArticulo14
            ? array_merge(self::NUEVOS, self::ARTICULO_14)
            : self::NUEVOS;

        foreach ($camposDocumentos as $campo => $nombre) {
            $ruta = $documentos?->{$campo};
            $disponible = !empty($ruta)
                && Storage::disk('local')->exists($ruta);

            $nuevos[$campo] = [
                'nombre' => $nombre,
                'disponible' => $disponible,
                'url' => $disponible
                    ? route('registro.documentos.ver', $campo)
                    : null,
            ];
        }

        return view('registro.documentos-probatorios', [
            'academicos' => $academicos,
            'nuevos' => $nuevos,
            'link' => old('link', $documentos?->link ?? ''),
            'esArticulo14' => $esArticulo14,
        ]);
    }

    public function store(
        Request $request,
        ConsultaAntecedentesService $servicio
    ) {
        $resultado = $this->verificarSolicitud($request, $servicio);

        if (!$resultado instanceof SolicitudCertificacion) {
            return $resultado;
        }

        $solicitud = $resultado;
        $actual = $solicitud->documentosProbatorios;

        $esArticulo14 = $this->esArticulo14($solicitud);

        $camposDocumentos = $esArticulo14
            ? array_merge(self::NUEVOS, self::ARTICULO_14)
            : self::NUEVOS;

        $reglas = [
            'link' => $esArticulo14
                ? ['exclude']
                : ['required', 'url:http,https', 'max:2048'],
        ];

        foreach ($camposDocumentos as $campo => $nombre) {
            $reglas[$campo] = [
                empty($actual?->{$campo}) ? 'required' : 'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ];
        }

        $datos = $request->validate($reglas);

        foreach (array_keys($camposDocumentos) as $campo) {
            unset($datos[$campo]);
        }

        $archivosNuevos = [];

        try {
            foreach ($camposDocumentos as $campo => $nombre) {
                if ($request->hasFile($campo)) {
                    $archivosNuevos[$campo] = $request
                        ->file($campo)
                        ->store(
                            'anfeca/documentos/solicitudes/' . $solicitud->id,
                            'local'
                        );
                }
            }

            \Illuminate\Support\Facades\DB::transaction(
                function () use ($solicitud, $datos, $archivosNuevos) {
                    $solicitudBloqueada = SolicitudCertificacion::query()
                        ->whereKey($solicitud->id)
                        ->where('user_id', $solicitud->user_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($solicitudBloqueada->estado !== 'borrador') {
                        throw new \RuntimeException(
                            'La solicitud ya no admite modificaciones.'
                        );
                    }

                    DocumentoProbatorioSolicitud::updateOrCreate(
                        ['solicitud_id' => $solicitudBloqueada->id],
                        array_merge($datos, $archivosNuevos)
                    );

                    // F3: espejo de documentos probatorios, enlace y pago.
                    app(EspejoPdm::class)->solicitud($solicitudBloqueada->id);
                }
            );
        } catch (\Throwable $e) {
            foreach ($archivosNuevos as $ruta) {
                Storage::disk('local')->delete($ruta);
            }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                // F3: error controlado del espejo PDM (la transacción ya se revirtió).
                throw $e;
            }
            if ($e instanceof \Illuminate\Database\QueryException
                && ($controlado = \App\Services\Pdm\ErrorValidacionPdm::desde($e)) !== null) {
                // F4/F5: dato no registrable en el PDM (disparadores anfeca_compat).
                throw $controlado;
            }

            report($e);

            return back()
                ->withInput()
                ->withErrors([
                    'documentos' => 'No fue posible guardar los documentos.',
                ]);
        }

        // NAVEGACION_COMPROMISO_ANFECA_20261119
        return redirect()
            ->route('registro.compromiso')
            ->with(
                'success',
                'Documentos guardados correctamente. Revise y acepte el compromiso de entrega de documentación para continuar.'
            );
    }

    public function ver(
        Request $request,
        string $campo,
        ConsultaAntecedentesService $servicio
    ) {
        $resultado = $this->verificarSolicitud($request, $servicio);

        if (!$resultado instanceof SolicitudCertificacion) {
            return $resultado;
        }

        $solicitud = $resultado;

        if (array_key_exists($campo, self::ACADEMICOS)) {
            $registro = $solicitud->formacionAcademica;
        } elseif (
            array_key_exists($campo, self::NUEVOS)
            || (
                $this->esArticulo14($solicitud)
                && array_key_exists($campo, self::ARTICULO_14)
            )
        ) {
            $registro = $solicitud->documentosProbatorios;
        } else {
            abort(404);
        }

        $ruta = $registro?->{$campo};

        abort_if(
            empty($ruta) || !Storage::disk('local')->exists($ruta),
            404
        );

        return Storage::disk('local')->download($ruta);
    }
}
