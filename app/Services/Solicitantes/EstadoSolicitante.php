<?php

namespace App\Services\Solicitantes;

/** Estado del solicitante que determina la navegación tras el acceso y el acceso a las etapas del registro. */
interface EstadoSolicitante
{
    public function tieneBorrador(int $usuarioId): bool;

    public function tieneEnviada(int $usuarioId): bool;

    public function datosAutorizados(int $usuarioId): bool;
}
