<?php

namespace App\Http\Controllers;

use App\Models\CertificadoHistorico;
use App\Models\DatosSolicitante;
use App\Models\FormacionAcademica;
use App\Models\InstitucionAfiliada;
use App\Models\SeleccionCertificacion;
use App\Models\SolicitudRefrendo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class RefrendoController extends Controller
{
    public const INCISOS = ['14.1' => 'Cargo directivo o equivalente', '14.2' => 'Perfil deseable PRODEP vigente', '14.3' => 'Pertenencia al SNII vigente'];

    private function antecedentes(Request $request)
    {
        $certificacion = SeleccionCertificacion::where('user_id', $request->user()->id)->value('certificacion');

        return CertificadoHistorico::where('user_id', $request->user()->id)
            ->where('certificacion', $certificacion)->where('estado_vinculacion', 'verificado')
            ->whereNotNull('numero_certificado')->where('numero_certificado', '!=', '')->get();
    }

    private function solicitud(Request $request, int $id, bool $editable = false): SolicitudRefrendo
    {
        $solicitud = SolicitudRefrendo::where('user_id', $request->user()->id)->findOrFail($id);
        abort_if($editable && $solicitud->completado_at, 409, 'El registro ya fue enviado y no puede modificarse.');

        return $solicitud;
    }

    public function inicio(Request $request)
    {
        return view('refrendos.inicio', ['datos' => DatosSolicitante::where('user_id', $request->user()->id)->first()]);
    }

    public function certificacion(Request $request)
    {
        return view('refrendos.certificacion', ['seleccion' => SeleccionCertificacion::where('user_id', $request->user()->id)->first()]);
    }

    public function guardarCertificacion(Request $request)
    {
        $datos = $request->validate(['certificacion' => ['required', Rule::in(['ANFECA', 'CACSLA', 'CACECA'])],
            'tipo_personal' => ['required', Rule::in(['Tiempo Completo', 'Medio Tiempo', 'PAES'])],
            'area_certificacion' => ['required', Rule::in(['Contaduría', 'Administración', 'Informática', 'Negocios Internacionales', 'Disciplinas vinculadas al ámbito...'])]]);
        SeleccionCertificacion::updateOrCreate(['user_id' => $request->user()->id], $datos);

        return redirect()->route('refrendos.detalle');
    }

    public function detalle(Request $request)
    {
        return view('refrendos.detalle', ['antecedentes' => $this->antecedentes($request), 'incisos' => self::INCISOS]);
    }

    public function guardarDetalle(Request $request)
    {
        $datos = $request->validate(['certificado_historico_id' => ['required', 'integer'], 'articulo_14' => ['required', 'boolean'],
            'inciso' => [Rule::requiredIf($request->boolean('articulo_14')), 'nullable', Rule::in(array_keys(self::INCISOS))]]);
        $antecedente = $this->antecedentes($request)->firstWhere('id', (int) $datos['certificado_historico_id']);
        abort_unless($antecedente, 403, 'Se requiere un antecedente verificado de la certificación seleccionada.');
        $numero = 1;
        for ($i = 1; $i <= 7; $i++) {
            if (trim((string) $antecedente->{'refrendo_'.$i}) !== '') {
                $numero = $i + 1;
            }
        }
        $solicitud = SolicitudRefrendo::firstOrCreate(
            ['certificado_historico_id' => $antecedente->id, 'ciclo' => now()->year],
            ['user_id' => $request->user()->id, 'numero_certificado' => $antecedente->numero_certificado,
                'numero_refrendo' => $numero, 'articulo_14' => $request->boolean('articulo_14'),
                'inciso' => $request->boolean('articulo_14') ? $datos['inciso'] : null,
                'seleccion' => SeleccionCertificacion::where('user_id', $request->user()->id)->first()->only(['certificacion', 'tipo_personal', 'area_certificacion'])]);
        abort_unless($solicitud->user_id === $request->user()->id, 403);
        DB::transaction(function () use ($request, $solicitud, $datos) {
            $solicitud = SolicitudRefrendo::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();
            if ($solicitud->completado_at) {
                return;
            }
            $solicitud->update(['articulo_14' => $request->boolean('articulo_14'), 'inciso' => $request->boolean('articulo_14') ? $datos['inciso'] : null,
                'seleccion' => SeleccionCertificacion::where('user_id', $request->user()->id)->first()->only(['certificacion', 'tipo_personal', 'area_certificacion'])]);
        });
        $solicitud->refresh();

        return redirect()->route($solicitud->completado_at ? 'refrendos.completado' : 'refrendos.formacion', $solicitud->id);
    }

    public function formacion(Request $request, int $id)
    {
        $solicitud = $this->solicitud($request, $id);

        if ($solicitud->completado_at) {
            return redirect()->route('refrendos.completado', $id);
        }

        return view('refrendos.formacion', ['solicitud' => $solicitud,
            'formacion' => $solicitud->formacion ?? FormacionAcademica::where('user_id', $request->user()->id)->first()?->toArray() ?? [],
            'instituciones' => InstitucionAfiliada::orderBy('numero_afiliacion')->get()]);
    }

    public function guardarFormacion(Request $request, int $id)
    {
        $solicitud = $this->solicitud($request, $id, true);
        $niveles = ['Licenciatura', 'Especialidad', 'Maestría', 'Doctorado'];
        $datos = $request->validate(['nivel_origen' => ['required', Rule::in($niveles)], 'titulo_origen' => ['required', 'string', 'max:200'],
            'nivel_ultimo_grado' => ['required', Rule::in($niveles)], 'titulo_ultimo_grado' => ['required', 'string', 'max:200'],
            'institucion_afiliada_id' => ['required', 'exists:instituciones_afiliadas,id']]);
        $datos['institucion'] = InstitucionAfiliada::findOrFail($datos['institucion_afiliada_id'])->nombre;
        DB::transaction(function () use ($request, $id, $datos) {
            $locked = SolicitudRefrendo::where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($id);
            abort_if($locked->completado_at, 409);
            $locked->update(['formacion' => $datos]);
        });

        return redirect()->route('refrendos.documentos', $id);
    }

    public static function campos(bool $articulo): array
    {
        $campos = ['constancia_antiguedad' => 'Constancia de Antigüedad', 'comprobante_pago' => 'Comprobante de Pago',
            'cedula_ultimo_grado' => 'Cédula de último grado', 'titulo_ultimo_grado' => 'Título de último grado'];

        return $articulo ? $campos + ['carta_solicitud' => 'Carta Solicitud de Refrendo por Art. 14', 'constancia_articulo_14' => 'Constancia de Refrendo por Art. 14'] : $campos;
    }

    public function documentos(Request $request, int $id)
    {
        $solicitud = $this->solicitud($request, $id);
        if (! $solicitud->formacion) {
            return redirect()->route('refrendos.formacion', $id);
        }

        return view('refrendos.documentos', ['solicitud' => $solicitud, 'campos' => self::campos($solicitud->articulo_14)]);
    }

    public function guardarDocumentos(Request $request, int $id)
    {
        $solicitud = $this->solicitud($request, $id, true);
        abort_unless($solicitud->formacion, 409, 'Completa primero tu formación académica.');
        $reglas = [];
        foreach (self::campos($solicitud->articulo_14) as $campo => $label) {
            $reglas[$campo] = [empty($solicitud->documentos[$campo]) ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'];
        }
        $request->validate($reglas);
        $nuevos = [];
        try {
            foreach (array_keys($reglas) as $campo) {
                if ($request->hasFile($campo)) {
                    $ruta = $request->file($campo)->store('anfeca/refrendos/'.$request->user()->id.'/'.$id, 'local');
                    if (! $ruta) {
                        throw new \RuntimeException('No se pudo almacenar el documento.');
                    }
                    $nuevos[$campo] = $ruta;
                }
            }
            DB::transaction(function () use ($request, $id, $nuevos, $solicitud) {
                $locked = SolicitudRefrendo::where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($id);
                abort_if($locked->completado_at || $locked->articulo_14 !== $solicitud->articulo_14 || $locked->inciso !== $solicitud->inciso, 409, 'La solicitud cambió. Revisa los documentos y vuelve a intentar.');
                $locked->update(['documentos' => array_merge($locked->documentos ?? [], $nuevos),
                    'datos_personales' => DatosSolicitante::where('user_id', $request->user()->id)->first()->only(['nombres', 'apellido_paterno', 'apellido_materno', 'curp', 'correo_electronico']),
                    'completado_at' => now()]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete(array_values($nuevos));
            throw $exception;
        }

        return redirect()->route('refrendos.completado', $id);
    }

    public function ver(Request $request, int $id, string $campo)
    {
        $solicitud = $this->solicitud($request, $id);
        abort_unless(array_key_exists($campo, self::campos($solicitud->articulo_14)), 404);
        $ruta = $solicitud->documentos[$campo] ?? null;
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404);

        return Storage::disk('local')->download($ruta, $campo.'.'.pathinfo($ruta, PATHINFO_EXTENSION));
    }

    public function completado(Request $request, int $id)
    {
        $solicitud = $this->solicitud($request, $id);
        abort_unless($solicitud->completado_at, 409, 'El registro aún no está completo.');

        return view('refrendos.completado', compact('solicitud'));
    }

    public function comprobante(Request $request, int $id)
    {
        $solicitud = $this->solicitud($request, $id);
        abort_unless($solicitud->completado_at, 409);

        return response()->view('refrendos.comprobante', compact('solicitud'))->header('Content-Disposition', 'attachment; filename="comprobante-refrendo-'.$id.'.html"');
    }
}
