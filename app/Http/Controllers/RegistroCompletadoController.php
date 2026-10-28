<?php

namespace App\Http\Controllers;

use App\Models\DatosSolicitante;
use App\Models\SeleccionCertificacion;
use App\Models\FormacionAcademica;
use App\Models\DocumentoProbatorio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RegistroCompletadoController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $datos = DatosSolicitante::where('user_id', $userId)
            ->first();

        $certificacion = SeleccionCertificacion::where(
            'user_id', $userId
        )->first();

        $formacion = FormacionAcademica::where(
            'user_id', $userId
        )->first();

        $documentos = DocumentoProbatorio::where(
            'user_id', $userId
        )->first();

        $camposAcademicos = [
            'titulo_origen_archivo',
            'cedula_origen_archivo',
            'titulo_ultimo_grado_archivo',
            'cedula_ultimo_grado_archivo',
        ];

        $camposProbatorios = [
            'constancia_antiguedad_archivo',
            'comprobante_pago_archivo',
        ];

        $academicosCompletos = $formacion !== null;

        foreach ($camposAcademicos as $campo) {
            $ruta = $formacion?->{$campo};

            if (!$ruta || !Storage::disk('local')->exists($ruta)) {
                $academicosCompletos = false;
                break;
            }
        }

        $probatoriosCompletos = $documentos !== null;

        foreach ($camposProbatorios as $campo) {
            $ruta = $documentos?->{$campo};

            if (!$ruta || !Storage::disk('local')->exists($ruta)) {
                $probatoriosCompletos = false;
                break;
            }
        }

        $registroCompleto =
            $datos !== null &&
            $certificacion !== null &&
            $academicosCompletos &&
            $probatoriosCompletos;

        return view('registro.completado', [
            'registroCompleto' => $registroCompleto,
        ]);
    }
}
