<?php

namespace App\Http\Controllers;

use App\Services\Anfeca\ConsultaRefrendo;
use App\Services\Anfeca\SolicitudActiva;
use App\Services\Anfeca\Tramites;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Selección de certificación sobre SOLICITUD (convocatoria, área, tipo de personal, número de certificado).
 * El refrendo solo inicia cuando la persona captura explícitamente el número de su certificado.
 */
class SeleccionCertificacionController extends Controller
{
    public function __construct(
        private readonly Tramites $tramites,
        private readonly ConsultaRefrendo $refrendo,
        private readonly SolicitudActiva $activa,
    ) {
    }

    public function create(Request $request)
    {
        $cuenta = $request->user();
        $solicitud = $this->activa->borrador($cuenta);
        $seleccion = $solicitud ? (object) [
            'certificacion' => $solicitud->certificacion,
            'tipo_personal' => $solicitud->tipo_personal,
            'area_certificacion' => $solicitud->area_certificacion,
        ] : null;

        $resultado = $this->refrendo->consultar($cuenta->id, $seleccion?->certificacion);
        $resultado['certificacion_consultada'] = $seleccion?->certificacion;

        return view('registro.certificacion', [
            'seleccion' => $seleccion,
            'resultadoAntecedentes' => $resultado,
            'numeroCertificadoGuardado' => $solicitud?->tipo_tramite === 'refrendo' ? (string) $solicitud->numero_certificado : '',
        ]);
    }

