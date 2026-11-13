<?php

namespace App\Services;

use App\Models\AcuseSolicitud;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

class AcusePdfService
{
    public function generar(AcuseSolicitud $acuse): string
    {
        if ($acuse->version !== 1) {
            throw new RuntimeException('Versión de acuse no soportada.');
        }

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot(public_path('images'));

        $pdf = new Dompdf($options);
        $pdf->setPaper('letter');
        $pdf->loadHtml(view('acuses.v1', ['datos' => $acuse->datos, 'logo' => $this->logo()])->render(), 'UTF-8');
        $pdf->render();

        $contenido = $pdf->output();
        unset($pdf);
        gc_collect_cycles();

        return $contenido;
    }

    private function logo(): string
    {
        $original = imagecreatefrompng(public_path('images/logo-certificacion.png'));
        if ($original === false) {
            throw new RuntimeException('No se pudo leer el logotipo institucional.');
        }
        $reducido = imagescale($original, 380);
        unset($original);
        if ($reducido === false) {
            throw new RuntimeException('No se pudo preparar el logotipo institucional.');
        }
        ob_start();
        try {
            imagepng($reducido);

            return 'data:image/png;base64,'.base64_encode(ob_get_contents());
        } finally {
            ob_end_clean();
            unset($reducido);
        }
    }
}
