<?php

namespace App\Http\Controllers;

use App\Services\Anfeca\ConsultaRefrendo;
use App\Services\Anfeca\HistoricoCertificados;
use App\Services\Anfeca\SolicitudActiva;
use App\Services\Anfeca\Tramites;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Datos de refrendo sobre SOLICITUD (SOLI_ART14, SOLI_ART13, SOLI_ID_ARTICULO → ARTICULO_SUBINDICE) y el evento
 * «Datos de refrendo registrados». El número de refrendo correspondiente se calcula del histórico del certificado.
 */
class DatoRefrendoController extends Controller
{
    public function __construct(
        private readonly SolicitudActiva $activa,
        private readonly Tramites $tramites,
        private readonly ConsultaRefrendo $refrendo,
        private readonly HistoricoCertificados $historico,
    ) {
    }

    private function solicitudRefrendo(Request $request)
    {
        $solicitud = $this->activa->borrador($request->user());

        return $solicitud && $solicitud->tipo_tramite === 'refrendo' ? $solicitud : null;
    }

    public function create(Request $request)
    {
        $solicitud = $this->solicitudRefrendo($request);
        if (!$solicitud) {
            return redirect()->route('registro.certificacion')
                ->with('error', 'No existe un refrendo activo. Primero debe guardar su selección de certificación.');
        }
        $certificado = $this->historico->certificado((string) $solicitud->numero_certificado, $solicitud->certificacion);

        return view('registro.refrendo', [
            'solicitud' => $solicitud,
            'datosRefrendo' => $solicitud->datosRefrendo,
            'datosAutomaticos' => $this->refrendo->obtenerDatosRefrendo($certificado),
        ]);
    }

    public function store(Request $request)
    {
        $solicitud = $this->solicitudRefrendo($request);
        if (!$solicitud) {
            return redirect()->route('registro.certificacion')->with('error', 'No existe una solicitud de refrendo activa.');
        }

        $datos = $request->validate([
            'aplica_articulo_14' => ['required', 'boolean'],
            'inciso_articulo_14' => ['nullable', 'required_if:aplica_articulo_14,1', 'in:13.1,14.1,14.2,14.3'],
        ]);
        $aplica = (int) $datos['aplica_articulo_14'] === 1;

        DB::transaction(function () use ($solicitud, $aplica, $datos) {
            $actual = $this->tramites->bloquear($solicitud->id);
            abort_unless($actual && $actual->estado === 'borrador', 409, 'La solicitud ya no admite modificaciones.');
            $this->tramites->guardarRefrendo($solicitud->id, $aplica, $aplica ? $datos['inciso_articulo_14'] : null);
        });

        // Todos los refrendos, incluido artículo 14, continúan a Formación académica.
        return redirect()->route('registro.formacion')->with('success', 'Datos de refrendo guardados correctamente.');
    }
}
