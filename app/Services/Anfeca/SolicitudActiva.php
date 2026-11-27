<?php

namespace App\Services\Anfeca;

use App\Models\Cuenta;

/**
 * Resuelve la solicitud con la que trabaja cada pantalla del registro:
 *  - borrador(): la solicitud sin enviar de la persona; un refrendo solo es válido si su certificado
 *    histórico sigue perteneciendo a la persona (CURP) y a la certificación.
 *  - paraConsulta(): el borrador válido o, si no hay, la última solicitud enviada.
 */
final class SolicitudActiva
{
    public function __construct(private readonly Tramites $tramites, private readonly ConsultaRefrendo $refrendo)
    {
    }

    public function borrador(Cuenta $cuenta): ?SolicitudVista
    {
        $solicitud = $this->tramites->borrador((int) $cuenta->usua_id_persona);
        if (!$solicitud) {
            return null;
        }
        if ($solicitud->tipo_tramite === 'refrendo'
            && !$this->refrendo->validarCertificadoAnterior($cuenta->id, (string) $solicitud->certificacion, (string) $solicitud->numero_certificado)) {
            return null;
        }

        return $solicitud;
    }

    public function paraConsulta(Cuenta $cuenta): ?SolicitudVista
    {
        return $this->borrador($cuenta) ?? $this->tramites->ultimaEnviada((int) $cuenta->usua_id_persona);
    }
}
