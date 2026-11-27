<?php

namespace App\Services\Solicitantes;

use App\Auth\UsuarioPdm36;
use Illuminate\Support\Facades\DB;

/**
 * Implementación sobre el modelo de 36 tablas: usuario → persona → solicitud → último estado_solicitud.
 * «Enviada» = último evento con siglas EN; cualquier otro último evento (BO, FA, CA) = borrador.
 */
class EstadoSolicitantePdm36 implements EstadoSolicitante
{
    public function tieneBorrador(int $usuarioId): bool
    {
        return $this->ultimos($usuarioId)->contains(fn ($s) => $s !== 'EN');
    }

    public function tieneEnviada(int $usuarioId): bool
    {
        return $this->ultimos($usuarioId)->contains('EN');
    }

    public function datosAutorizados(int $usuarioId): bool
    {
        return UsuarioPdm36::query()->whereKey($usuarioId)->where('usua_estatus', UsuarioPdm36::ESTATUS_AUTORIZADO)->exists();
    }

    /** Siglas del último evento de cada solicitud de la persona del usuario. */
    private function ultimos(int $usuarioId)
    {
        return collect(DB::select('select distinct on (s.soli_id_solicitud) e.esso_siglas
            from anfeca_modelo.usuario u
            join anfeca_modelo.solicitud s on s.soli_id_persona = u.usua_id_persona
            join anfeca_modelo.estado_solicitud e on e.esso_id_solicitud = s.soli_id_solicitud
            where u.usua_id_usuario = ?
            order by s.soli_id_solicitud, e.esso_fecha desc, e.esso_hora desc, e.esso_id__estado_solicitud desc', [$usuarioId]))
            ->pluck('esso_siglas');
    }
}
