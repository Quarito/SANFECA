<?php

namespace App\Policies;

use App\Models\SolicitudCertificacion;
use App\Models\User;
use App\Services\Coordinacion\ConsultaSolicitudes;

/**
 * Autorización de la coordinación sobre solicitudes y sus documentos.
 * Se evalúa con la misma consulta de alcance que la bandeja y el dashboard.
 */
class SolicitudCertificacionPolicy
{
    public function __construct(private readonly ConsultaSolicitudes $consulta)
    {
    }

    public function consultarComoCoordinacion(User $usuario, SolicitudCertificacion $solicitud): bool
    {
        return $usuario->esCoordinador() && $this->consulta->permite($usuario, (int) $solicitud->id);
    }

    public function descargarDocumentoComoCoordinacion(User $usuario, SolicitudCertificacion $solicitud): bool
    {
        return $this->consultarComoCoordinacion($usuario, $solicitud);
    }
}
