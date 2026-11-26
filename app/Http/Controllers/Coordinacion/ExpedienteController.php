<?php

namespace App\Http\Controllers\Coordinacion;

use App\Http\Controllers\Controller;
use App\Models\SolicitudCertificacion;
use App\Services\Coordinacion\ConsultaSolicitudes;
use App\Services\Coordinacion\DocumentosAplicables;
use App\Services\Coordinacion\RevisionDocumental;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Consulta del expediente desde la coordinación (solo lectura de la solicitud y sus documentos).
 * Fuera de alcance o inexistente → 404 (no revela si el registro existe).
 * Con ANFECA_COORDINACION_REVISION=true añade el estado de revisión documental (fase 6.3.1).
 */
class ExpedienteController extends Controller
{
    /** Documentos permitidos: campo => [origen, etiqueta]. Definición única en DocumentosAplicables. */
    public const DOCUMENTOS = DocumentosAplicables::DOCUMENTOS;

    private const ETIQUETA_CARGA = ['cargado' => 'cargado', 'no_cargado' => 'no-cargado', 'no_localizado' => 'no-localizado'];

    public function __construct(
        private readonly ConsultaSolicitudes $consulta,
        private readonly DocumentosAplicables $documentos,
    ) {
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

        $revisionActiva = config('anfeca_coordinacion.revision_documental') === true;
        $revision = $revisionActiva ? app(RevisionDocumental::class)->resumen($modelo) : null;
        $aplicaArticulo = $this->documentos->aplicaArticulo13o14($modelo);

        // Documentos aplicables al trámite (orden de la cuadrícula) y los que no aplican.
        $documentos = [];
        $noAplican = [];
        foreach (self::DOCUMENTOS as $campo => [, $etiqueta]) {
            $ruta = $this->documentos->ruta($modelo, $campo);

            if (DocumentosAplicables::esArticulo($campo) && !$aplicaArticulo && $ruta === null) {
                $noAplican[] = $etiqueta;
                continue;
            }
            // Solo licenciatura: los documentos del último grado no aplican (la licenciatura ya está en origen).
            if (!\App\Services\Formacion\RequisitosFormacion::documentoAplica($modelo->formacionAcademica, $campo)) {
                $noAplican[] = $etiqueta.' (solo licenciatura)';
                continue;
            }

            $doc = [
                'campo' => $campo,
                'etiqueta' => $etiqueta,
                'estado' => self::ETIQUETA_CARGA[$this->documentos->estadoCarga($ruta)],
            ];
            if ($revision !== null) {
                $doc['revision'] = $revision['documentos'][$campo];
            }
            $documentos[] = $doc;
        }

        // Principales (formación y probatorios comunes) y adicionales del artículo 13/14.
        [$adicionales, $principales] = collect($documentos)
            ->partition(fn ($d) => DocumentosAplicables::esArticulo($d['campo']))
            ->map(fn ($c) => $c->values()->all())
            ->all();

        $zona = app(\App\Services\Zonas\ZonasAnfeca::class)->deInstitucion($detalle->institucion_id !== null ? (int) $detalle->institucion_id : null);

        return view('coordinacion.expediente', [
            'detalle' => $detalle,
            'zona' => $zona,
            // Zona HISTÓRICA del certificado (refrendo): dato del certificado previo, distinto de la
            // zona de la institución del trámite actual.
            'zonaHistorica' => $modelo->tipo_tramite === 'refrendo' ? ($modelo->certificadoHistorico?->zona ?: null) : null,
            'refrendo' => $modelo->datosRefrendo,
            'aplicaArticulo' => $aplicaArticulo,
            'principales' => $principales,
            'adicionales' => $adicionales,
            'noAplican' => $noAplican,
            'revision' => $revision,
            'solicitudEnviada' => $modelo->estado === 'enviado',
        ]);
    }

    public function documento(Request $request, int $solicitud, string $campo)
    {
        abort_unless(DocumentosAplicables::existe($campo), 404);
        $modelo = $this->solicitudAutorizada($request, $solicitud, 'descargarDocumentoComoCoordinacion');

        // Seguimiento previo al envío: la coordinación NO consulta ni descarga documentos de borradores.
        abort_unless($modelo->estado === 'enviado', 403, 'Los documentos de solicitudes no enviadas no pueden consultarse.');

        $ruta = $this->documentos->ruta($modelo, $campo);
        abort_if(
            $ruta === null
            || !$this->documentos->rutaSegura($ruta)
            || !Storage::disk('local')->exists($ruta),
            404
        );

        // «Ver» desde la revisión documental lleva ?v=<token de versión>: si el archivo cambió, 409.
        if ($request->filled('v')) {
            $actual = $this->documentos->version($modelo, $campo)->token();
            abort_unless(hash_equals($actual, (string) $request->query('v')), 409,
                'El archivo cambió desde que se abrió el expediente. Actualice la página.');
        }

        // Visualización en línea (visor modal) o descarga opcional; nunca se expone la ruta física.
        $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) ?: 'pdf';
        $nombre = "solicitud-{$modelo->id}-{$campo}.{$extension}";
        $disposicion = $request->boolean('descargar') ? 'attachment' : 'inline';
        $tipo = match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => Storage::disk('local')->mimeType($ruta) ?: 'application/octet-stream',
        };

        return Storage::disk('local')->response($ruta, $nombre, [
            'Content-Type' => $tipo,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
        ], $disposicion);
    }
}
