<?php

namespace App\Http\Controllers;

use App\Models\SeleccionCertificacion;
use App\Services\ConsultaAntecedentesService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeleccionCertificacionController extends Controller
{
    public function create(
    Request $request,
    ConsultaAntecedentesService $consultaAntecedentes
) {
    $seleccion = SeleccionCertificacion::where(
        'user_id',
        $request->user()->id
    )->first();

    $resultadoAntecedentes = $consultaAntecedentes->consultar(
        $request->user()->id
    );

    return view('registro.certificacion', [
        'seleccion' => $seleccion,
    ]);
}

    public function store(Request $request)
    {
        $datos = $request->validate([
            'certificacion' => [
                'required',
                Rule::in(['ANFECA', 'CACSLA', 'CACECA']),
            ],
            'tipo_personal' => [
                'required', 'string', 'max:100',
            ],
            'area_certificacion' => [
                'required', 'string', 'max:150',
            ],
        ]);

        SeleccionCertificacion::updateOrCreate(
            ['user_id' => $request->user()->id],
            $datos
        );

        return redirect()
            ->route('registro.formacion')
            ->with('success', 'Selección guardada correctamente.');
    }
}

