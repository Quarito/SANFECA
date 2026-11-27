<?php

namespace App\Http\Controllers\Coordinacion;

use App\Http\Controllers\Controller;
use App\Models\Pdm\Solicitud;
use App\Services\Pdm\Solicitantes\ConsultaExpedientesPdm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class ExpedientePdmController extends Controller
{
    public function __construct(private readonly ConsultaExpedientesPdm $consulta) {}

    public function show(int $id)
    {
        abort_unless($id > 0 && $id <= 2147483647, 404);
        $solicitud = $this->consulta->consulta()
            ->with('solicitudDocumentos.documento')->findOrFail($id);

        return view('coordinacion.expediente-pdm', [
            'solicitud' => $solicitud,
            'fechaEstado' => $this->consulta->fechaEstado($solicitud),
            'pagoDisponible' => $this->consulta->estado($solicitud) === 'enviado' && $this->rutaPago($solicitud) !== null,
        ]);
    }

    public function documento(Request $request, int $id, string $campo)
    {
        abort_unless($id > 0 && $id <= 2147483647, 404);
        abort_unless($campo === 'comprobante_pago_archivo' && ! $request->filled('v'), 404);
        $solicitud = $this->consulta->consulta()->findOrFail($id);
        abort_unless($this->consulta->estado($solicitud) === 'enviado', 403);
        $ruta = $this->rutaPago($solicitud);
        abort_if($ruta === null, 404);
        $disco = Storage::disk('local');
        $tipo = $disco->mimeType($ruta);
        $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$tipo] ?? null;
        abort_if($extension === null, 404);

        return $disco->response($ruta, "solicitud-{$id}-comprobante.{$extension}", [
            'Content-Type' => $tipo,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], $request->boolean('descargar') ? 'attachment' : 'inline');
    }

    private function rutaPago(Solicitud $solicitud): ?string
    {
        $pagos = $solicitud->pagos()->limit(2)->pluck('pago_url_comprobante');
        $prefijo = "anfeca/documentos/solicitudes/{$solicitud->getKey()}/";
        $ruta = $pagos->count() === 1 ? $pagos->first() : null;

        return $ruta && str_starts_with($ruta, $prefijo)
            && preg_match('/^[a-zA-Z0-9_-]+\.[a-zA-Z0-9]+$/D', substr($ruta, strlen($prefijo)))
            && Storage::disk('local')->exists($ruta) ? $ruta : null;
    }
}
