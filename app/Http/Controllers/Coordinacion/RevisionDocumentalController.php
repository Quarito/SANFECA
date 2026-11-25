<?php

namespace App\Http\Controllers\Coordinacion;

use App\Exceptions\Coordinacion\ConflictoRevision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coordinacion\RegistrarRevisionRequest;
use App\Models\SolicitudCertificacion;
use App\Services\Coordinacion\DocumentosAplicables;
use App\Services\Coordinacion\RevisionDocumental;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Revisión documental (fase 6.3.1). Solo lectura del expediente y SOLO INSERCIÓN de revisiones.
 * No existen rutas de actualización ni eliminación del historial.
 * Desactivada (404) mientras ANFECA_COORDINACION_REVISION no sea true.
 */
class RevisionDocumentalController extends Controller
{
    public function __construct(
        private readonly RevisionDocumental $revision,
        private readonly DocumentosAplicables $documentos,
    ) {
    }

    /** GET /coordinacion/solicitudes/{s}/revision — estado por documento e indicadores. */
    public function resumen(Request $request, int $solicitud): JsonResponse
    {
        $modelo = $this->autorizada($request, $solicitud);

        return response()->json(['folio' => $modelo->id, 'solicitud_estado' => $modelo->estado] + $this->revision->resumen($modelo));
    }

    /** GET /coordinacion/solicitudes/{s}/documentos/{campo}/revisiones — versión, vigente e historial. */
    public function historial(Request $request, int $solicitud, string $campo): JsonResponse
    {
        $modelo = $this->autorizada($request, $solicitud);
        $this->documentoAplicable($modelo, $campo);

        return response()->json($this->revision->detalle($modelo, $campo) + [
            // Clave nueva por cada apertura del formulario; el navegador la reutiliza en los reintentos.
            'clave_idempotencia' => (string) Str::uuid(),
            'coordinador' => ['nombre' => $request->user()->name, 'rol' => $request->user()->rol],
            'texto_maximo' => (int) config('anfeca_coordinacion.revision_texto_maximo'),
            'texto_minimo' => (int) config('anfeca_coordinacion.revision_texto_minimo'),
        ]);
    }

    /** POST /coordinacion/solicitudes/{s}/documentos/{campo}/revisiones — inserta una revisión. */
    public function registrar(Request $request, int $solicitud, string $campo): JsonResponse
    {
        // Primero la autorización (404 sin revelar nada) y después la validación del formulario (422).
        $modelo = $this->autorizada($request, $solicitud);
        $this->documentoAplicable($modelo, $campo);
        $datos = app(RegistrarRevisionRequest::class)->validated();

        try {
            [$revision, $creada] = $this->revision->registrar($request->user(), $modelo, $campo, $datos);
        } catch (ConflictoRevision $e) {
            return response()->json(['message' => $e->getMessage(), 'motivo' => $e->motivo] + $e->contexto, 409);
        }

        return response()->json([
            'revision' => $this->revision->publica($revision),
            'reintento' => !$creada,
            'resumen' => $this->revision->resumen($modelo->fresh()),
        ], $creada ? 201 : 200);
    }

    /** Autorización en CADA petición (incluidos los reintentos): fuera de alcance o inexistente → 404. */
    private function autorizada(Request $request, int $id): SolicitudCertificacion
    {
        abort_unless(config('anfeca_coordinacion.revision_documental') === true, 404);
        $solicitud = SolicitudCertificacion::find($id);
        abort_if($solicitud === null || Gate::forUser($request->user())->denies('revisarDocumentoComoCoordinacion', $solicitud), 404);

        return $solicitud;
    }

    /** «No aplica» no se puede revisar: lo determinan las reglas del trámite. */
    private function documentoAplicable(SolicitudCertificacion $solicitud, string $campo): void
    {
        abort_unless($this->documentos->aplica($solicitud, $campo), 404);
    }
}
