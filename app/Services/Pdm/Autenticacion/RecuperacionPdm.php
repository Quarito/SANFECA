<?php

namespace App\Services\Pdm\Autenticacion;

use App\Models\Pdm\Usuario;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

final class RecuperacionPdm
{
    public function enviar(string $curp, string $email): void
    {
        try {
            $this->bloquear($email, function () use ($curp, $email) {
                $usuario = $this->cuentaConCorreoConfirmado($email);
                if ($usuario && $usuario->persona->pers_curp === $curp) {
                    Password::broker('pdm')->sendResetLink(['email' => $email]);
                }
            });
        } catch (LockTimeoutException) {
            // La respuesta pública es idéntica para cuentas desconocidas y solicitudes simultáneas.
        }
    }

    public function restablecer(#[\SensitiveParameter] array $datos): bool
    {
        $email = mb_strtolower(trim($datos['email']));
        $datos['email'] = $email;

        try {
            return $this->bloquear($email, function () use ($datos, $email): bool {
                if (! $this->cuentaConCorreoConfirmado($email)) {
                    return false;
                }

                return Password::broker('pdm')->reset(Arr::only($datos, ['email', 'password', 'token']), function (Usuario $usuario, string $password) {
                    $usuario->forceFill(['usua_contrasenia' => Hash::make($password)])->saveOrFail();
                }) === Password::PASSWORD_RESET;
            });
        } catch (LockTimeoutException) {
            return false;
        }
    }

    private function cuentaConCorreoConfirmado(string $email): ?Usuario
    {
        $usuario = Auth::guard('pdm')->getProvider()->retrieveByCredentials(['email' => $email]);
        $contacto = $usuario?->persona->comunicaciones()
            ->whereHas('tipoComunicacion', fn ($tipo) => $tipo
                ->where('tico_tipo_comunicacion', config('anfeca_pdm.catalogos.tipos_comunicacion.CORREO'))
                ->where('tico_estado', 'ACT'))
            ->whereRaw('lower(comu_valor) = ?', [$email])
            ->exists();

        return $contacto ? $usuario : null;
    }

    private function bloquear(string $email, callable $operacion): mixed
    {
        return Cache::store('pdm_recuperacion')
            ->lock('restablecer:'.hash('sha256', $email), 60)
            ->block(2, $operacion);
    }
}
