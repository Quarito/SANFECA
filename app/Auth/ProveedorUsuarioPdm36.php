<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/** Proveedor de usuarios sobre anfeca_modelo.usuario: el «email» del formulario de acceso es usua_nombre. */
class ProveedorUsuarioPdm36 extends EloquentUserProvider
{
    public function retrieveByCredentials(array $credentials)
    {
        $correo = mb_strtolower(trim((string) ($credentials['email'] ?? '')));
        if ($correo === '') {
            return null;
        }

        return $this->newModelQuery()->where('usua_nombre', $correo)->where('usua_estatus', '<>', 'BAJA')->first();
    }

    /** El modelo aprobado no tiene token de «recordarme»: no se guarda nada. */
    public function updateRememberToken(Authenticatable $user, $token): void {}
}
