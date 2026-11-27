<?php

namespace App\Services\Solicitantes;

use App\Models\DatosSolicitante;
use App\Models\SolicitudCertificacion;

/** Implementación vigente sobre las tablas operativas de public (comportamiento sin cambios). */
class EstadoSolicitantePublic implements EstadoSolicitante
{
    public function tieneBorrador(int $usuarioId): bool
    {
        return SolicitudCertificacion::query()->where('user_id', $usuarioId)->where('estado', 'borrador')->exists();
    }

    public function tieneEnviada(int $usuarioId): bool
    {
        return SolicitudCertificacion::query()->where('user_id', $usuarioId)->where('estado', 'enviado')->exists();
    }

    public function datosAutorizados(int $usuarioId): bool
    {
        return DatosSolicitante::where('user_id', $usuarioId)->where('datos_autorizados', true)->exists();
    }
}
