<?php

namespace App\Models;

use App\Models\Pdm\Usuario;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Notifications\Notifiable;

/**
 * Cuenta de acceso sobre public.usuario (modelo oficial). No agrega columnas:
 *  - correo de acceso      → USUA_NOMBRE (también registrado en COMUNICACION tipo «Correo electrónico»)
 *  - contraseña (hash)     → USUA_CONTRASENIA
 *  - perfil                → USUA_ID_ROL → ROL.ROL_NOMBRE (solicitante, coordinador_nacional, coordinador_zona_N)
 *  - autorización de datos → USUA_ESTATUS = DATOS_AUTORIZADOS
 * Sin «recordarme»: el modelo no tiene columna para el token persistente.
 */
class Cuenta extends Usuario implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPassword, Notifiable;

    public const ROL_SOLICITANTE = 'solicitante';
    public const ROL_COORDINADOR_NACIONAL = 'coordinador_nacional';
    public const PREFIJO_COORDINADOR_ZONA = 'coordinador_zona_';

    protected $hidden = ['usua_contrasenia'];

    public function getAuthPasswordName()
    {
        return 'usua_contrasenia';
    }

    public function getRememberTokenName()
    {
        return '';
    }

    /** Compatibilidad con el código existente ($user->id). */
    public function getIdAttribute(): int
    {
        return (int) $this->usua_id_usuario;
    }

    public function getEmailAttribute(): string
    {
        return (string) $this->usua_nombre;
    }

    public function getEmailForPasswordReset()
    {
        return mb_strtolower($this->email);
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    public function getNameAttribute(): string
    {
        $p = $this->persona;

        return trim(implode(' ', array_filter([$p?->pers_nombre, $p?->pers_apellido_paterno, $p?->pers_apellido_materno])));
    }

    public function getRolAttribute(): ?string
    {
        return $this->rol()->value('rol_nombre');
    }

    public function datosAutorizados(): bool
    {
        return $this->usua_estatus === config('anfeca_8088.estatus_usuario.AUTORIZADO');
    }

    public function esCoordinador(): bool
    {
        return $this->esCoordinadorNacional() || $this->esCoordinadorZona();
    }

    public function esCoordinadorNacional(): bool
    {
        return $this->rol === self::ROL_COORDINADOR_NACIONAL;
    }

    public function esCoordinadorZona(): bool
    {
        return str_starts_with((string) $this->rol, self::PREFIJO_COORDINADOR_ZONA);
    }

    /** Privilegios efectivos (ROL → ROL_PRIVILEGIO → PRIVILEGIO). */
    public function privilegios(): array
    {
        return \Illuminate\Support\Facades\DB::table('rol_privilegio as rp')
            ->join('privilegio as p', 'p.priv_id_privilegio', '=', 'rp.ropr_id_privilegio')
            ->where('rp.ropr_id_rol', $this->usua_id_rol)
            ->pluck('p.priv_nombre')->all();
    }

    public function tienePrivilegio(string $privilegio): bool
    {
        return in_array($privilegio, $this->privilegios(), true);
    }
}
