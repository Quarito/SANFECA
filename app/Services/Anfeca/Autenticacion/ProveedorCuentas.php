<?php

namespace App\Services\Anfeca\Autenticacion;

use App\Models\Cuenta;
use Illuminate\Auth\EloquentUserProvider;

/**
 * Proveedor de cuentas sobre public.usuario. El correo de acceso es USUA_NOMBRE (sin distinguir mayúsculas).
 * El SQL oficial no impone unicidad: ante dos cuentas con el mismo correo se rechaza el acceso.
 * Solo acceden cuentas ACTIVO o DATOS_AUTORIZADOS cuya persona no esté inactiva.
 */
final class ProveedorCuentas extends EloquentUserProvider
{
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        $correo = $credentials['email'] ?? null;
        if (!is_string($correo) || trim($correo) === '' || array_diff_key($credentials, ['email' => true, 'password' => true])) {
            return null;
        }
        $coincidencias = $this->newModelQuery()->whereRaw('lower(usua_nombre) = lower(?)', [trim($correo)])->limit(2)->get();

        return $this->activa($coincidencias->count() === 1 ? $coincidencias->first() : null);
    }

    public function retrieveById($identifier)
    {
        return $this->activa(parent::retrieveById($identifier));
    }

    private function activa(?Cuenta $cuenta): ?Cuenta
    {
        $permitidos = [config('anfeca_8088.estatus_usuario.ACTIVO'), config('anfeca_8088.estatus_usuario.AUTORIZADO')];

        return $cuenta && in_array($cuenta->usua_estatus, $permitidos, true) && $cuenta->persona?->pers_activo !== false ? $cuenta : null;
    }
}
