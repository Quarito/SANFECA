<?php

namespace App\Http\Controllers;

use App\Models\DocumentoProbatorio;
use App\Models\FormacionAcademica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentoProbatorioController extends Controller
{
    private const ACADEMICOS = [
        'titulo_origen_archivo' => 'Título de Licenciatura',
        'cedula_origen_archivo' => 'Cédula de Licenciatura',
        'titulo_ultimo_grado_archivo' => 'Título de último grado',
        'cedula_ultimo_grado_archivo' => 'Cédula de último grado',
    ];

    private const NUEVOS = [
        'constancia_antiguedad_archivo' => 'Constancia de Antigüedad',
        'comprobante_pago_archivo' => 'Comprobante de Pago',
    ];

    public function create(Request $request)
    {
        $userId = $request->user()->id;

        $formacion = FormacionAcademica::where('user_id', $userId)
            ->first();

        $documentos = DocumentoProbatorio::where('user_id', $userId)
            ->first();

        $academicos = [];

        foreach (self::ACADEMICOS as $campo => $nombre) {
            $ruta = $formacion?->{$campo};

            $academicos[$campo] = [
                'nombre' => $nombre,
                'disponible' => !empty($ruta)
                    && Storage::disk('local')->exists($ruta),
                'url' => !empty($ruta)
                    && Storage::disk('local')->exists($ruta)
                    ? route('registro.documentos.ver', $campo)
                    : null,
            ];
        }

        $nuevos = [];

        foreach (self::NUEVOS as $campo => $nombre) {
            $ruta = $documentos?->{$campo};

            $nuevos[$campo] = [
                'nombre' => $nombre,
                'disponible' => !empty($ruta)
                    && Storage::disk('local')->exists($ruta),
                'url' => !empty($ruta)
                    && Storage::disk('local')->exists($ruta)
                    ? route('registro.documentos.ver', $campo)
                    : null,
            ];
        }

        return view('registro.documentos-probatorios', [
            'academicos' => $academicos,
            'nuevos' => $nuevos,
            'link' => old('link', $documentos?->link ?? ''),
        ]);
    }

    public function store(Request $request)
    {
        $userId = $request->user()->id;

        $actual = DocumentoProbatorio::where('user_id', $userId)
            ->first();

        $reglas = [
            'link' => ['nullable', 'url:http,https', 'max:2048'],
        ];

        foreach (self::NUEVOS as $campo => $nombre) {
            $reglas[$campo] = [
                empty($actual?->{$campo}) ? 'required' : 'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ];
        }

        $datos = $request->validate($reglas);

        foreach (array_keys(self::NUEVOS) as $campo) {
            unset($datos[$campo]);
        }

        $archivosNuevos = [];

        try {
            foreach (self::NUEVOS as $campo => $nombre) {
                if ($request->hasFile($campo)) {
                    $archivosNuevos[$campo] = $request
                        ->file($campo)
                        ->store(
                            'anfeca/documentos/' . $userId,
                            'local'
                        );
                }
            }

            DocumentoProbatorio::updateOrCreate(
                ['user_id' => $userId],
                array_merge($datos, $archivosNuevos)
            );
        } catch (\Throwable $e) {
            foreach ($archivosNuevos as $ruta) {
                Storage::disk('local')->delete($ruta);
            }

            report($e);

            return back()
                ->withInput()
                ->withErrors([
                    'documentos' => 'No fue posible guardar los documentos.',
                ]);
        }

        return redirect()
            ->route('registro.documentos')
            ->with('success', 'Documentos guardados correctamente.');
    }

    public function ver(Request $request, string $campo)
    {
        $userId = $request->user()->id;

        if (array_key_exists($campo, self::ACADEMICOS)) {
            $registro = FormacionAcademica::where('user_id', $userId)
                ->first();
        } elseif (array_key_exists($campo, self::NUEVOS)) {
            $registro = DocumentoProbatorio::where('user_id', $userId)
                ->first();
        } else {
            abort(404);
        }

        $ruta = $registro?->{$campo};

        abort_if(
            empty($ruta) || !Storage::disk('local')->exists($ruta),
            404
        );

        return Storage::disk('local')->download($ruta);
    }
}
