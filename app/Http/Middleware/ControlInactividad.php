<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inactividad real del usuario, independiente de la vigencia física del archivo de sesión.
 *
 * - Guarda en la propia sesión (controlador existente; sin tablas) el momento de la última petición del usuario.
 * - La consulta /sesion/estado NO cuenta como actividad: comprobar el estado nunca prolonga la sesión.
 * - Si pasaron más de session.lifetime minutos sin actividad, la sesión se da por terminada aunque el archivo
 *   siga existiendo: se cierra la autenticación, se invalida la sesión y se regenera el token CSRF.
 *   No se reactiva ni se autentica nada.
 */
class ControlInactividad
{
    public const CLAVE = 'anfeca_sesion.ultima_actividad';

    public const ENCABEZADO = 'X-Anfeca-Sesion';

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

        if ($ultima !== null && $ahora - (int) $ultima > self::limiteSegundos()) {
            $autenticado = Auth::check();
            if ($autenticado) {
                Auth::guard('web')->logout();
            }
            $sesion->invalidate();
            $sesion->regenerateToken();
            $sesion->put(self::CLAVE, $ahora);

            // Visitante que solo consulta una página: se continúa con una sesión nueva (sin aviso ni bucles).
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
