<?php

namespace App\Http\Controllers;

use App\Models\DatosSolicitante;
use App\Models\SolicitudCertificacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AcuseRecepcionController extends Controller
{
    public function descargar(Request $request, int $solicitudId)
    {
        $solicitud = SolicitudCertificacion::query()
            ->whereKey($solicitudId)
            ->where('user_id', $request->user()->id)
            ->where('estado', 'enviado')
            ->firstOrFail();

        $solicitud->load([
            'formacionAcademica.institucionAfiliada',
            'documentosProbatorios',
            'datosRefrendo',
        ]);

        $datos = DatosSolicitante::where(
            'user_id',
            $solicitud->user_id
        )->first();

        $formacion = $solicitud->formacionAcademica;
        $institucion = $formacion?->institucionAfiliada;
        $documentos = $solicitud->documentosProbatorios;

        $archivos = [
            'Título de origen' => $formacion?->titulo_origen_archivo,
            'Cédula de origen' => $formacion?->cedula_origen_archivo,
            'Título del último grado' => $formacion?->titulo_ultimo_grado_archivo,
            'Cédula del último grado' => $formacion?->cedula_ultimo_grado_archivo,
            'Constancia de antigüedad' => $documentos?->constancia_antiguedad_archivo,
            'Comprobante de pago' => $documentos?->comprobante_pago_archivo,
        ];

        // Solo licenciatura: el último grado es la licenciatura; no se listan documentos de posgrado.
        if (\App\Services\Formacion\RequisitosFormacion::soloLicenciatura($formacion)) {
            unset($archivos['Título del último grado'], $archivos['Cédula del último grado']);
        }

        $refrendo = $solicitud->datosRefrendo;

        $esArticulo14 = $solicitud->tipo_tramite === 'refrendo'
            && $refrendo !== null
            && (int) $refrendo->aplica_articulo_14 === 1
            && in_array(
                $refrendo->inciso_articulo_14,
                ['13.1', '14', '14.1', '14.2', '14.3'],
                true
            );

        if ($esArticulo14) {
            $archivos['Solicitud artículo 14'] =
                $documentos?->solicitud_articulo_14_archivo;

            $archivos['Constancia artículo 14'] =
                $documentos?->constancia_articulo_14_archivo;
        }

        $documentosRecibidos = [];

        foreach ($archivos as $nombre => $ruta) {
            if ($ruta && Storage::disk('local')->exists($ruta)) {
                $documentosRecibidos[] = $nombre;
            }
        }

        $fechaRecepcion = $solicitud->fecha_envio
            ? $solicitud->fecha_envio
                ->copy()
                ->timezone('America/Mexico_City')
                ->format('d/m/Y H:i:s')
            : 'No registrada';

        $pdf = Pdf::loadView('registro.acuse-recepcion', [
            'solicitud' => $solicitud,
            'datos' => $datos,
                'formacion' => $formacion,
            'institucion' => $institucion,
            'documentosRecibidos' => $documentosRecibidos,
            'fechaRecepcion' => $fechaRecepcion,
        ])->setPaper('letter', 'portrait');

        return $pdf->download(
            'acuse_anfeca_' . $solicitud->id . '.pdf'
        );
    }
}
