<?php

namespace App\Services\Pdm\Autenticacion;

use App\Models\Pdm\Usuario;
use Illuminate\Auth\EloquentUserProvider;

final class ProveedorUsuarioPdm extends EloquentUserProvider
{
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        $correo = $credentials['email'] ?? null;
        if (! is_string($correo) || trim($correo) === '' || array_diff_key($credentials, ['email' => true, 'password' => true])) {
            return null;
        }

        // El mapeo vigente guarda el correo de acceso en USUA_NOMBRE.
        // El SQL oficial permite duplicados. Ante ambigüedad se rechaza el acceso.
        $coincidencias = $this->newModelQuery()
            ->whereRaw('lower(usua_nombre) = lower(?)', [trim($correo)])
            ->limit(2)->get();

        $usuario = $coincidencias->count() === 1 ? $coincidencias->first() : null;

        return $this->activo($usuario);
    }

    public function retrieveById($identifier)
    {
        return $this->activo(parent::retrieveById($identifier));
    }

    private function activo(?Usuario $usuario): ?Usuario
    {
        return $usuario?->usua_estatus === Usuario::ESTATUS_ACTIVO && $usuario->persona->pers_activo !== false
            ? $usuario
            : null;
    }
}