    public function consultarCertificadoAnterior(Request $request)
    {
        $datos = $request->validate([
            'numero_certificado' => ['required', 'string', 'regex:/^[0-9]{1,7}$/'],
        ]);
        $cuenta = $request->user();
        if (!$cuenta->datosAutorizados()) {
            return response()->json(['mensaje' => 'Complete y autorice sus datos personales antes de consultar.'], 422);
        }

        $mensajeNoDisponible = 'No fue posible verificar un certificado con los datos proporcionados.';
        $certificado = null;
        foreach (array_keys((array) config('anfeca_8088.certificaciones')) as $certificacion) {
            $certificado = $this->refrendo->validarCertificadoAnterior($cuenta->id, $certificacion, $datos['numero_certificado']);
            if ($certificado) {
                break;
            }
        }
        if (!$certificado) {
            return response()->json(['encontrado' => false, 'mensaje' => $mensajeNoDisponible]);
        }

        return response()->json([
            'encontrado' => true,
            'mensaje' => 'Certificado verificado correctamente.',
            'historial' => [
                'certificacion' => $certificado->certificacion,
                'primera_vez' => $certificado->primera_vez,
                'refrendos' => $this->refrendo->refrendos($certificado),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->session()->forget('certificado_anterior_validado');
        $datos = $request->validate([
            'certificacion' => ['required', Rule::in(array_keys((array) config('anfeca_8088.certificaciones')))],
            'tipo_personal' => ['required', 'string', 'max:150', Rule::in((array) config('anfeca_8088.tipos_personal_academico'))],
            'area_certificacion' => ['required', 'string', 'max:200', Rule::in((array) config('anfeca_8088.areas_certificacion'))],
            'numero_certificado_anterior' => ['nullable', 'string', 'regex:/^[0-9]{1,7}$/'],
        ]);
        $cuenta = $request->user();
        $persona = (int) $cuenta->usua_id_persona;
        $numero = trim((string) ($datos['numero_certificado_anterior'] ?? ''));

        $borrador = $this->tramites->borrador($persona);
        if ($borrador && $borrador->certificacion !== $datos['certificacion']) {
            return redirect()->route('registro.certificacion')->withErrors([
                'certificacion' => 'Ya tiene una solicitud en proceso. Debe continuarla antes de iniciar otra certificación.',
            ]);
        }
        if ($borrador && $borrador->tipo_tramite === 'refrendo' && $numero !== '' && $numero !== (string) $borrador->numero_certificado) {
            return back()->withErrors(['numero_certificado_anterior' => 'El número proporcionado no corresponde al certificado vinculado al refrendo.'])->withInput();
        }
        if ($borrador && $borrador->tipo_tramite === 'inicial' && $numero !== '') {
            return redirect()->route('registro.certificacion')->withErrors([
                'certificacion' => 'Ya existe una solicitud en proceso. Debe continuarla antes de iniciar otra.',
            ]);
        }
        if ($borrador && $borrador->tipo_tramite === 'refrendo') {
            $numero = (string) $borrador->numero_certificado;
        }

        // Refrendo: el certificado debe ser propio, de la certificación elegida y estar en periodo de refrendo.
        $certificado = null;
        if ($numero !== '') {
            $certificado = $this->refrendo->validarCertificadoAnterior($cuenta->id, $datos['certificacion'], $numero);
            if (!$certificado) {
                return back()->withErrors([
                    'numero_certificado_anterior' => $borrador
                        ? 'No fue posible verificar el certificado histórico vinculado al refrendo.'
                        : 'No fue posible validar el certificado anterior. Verifique el número y la certificación seleccionada.',
                ])->withInput();
            }
            if (!$borrador) {
                $vigencia = $this->refrendo->verificarVigenciaRefrendo($certificado);
                if (!$vigencia['elegible']) {
                    $mensaje = ($vigencia['motivo'] ?? '') === 'certificado_vigente'
                        ? 'El certificado todavía está vigente. Podrá solicitar el siguiente refrendo a partir del año '.$vigencia['proximo_anio'].'.'
                        : 'No fue posible determinar la vigencia del certificado histórico. Se requiere revisión.';

                    return back()->withErrors(['numero_certificado_anterior' => $mensaje])->withInput();
                }
            }
        } elseif (!$borrador && !$this->refrendo->primeraVezPermitida($cuenta->id, $datos['certificacion'], $datos['area_certificacion'])) {
            return redirect()->route('registro.certificacion')->with('error',
                'Ya cuenta con un certificado de '.$datos['certificacion'].' en el área '.$datos['area_certificacion']
                .'. Para refrendarlo, capture el número de su certificado anterior.');
        }

        try {
            $tipo = DB::transaction(function () use ($persona, $datos, $certificado, $borrador) {
                // Serializa las selecciones simultáneas de la misma persona.
                DB::table('persona')->where('pers_id_persona', $persona)->lockForUpdate()->first();
                $actual = $this->tramites->borrador($persona);
                if (($actual?->id) !== ($borrador?->id)) {
                    throw new \RuntimeException('borrador_incompatible');
                }
                if ($certificado && $this->refrendo->refrendoDuplicado($certificado, $actual?->id)) {
                    throw ValidationException::withMessages([
                        'numero_certificado_anterior' => 'Ya existe una solicitud de refrendo de este certificado en el periodo vigente.',
                    ]);
                }
                if ($actual) {
                    $this->tramites->actualizarSeleccion($actual->id, $datos['tipo_personal'], $datos['area_certificacion']);

                    return $actual->tipo_tramite;
                }
                $this->tramites->crear($persona, $datos['certificacion'], $datos['tipo_personal'], $datos['area_certificacion'],
                    $certificado?->numero_certificado);

                return $certificado ? 'refrendo' : 'inicial';
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'borrador_incompatible') {
                throw $e;
            }

            return redirect()->route('registro.certificacion')->withErrors([
                'certificacion' => 'Ya existe una solicitud en proceso. Debe continuarla antes de iniciar otra.',
            ]);
        }

        if ($tipo === 'refrendo') {
            return redirect()->route('registro.refrendo')->with('success', 'Se identificó automáticamente un trámite de refrendo.');
        }

        return redirect()->route('registro.formacion')->with('success', 'Se identificó automáticamente un trámite de primera vez.');
    }
}
