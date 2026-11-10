<?php

namespace App\Http\Controllers;

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

        if (!in_array($tipo, ['inicial', 'refrendo'], true)) {
            return null;
        }

        return SolicitudCertificacion::where('user_id', $userId)
            ->where('certificacion', $certificacion)
            ->where('tipo_tramite', $tipo)
            ->where('estado', 'borrador')
            ->latest('id')
            ->first();
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

        if (!$solicitud->formacionAcademica) {
            return redirect()
                ->route('registro.formacion')
                ->with('error', 'Primero debe completar la formación académica.');
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

        foreach (self::NUEVOS as $campo => $nombre) {
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

        $reglas = [
            'link' => ['nullable', 'url:http,https', 'max:2048'],
        ];

        foreach (self::NUEVOS as $campo => $nombre) {
            $reglas[$campo] = [
                empty($actual?->{$campo}) ? 'required' : 'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ];
        }

        $datos = $request->validate($reglas);

        foreach (array_keys(self::NUEVOS) as $campo) {
            unset($datos[$campo]);
        }

        $archivosNuevos = [];

        try {
            foreach (self::NUEVOS as $campo => $nombre) {
                if ($request->hasFile($campo)) {
                    $archivosNuevos[$campo] = $request
                        ->file($campo)
                        ->store(
                            'anfeca/documentos/solicitudes/' . $solicitud->id,
                            'local'
                        );
                }
            }

            DocumentoProbatorioSolicitud::updateOrCreate(
                ['solicitud_id' => $solicitud->id],
                array_merge($datos, $archivosNuevos)
            );
        } catch (\Throwable $e) {
            foreach ($archivosNuevos as $ruta) {
                Storage::disk('local')->delete($ruta);
            }

            report($e);

            return back()
                ->withInput()
                ->withErrors([
                    'documentos' => 'No fue posible guardar los documentos.',
                ]);
        }

        return redirect()
            ->route('registro.completado')
            ->with('success', 'Documentos guardados correctamente. Revise su solicitud antes de enviarla.');
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
        } elseif (array_key_exists($campo, self::NUEVOS)) {
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
