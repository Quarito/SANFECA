<?php

namespace App\Http\Middleware;

use App\Services\Pdm\ModoPdm;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * F4/F5 · En modo principal o exclusivo, la petición lee y escribe a través de las vistas
 * anfeca_compat (mismos nombres y columnas que public), sin cambiar modelos, controladores,
 * Blade ni Vue. users, sesiones y demás tablas técnicas siguen resolviéndose en public.
 *
 * En apagado y espejo no hace nada (con ANFECA_PDM_MODO=apagado ni siquiera consulta la base).
 * search_path se fija en la propia conexión de la petición (sin DB::purge) para que las
 * lecturas posteriores a una escritura vean la misma transacción.
 */
class AplicarModoPdm
{
    public function handle(Request $request, Closure $next): Response
    {
        if (ModoPdm::sobreVistas()) {
            DB::statement('set search_path to anfeca_compat, public');
        }

        return $next($request);
    }
}
