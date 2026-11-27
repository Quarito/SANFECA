<?php

namespace App\Services\Anfeca\Autenticacion;

use App\Models\Cuenta;
use App\Services\Anfeca\Catalogo;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * Recuperación de contraseña: la cuenta debe coincidir en CURP (PERSONA) y correo (USUARIO.USUA_NOMBRE y
 * COMUNICACION «Correo electrónico»). Los tokens viven en la caché de archivos: no se crea ninguna tabla.
 * La respuesta pública es idéntica exista o no la cuenta.
 */
final class RecuperacionCuentas
{
    public function enviar(string $curp, string $email): void
    {
        try {
            $this->bloquear($email, function () use ($curp, $email) {
                $cuenta = $this->cuentaConCorreoConfirmado($email);
                if ($cuenta && mb_strtoupper((string) $cuenta->persona?->pers_curp) === $curp) {
                    Password::broker('cuentas')->sendResetLink(['email' => $email]);
                }
            });
        } catch (LockTimeoutException) {
        }
    }

    public function restablecer(#[\SensitiveParameter] array $datos): bool
    {
        $email = mb_strtolower(trim($datos['email']));
        $datos['email'] = $email;
        try {
            return $this->bloquear($email, function () use ($datos, $email): bool {
                if (!$this->cuentaConCorreoConfirmado($email)) {
                    return false;
                }

                return Password::broker('cuentas')->reset(Arr::only($datos, ['email', 'password', 'token']), function (Cuenta $cuenta, string $password) {
                    DB::table('usuario')->where('usua_id_usuario', $cuenta->usua_id_usuario)->update(['usua_contrasenia' => Hash::make($password)]);
                }) === Password::PASSWORD_RESET;
            });
        } catch (LockTimeoutException) {
            return false;
        }
    }

    private function cuentaConCorreoConfirmado(string $email): ?Cuenta
    {
        $cuenta = Auth::guard('web')->getProvider()->retrieveByCredentials(['email' => $email]);
        $confirmado = $cuenta && DB::table('comunicacion')->where('comu_id_persona', $cuenta->usua_id_persona)
            ->where('comu_id_tipo_comunicacion', Catalogo::tipoComunicacion('CORREO'))
            ->whereRaw('lower(comu_valor) = ?', [$email])->exists();

        return $confirmado ? $cuenta : null;
    }

    private function bloquear(string $email, callable $operacion): mixed
    {
        return Cache::store('recuperacion')->lock('restablecer:'.hash('sha256', $email), 60)->block(2, $operacion);
    }
}
