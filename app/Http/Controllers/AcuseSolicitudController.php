<?php

namespace App\Http\Controllers;

use App\Models\SolicitudCertificacion;
use App\Services\AcusePdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class AcuseSolicitudController extends Controller
{
    public function descargar(Request $request, int $solicitud, AcusePdfService $pdf): Response|RedirectResponse
    {
        $registro = SolicitudCertificacion::where('user_id', $request->user()->id)
            ->with('acuse')->findOrFail($solicitud);

        abort_unless($registro->fecha_envio !== null && $registro->estado !== 'borrador', 404);

        if (! $registro->acuse) {
            return redirect()->route('panel')->with('error',
                'Esta solicitud se envió antes de habilitar los acuses y no cuenta con un acuse disponible.');
        }

        try {
            $contenido = $pdf->generar($registro->acuse);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('panel')->with('error',
                'No fue posible generar el PDF. Su solicitud sigue registrada; intente descargar el acuse nuevamente.');
        }

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="acuse-solicitud-'.$registro->id.'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
