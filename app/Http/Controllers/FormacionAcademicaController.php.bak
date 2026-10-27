<?php

namespace App\Http\Controllers;

use App\Models\FormacionAcademica;
use App\Models\InstitucionAfiliada;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FormacionAcademicaController extends Controller
{
    public function create(Request $request)
    {
        $formacion = FormacionAcademica::where(
            'user_id',
            $request->user()->id
        )->first();

        return view('registro.formacion-academica', [
            'formacion' => $formacion,
            'instituciones' => InstitucionAfiliada::orderBy(
                'numero_afiliacion'
            )->get(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nivel_origen' => [
                'required',
                Rule::in(['Licenciatura', 'Especialidad', 'Maestría', 'Doctorado']),
            ],
            'titulo_origen' => ['required', 'string', 'max:200'],
            'nivel_ultimo_grado' => [
                'required',
                Rule::in(['Licenciatura', 'Especialidad', 'Maestría', 'Doctorado']),
            ],
            'titulo_ultimo_grado' => ['required', 'string', 'max:200'],
            'institucion_afiliada_id' => [
                'required',
                'exists:instituciones_afiliadas,id',
            ],
        ]);

        FormacionAcademica::updateOrCreate(
            ['user_id' => $request->user()->id],
            $datos
        );

        return redirect()
            ->route('registro.formacion')
            ->with('success', 'Formación académica guardada correctamente.');
    }
}

