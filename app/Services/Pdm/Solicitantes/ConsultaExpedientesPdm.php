<?php

namespace App\Services\Pdm\Solicitantes;

use App\Models\Pdm\Solicitud;
use Illuminate\Database\Eloquent\Builder;

final class ConsultaExpedientesPdm
{
    public function consulta(): Builder
    {
        return Solicitud::query()
            ->with(['persona.institucion', 'areaCertificacion', 'tipoPersonalAcademico', 'convocatoria.tipoConvocatoria', 'ultimoEstado']);
    }

    public function estado(Solicitud $solicitud): ?string
    {
        return match ($solicitud->ultimoEstado?->esso_siglas) {
            'EN' => 'enviado',
            'BO', 'FA', 'CA' => 'borrador',
            default => null,
        };
    }

    public function fechaEstado(Solicitud $solicitud): ?string
    {
        $evento = $solicitud->ultimoEstado;

        return $evento ? $evento->esso_fecha->format('Y-m-d').' '.substr($evento->esso_hora, 0, 8) : null;
    }
}
