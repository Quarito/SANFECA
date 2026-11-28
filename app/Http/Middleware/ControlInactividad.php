<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inactividad real del usuario, independiente de la vigencia física del archivo de sesión.
 *
 * - Toda petición normal (navegación, envío de formularios, AJAX) es actividad, como en Laravel.
 * - GET /sesion/estado NO es actividad: comprobar el estado nunca prolonga la sesión.
 * - Momento exacto del cierre: la primera petición recibida cuando han pasado MÁS de session.lifetime minutos
 *   (120 → 7 200 s) desde la última actividad. Nunca antes. Entonces se cierra la autenticación, se invalida la
 *   sesión y se regenera el token; no se reactiva ni se autentica nada.
 * - Si el navegador envía la cookie de sesión pero el servidor ya no tiene esa sesión (archivo vencido o
 *   invalidado), la petición se marca como «sesión perdida» para que un 401 posterior se identifique como
 *   sesión finalizada y no como cualquier otro error de autenticación.
 */
class ControlInactividad
{
    public const CLAVE = 'anfeca_sesion.ultima_actividad';

    public const ENCABEZADO = 'X-Anfeca-Sesion';

    public const SESION_PERDIDA = 'anfeca_sesion_perdida';

    public static function limiteSegundos(): int
    {
        return max(60, (int) config('session.lifetime', 120) * 60);
    }

    /** Segundos de inactividad que le quedan a la sesión de la petición (0 si ya terminó). */
    public static function restante(Request $request): int
    {
        if (!$request->hasSession()) {
            return 0;
        }
        $ultima = (int) $request->session()->get(self::CLAVE, time());

        return max(0, self::limiteSegundos() - (time() - $ultima));
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('anfeca_sesion.activo') || !$request->hasSession()) {
            return $next($request);
        }

        $sesion = $request->session();
        $ultima = $sesion->get(self::CLAVE);
        $ahora = time();

        if ($ultima === null && $request->cookies->has($sesion->getName())) {
            $request->attributes->set(self::SESION_PERDIDA, true);
        }

        if ($ultima !== null && $ahora - (int) $ultima > self::limiteSegundos()) {
            $autenticado = Auth::check();
            if ($autenticado) {
                Auth::guard('web')->logout();
            }
            $sesion->invalidate();
            $sesion->regenerateToken();
            $sesion->put(self::CLAVE, $ahora);

            // Visitante que solo consulta una página: continúa con una sesión nueva (sin aviso ni bucles).
            $consulta = $request->isMethod('GET') || $request->isMethod('HEAD');
            if ((!$autenticado && $consulta && !$request->routeIs('sesion.estado')) || $request->routeIs('sesion.finalizada')) {
                return $next($request);
            }
            if ($request->expectsJson()) {
                return response()->json([
                    'vigente' => false,
                    'autenticado' => false,
                    'mensaje' => 'Su sesión terminó por inactividad. Inicie sesión nuevamente para continuar.',
                    'redireccion' => route('sesion.finalizada'),
                ], 401)->header(self::ENCABEZADO, 'finalizada');
            }

            return redirect()->route('sesion.finalizada')->with('sesion_motivo', $autenticado ? 'inactividad' : 'formulario');
        }

        if (!$request->routeIs('sesion.estado')) {
            $sesion->put(self::CLAVE, $ahora);
        }

        return $next($request);
    }
}
