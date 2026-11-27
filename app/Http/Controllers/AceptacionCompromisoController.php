<?php

namespace App\Http\Controllers;

use App\Services\Anfeca\SolicitudActiva;
use App\Services\Anfeca\Tramites;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aceptación y compromiso: evento «Compromiso de entrega aceptado» (ESTADO_SOLICITUD, fecha y hora).
 * La casilla es obligatoria y se valida en el servidor antes del envío.
 */
class AceptacionCompromisoController extends Controller
{
    public function __construct(private readonly SolicitudActiva $activa, private readonly Tramites $tramites)
    {
    }

    public function index(Request $request)
    {
        $solicitud = $this->activa->borrador($request->user());
        // Consulta de la aceptación de una solicitud ya enviada.
        if (!$solicitud) {
            $enviada = $this->tramites->ultimaEnviada((int) $request->user()->usua_id_persona);
            $solicitud = $enviada?->compromiso_aceptado ? $enviada : null;
        }
        if (!$solicitud) {
            return redirect()->route('registro.completado')->with('error', 'No existe una solicitud en borrador válida.');
        }

        return view('registro.aceptacion-compromiso', [
            'aceptado' => (bool) $solicitud->compromiso_aceptado,
            'aceptadoEn' => $solicitud->compromiso_aceptado_en?->copy()->timezone('America/Mexico_City')->format('d/m/Y H:i'),
            'soloConsulta' => $solicitud->estado !== 'borrador',
        ]);
    }

    public function guardar(Request $request)
    {
        $request->validate([
            'compromiso_aceptado' => ['required', 'accepted'],
        ], [
            'compromiso_aceptado.accepted' => 'Debe aceptar el compromiso para continuar.',
            'compromiso_aceptado.required' => 'Debe aceptar el compromiso para continuar.',
        ]);

        $solicitud = $this->activa->borrador($request->user());
        if (!$solicitud) {
            return redirect()->route('registro.completado')->with('error', 'No existe una solicitud en borrador válida.');
        }

        DB::transaction(function () use ($solicitud) {
            $actual = $this->tramites->bloquear($solicitud->id);
            if (!$actual || $actual->estado !== 'borrador') {
                throw ValidationException::withMessages(['solicitud' => 'La solicitud ya no permite registrar la aceptación.']);
            }
            if (!$actual->compromiso_aceptado) {
                $this->tramites->registrarEvento($actual->id, 'CA');
            }
        });

        return redirect()->route('registro.completado')->with('success', 'Su compromiso fue registrado correctamente.');
    }
}
