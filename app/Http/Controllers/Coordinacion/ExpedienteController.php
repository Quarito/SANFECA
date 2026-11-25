<?php

namespace App\Http\Controllers\Coordinacion;

use App\Http\Controllers\Controller;
use App\Models\SolicitudCertificacion;
use App\Services\Coordinacion\ConsultaSolicitudes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Consulta básica (solo lectura) del expediente desde la coordinación.
 * Fuera de alcance o inexistente → 404 (no revela si el registro existe).
 * No hay operaciones de escritura sobre la solicitud.
 */
class ExpedienteController extends Controller
{
    /** Documentos permitidos: campo => [origen, etiqueta]. */
    public const DOCUMENTOS = [
        'titulo_origen_archivo' => ['formacion', 'Título de licenciatura'],
        'cedula_origen_archivo' => ['formacion', 'Cédula de licenciatura'],
        'titulo_ultimo_grado_archivo' => ['formacion', 'Título del último grado'],
        'cedula_ultimo_grado_archivo' => ['formacion', 'Cédula del último grado'],
        'constancia_antiguedad_archivo' => ['probatorios', 'Constancia de antigüedad'],
        'comprobante_pago_archivo' => ['probatorios', 'Comprobante de pago'],
        'solicitud_articulo_14_archivo' => ['probatorios', 'Solicitud por artículo'],
        'constancia_articulo_14_archivo' => ['probatorios', 'Constancia probatoria de artículo'],
    ];

    public function __construct(private readonly ConsultaSolicitudes $consulta)
    {
    }

    private function solicitudAutorizada(Request $request, int $id, string $habilidad): SolicitudCertificacion
    {
        $solicitud = SolicitudCertificacion::find($id);

        abort_if(
            $solicitud === null || Gate::forUser($request->user())->denies($habilidad, $solicitud),
            404
        );

        return $solicitud;
    }

    public function show(Request $request, int $solicitud)
    {
        $modelo = $this->solicitudAutorizada($request, $solicitud, 'consultarComoCoordinacion');
        $detalle = $this->consulta->detalle($request->user(), $modelo->id);
        abort_if($detalle === null, 404);

        $refrendo = $modelo->datosRefrendo;
        $aplicaArticulo = $this->aplicaArticulo13o14($modelo);

        // Documentos aplicables al trámite (orden de la cuadrícula) y los que no aplican.
        // Estado de CARGA únicamente: la validación documental es una fase posterior.
        $documentos = [];
        $noAplican = [];
        foreach (self::DOCUMENTOS as $campo => [$origen, $etiqueta]) {
            $ruta = $this->ruta($modelo, $campo);
            $esArticulo = str_contains($campo, 'articulo_14');

            if ($esArticulo && !$aplicaArticulo && $ruta === null) {
                $noAplican[] = $etiqueta;
                continue;
            }

            $documentos[] = [
                'campo' => $campo,
                'etiqueta' => $etiqueta,
                'estado' => match (true) {
                    $ruta === null => 'no-cargado',
                    Storage::disk('local')->exists($ruta) => 'cargado',
                    default => 'no-localizado',
                },
            ];
        }

        // Principales (formación y probatorios comunes) y adicionales del artículo 13/14.
        [$adicionales, $principales] = collect($documentos)
            ->partition(fn ($d) => str_contains($d['campo'], 'articulo_14'))
            ->map(fn ($c) => $c->values()->all())
            ->all();

        return view('coordinacion.expediente', [
            'detalle' => $detalle,
            'refrendo' => $refrendo,
            'aplicaArticulo' => $aplicaArticulo,
            'principales' => $principales,
            'adicionales' => $adicionales,
            'noAplican' => $noAplican,
        ]);
    }

    public function documento(Request $request, int $solicitud, string $campo)
    {
        abort_unless(array_key_exists($campo, self::DOCUMENTOS), 404);
        $modelo = $this->solicitudAutorizada($request, $solicitud, 'descargarDocumentoComoCoordinacion');

        $ruta = $this->ruta($modelo, $campo);
        abort_if(
            $ruta === null
            || str_contains($ruta, '..')
            || !str_starts_with($ruta, 'anfeca/')
            || !Storage::disk('local')->exists($ruta),
            404
        );

        return Storage::disk('local')->response($ruta, basename($ruta), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Mismo criterio que Documentos probatorios del solicitante (refrendo, art. 13.1 o 14.x). */
    private function aplicaArticulo13o14(SolicitudCertificacion $solicitud): bool
    {
        $datos = $solicitud->datosRefrendo;

        return $solicitud->tipo_tramite === 'refrendo'
            && $datos !== null
            && (int) $datos->aplica_articulo_14 === 1
            && in_array((string) $datos->inciso_articulo_14, ['13.1', '14', '14.1', '14.2', '14.3'], true);
    }

    private function ruta(SolicitudCertificacion $solicitud, string $campo): ?string
    {
        [$origen] = self::DOCUMENTOS[$campo];
        $registro = $origen === 'formacion' ? $solicitud->formacionAcademica : $solicitud->documentosProbatorios;
        $ruta = $registro?->{$campo};

        return filled($ruta) ? (string) $ruta : null;
    }
}
