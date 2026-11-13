<?php

namespace App\Services;

use App\Models\AcuseSolicitud;
use App\Models\DatosSolicitante;
use App\Models\SeleccionCertificacion;
use App\Models\SolicitudCertificacion;
use Illuminate\Support\Facades\Storage;
use LogicException;

class AcuseSolicitudService
{
    /** Se invoca dentro de la transacción de envío, con la solicitud bloqueada. */
    public function registrar(SolicitudCertificacion $solicitud): AcuseSolicitud
    {
        if ($existente = $solicitud->acuse()->first()) {
            return $existente;
        }

        if ($solicitud->estado !== 'enviado' || ! $solicitud->fecha_envio) {
            throw new LogicException('El acuse requiere una solicitud enviada.');
        }

        $persona = DatosSolicitante::where('user_id', $solicitud->user_id)->firstOrFail();
        $seleccion = SeleccionCertificacion::where('user_id', $solicitud->user_id)
            ->where('certificacion', $solicitud->certificacion)->firstOrFail();
        $solicitud->load(['formacionAcademica.institucionAfiliada', 'datosRefrendo', 'certificadoHistorico', 'documentosProbatorios']);
        $formacion = $solicitud->formacionAcademica;
        $institucion = $formacion?->institucionAfiliada;
        $refrendo = $solicitud->datosRefrendo;
        $omiteFormacion = $solicitud->tipo_tramite === 'refrendo'
            && $refrendo?->aplica_articulo_14
            && in_array($refrendo->inciso_articulo_14, ['14.1', '14.2', '14.3', '14.4'], true);

        $documentos = [];
        foreach ([
            'titulo_origen_archivo' => ['Título profesional', $formacion, ! $omiteFormacion],
            'cedula_origen_archivo' => ['Cédula profesional', $formacion, ! $omiteFormacion],
            'titulo_ultimo_grado_archivo' => ['Título de último grado', $formacion, ! $omiteFormacion],
            'cedula_ultimo_grado_archivo' => ['Cédula de último grado', $formacion, ! $omiteFormacion],
            'constancia_antiguedad_archivo' => ['Constancia de antigüedad', $solicitud->documentosProbatorios, true],
            'comprobante_pago_archivo' => ['Comprobante de pago', $solicitud->documentosProbatorios, true],
        ] as $campo => [$etiqueta, $registro, $requerido]) {
            $ruta = $registro?->{$campo};
            $documentos[$campo] = [
                'nombre' => $etiqueta,
                'adjuntado' => filled($ruta) && Storage::disk('local')->exists($ruta),
                'requerido' => (bool) $requerido,
            ];
        }

        return $solicitud->acuse()->create([
            'version' => 1,
            'datos' => [
                'folio' => (string) $solicitud->id,
                'fecha_recepcion' => $solicitud->fecha_envio->copy()
                    ->timezone('America/Mexico_City')->format('d/m/Y H:i:s'),
                'zona_horaria' => 'America/Mexico_City',
                'certificacion' => $solicitud->certificacion,
                'tipo_tramite' => $solicitud->tipo_tramite,
                'tipo_personal' => $seleccion->tipo_personal,
                'area_certificacion' => $seleccion->area_certificacion,
                'persona' => $persona->only([
                    'nombres', 'apellido_paterno', 'apellido_materno', 'curp',
                    'correo_electronico', 'telefono_particular', 'telefono_celular',
                ]),
                'omite_formacion' => (bool) $omiteFormacion,
                'documentos' => $documentos,
                'formacion' => $formacion?->only([
                    'nivel_origen', 'titulo_origen', 'nivel_ultimo_grado', 'titulo_ultimo_grado',
                ]),
                'institucion' => $institucion?->only([
                    'numero_afiliacion', 'nombre', 'campus', 'unidad', 'sede', 'zona_anfeca',
                ]),
                'refrendo' => $solicitud->tipo_tramite === 'refrendo' ? [
                    'numero_certificado' => $solicitud->certificadoHistorico?->numero_certificado,
                    'refrendo_actual' => $refrendo?->refrendo_actual,
                    'aplica_articulo_14' => (bool) $refrendo?->aplica_articulo_14,
                    'inciso_articulo_14' => $refrendo?->inciso_articulo_14,
                ] : null,
            ],
        ]);
    }
}
