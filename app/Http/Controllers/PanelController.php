<?php

namespace App\Http\Controllers;

use App\Models\DatosSolicitante;
use App\Models\SeleccionCertificacion;
use App\Models\FormacionAcademica;
use Illuminate\Http\Request;

class PanelController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $datosCompletos = DatosSolicitante::where(
            'user_id', $userId
        )->exists();

        $certificacionCompleta = SeleccionCertificacion::where(
            'user_id', $userId
        )->exists();

        $formacionCompleta = FormacionAcademica::where(
            'user_id', $userId
        )->exists();

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
