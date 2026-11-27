<?php

namespace App\Http\Controllers;

use App\Models\DatosSolicitante;
use App\Models\Pdm\Usuario;
use App\Services\Pdm\Solicitantes\ConsultaExpedientesPdm;
use App\Models\SeleccionCertificacion;
use App\Models\FormacionAcademicaSolicitud;
use App\Models\SolicitudCertificacion;
use Illuminate\Http\Request;

class PanelController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user() instanceof Usuario) {
            $consulta = app(ConsultaExpedientesPdm::class);
            $expedientesPdm = $consulta->consulta()
                ->where('soli_id_persona', $request->user()->usua_id_persona)
                ->orderByDesc('soli_id_solicitud')->paginate(20);

            return view('panel', compact('expedientesPdm'));
        }

        $userId = $request->user()->id;

        $datosCompletos = DatosSolicitante::where(
            'user_id', $userId
        )->exists();

        $certificacionCompleta = SeleccionCertificacion::where(
            'user_id', $userId
        )->exists();

        // Formación del trámite actual (formaciones_academicas_solicitud); la tabla antigua
        // formaciones_academicas ya no se escribe y no existe en el modelo PowerDesigner.
        $formacionCompleta = FormacionAcademicaSolicitud::whereIn(
            'solicitud_id',
            SolicitudCertificacion::where('user_id', $userId)->select('id')
        )->whereNotNull('formacion_completada_at')->exists();

        $etapas = [
	    [
	        'nombre' => 'Datos Personales',
	        'descripcion' => 'Información personal, domicilio y contacto.',
	        'completa' => $datosCompletos,
	        'ruta' => $datosCompletos
	            ? 'mis-datos.edit'
	            : 'registro.datos',
	    ],
	    [
	        'nombre' => 'Selección de Certificación',
	        'descripcion' => 'Certificación, tipo de personal y área académica.',
	        'completa' => $certificacionCompleta,
	        'ruta' => 'registro.certificacion',
	    ],
	    [
	        'nombre' => 'Formación Académica',
	        'descripcion' => 'Grados académicos, documentos e institución.',
	        'completa' => $formacionCompleta,
	        'ruta' => 'registro.formacion',
	    ],
	];
        $completadas = collect($etapas)
            ->where('completa', true)
            ->count();

        $porcentaje = (int) round(
            ($completadas / count($etapas)) * 100
        );

        return view('panel', compact(
            'etapas',
            'completadas',
            'porcentaje'
        ));
    }
}
