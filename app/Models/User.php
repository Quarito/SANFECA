<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // Roles en users.rol (varchar 20). El alta pública asigna siempre SOLICITANTE;
    // los roles administrativos solo se asignan con anfeca:coordinador-alta.
    public const ROL_SOLICITANTE = 'solicitante';
    public const ROL_COORDINADOR_NACIONAL = 'coordinador_nacional';
    public const ROL_COORDINADOR_ZONA = 'coordinador_zona';
    public const ROLES_COORDINACION = [self::ROL_COORDINADOR_NACIONAL, self::ROL_COORDINADOR_ZONA];

    public function esCoordinador(): bool
    {
        return in_array($this->rol, self::ROLES_COORDINACION, true);
    }

    public function esCoordinadorNacional(): bool
    {
        return $this->rol === self::ROL_COORDINADOR_NACIONAL;
    }

    public function esCoordinadorZona(): bool
    {
        return $this->rol === self::ROL_COORDINADOR_ZONA;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
