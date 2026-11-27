<?php

namespace App\Http\Controllers;

use App\Services\Anfeca\DocumentosPdm;
use App\Services\Anfeca\SolicitudActiva;
use App\Services\Anfeca\SolicitudVista as SolicitudCertificacion;
use App\Services\Anfeca\Tramites;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Documentos probatorios sobre SOLICITUD_DOCUMENTO, ESTADO_SOLICITUD_DOCUMENTO y PAGO (36 tablas oficiales).
 * Una vez guardados quedan en solo consulta, igual que antes.
 */
class DocumentoProbatorioController extends Controller
{
    public function __construct(
        private readonly SolicitudActiva $activa,
        private readonly DocumentosPdm $documentosPdm,
        private readonly Tramites $tramites,
    ) {
    }

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

    // FORMATO_ARTICULO_14_ANFECA
    // Formato oficial (Word) de la solicitud de refrendo por artículo 14.
    private const FORMATO_ARTICULO_14 = 'documentos/ANFECA_Formato_Refrendo_Articulo_14.docx';
    private const FORMATO_ARTICULO_14_NOMBRE = 'Solicitud_Refrendo_Articulo_14_ANFECA.docx';

    /**
     * El formato se ofrece SOLO en refrendos por artículo 14 (incisos 14, 14.1,
     * 14.2 y 14.3). El supuesto 13.1 comparte documentos con el artículo 14
     * (esArticulo14) pero, por ahora, no habilita la descarga del formato.
     */
    private function ofreceFormatoArticulo14(SolicitudCertificacion $solicitud): bool
    {
        $datos = $solicitud->datosRefrendo;

        return $solicitud->tipo_tramite === 'refrendo'
            && $datos !== null
            && (int) $datos->aplica_articulo_14 === 1
            && in_array(
                (string) $datos->inciso_articulo_14,
                ['14', '14.1', '14.2', '14.3'],
                true
            );
    }

private function solicitudActiva(Request $request): ?SolicitudCertificacion
{
    return $this->activa->borrador($request->user());
}

    private function verificarSolicitud(Request $request)
    {
        $solicitud = $this->solicitudActiva($request);

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

    public function create(Request $request)
    {
        $resultado = $this->verificarSolicitud($request);

        if (!$resultado instanceof SolicitudCertificacion) {
            return $resultado;
        }

        $solicitud = $resultado;
        $formacion = $solicitud->formacionAcademica;
        $documentos = $solicitud->documentosProbatorios;

        $academicos = [];

        foreach (self::ACADEMICOS as $campo => $nombre) {
            if (!\App\Services\Formacion\RequisitosFormacion::documentoAplica($formacion, $campo)) {
                continue;   // solo licenciatura: sin documentos de posgrado
            }
            $ruta = $formacion?->{$campo};
            $disponible = !empty($ruta)
                && $this->documentosPdm->disco()->exists($ruta);

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
                && $this->documentosPdm->disco()->exists($ruta);

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
            'documentosBloqueados' => $documentos !== null,
            'formatoArticulo14Url' => $this->ofreceFormatoArticulo14($solicitud)
                ? route('registro.documentos.formato-articulo-14')
                : null,
        ]);
    }

    public function store(Request $request)
    {
        $resultado = $this->verificarSolicitud($request);

        if (!$resultado instanceof SolicitudCertificacion) {
            return $resultado;
        }

        $solicitud = $resultado;
        $actual = $solicitud->documentosProbatorios;

        if ($actual !== null) {
            return redirect()
                ->route('registro.documentos')
                ->with(
                    'error',
                    'Los documentos probatorios ya fueron guardados. Solo pueden consultarse.'
                );
        }

        $esArticulo14 = $this->esArticulo14($solicitud);

        $camposDocumentos = $esArticulo14
            ? array_merge(self::NUEVOS, self::ARTICULO_14)
            : self::NUEVOS;

        $reglas = [
            'link' => $esArticulo14
                ? ['exclude']
                : ['required', 'url:http,https', 'max:300'],
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

        $rutasCreadas = [];

        try {
            DB::transaction(function () use ($solicitud, $datos, $camposDocumentos, $request, &$rutasCreadas) {
                $solicitudBloqueada = $this->tramites->bloquear($solicitud->id);

                if (!$solicitudBloqueada || $solicitudBloqueada->estado !== 'borrador') {
                    throw new \RuntimeException('La solicitud ya no admite modificaciones.');
                }

                if ($this->documentosPdm->probatorios($solicitudBloqueada->id) !== null) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'documentos' =>
                            'Los documentos ya están guardados y no pueden modificarse.',
                    ]);
                }

                foreach ($camposDocumentos as $campo => $nombre) {
                    if ($request->hasFile($campo)) {
                        $rutasCreadas[] = $this->documentosPdm->guardar($solicitudBloqueada->id, $campo, $request->file($campo));
                    }
                }
                if (filled($datos['link'] ?? null)) {
                    $this->documentosPdm->guardarEnlace($solicitudBloqueada->id, $datos['link']);
                }

                // Evento «Documentos probatorios registrados» (ESTADO_SOLICITUD).
                $this->tramites->registrarEvento($solicitudBloqueada->id, 'DP');
            });
        } catch (\Throwable $e) {
            foreach ($rutasCreadas as $ruta) {
                $this->documentosPdm->disco()->delete($ruta);
            }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                throw $e;
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

    /**
     * Descarga del formato oficial de refrendo por artículo 14.
     * Requiere sesión, datos autorizados (middleware) y una solicitud activa
     * propia que sea refrendo por artículo 14; en cualquier otro caso, 404.
     * Se entrega el archivo original sin transformarlo.
     */
    public function formatoArticulo14(Request $request)
    {
        $resultado = $this->verificarSolicitud($request);

        if (
            !$resultado instanceof SolicitudCertificacion
            || !$this->ofreceFormatoArticulo14($resultado)
        ) {
            abort(404);
        }

        $ruta = resource_path(self::FORMATO_ARTICULO_14);

        abort_unless(is_file($ruta), 404);

        return response()->download(
            $ruta,
            self::FORMATO_ARTICULO_14_NOMBRE,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    public function ver(Request $request, string $campo)
    {
        $resultado = $this->verificarSolicitud($request);

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
            empty($ruta) || !$this->documentosPdm->disco()->exists($ruta),
            404
        );

        return $this->documentosPdm->disco()->download($ruta, $campo.'.'.pathinfo($ruta, PATHINFO_EXTENSION));
    }
}
