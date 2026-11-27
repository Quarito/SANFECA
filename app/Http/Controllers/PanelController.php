<?php

namespace App\Http\Controllers;

use App\Services\Anfeca\Tramites;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PanelController extends Controller
{
    public function index(Request $request, Tramites $tramites)
    {
        $persona = (int) $request->user()->usua_id_persona;

        // Etapas calculadas desde PERSONA, SOLICITUD y ESTADO_SOLICITUD (36 tablas oficiales).
        $datosCompletos = DB::table('persona')->where('pers_id_persona', $persona)->exists();
        $solicitudes = DB::table('solicitud')->where('soli_id_persona', $persona)->pluck('soli_id_solicitud');
        $certificacionCompleta = $solicitudes->isNotEmpty();
        $formacionCompleta = DB::table('estado_solicitud')->whereIn('esso_id_solicitud', $solicitudes)->where('esso_siglas', 'FA')->exists();

        $etapas = [
            [
                'nombre' => 'Datos Personales',
                'descripcion' => 'Información personal, domicilio y contacto.',
                'completa' => $datosCompletos,
                'ruta' => $datosCompletos ? 'mis-datos.edit' : 'registro.datos',
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

        $completadas = collect($etapas)->where('completa', true)->count();
        $porcentaje = (int) round(($completadas / count($etapas)) * 100);

        return view('panel', compact('etapas', 'completadas', 'porcentaje'));
    }
}
