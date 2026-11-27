<?php

namespace App\Models\Pdm;

use App\Models\User;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

/**
 * Tabla anfeca_modelo.usuario del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class Usuario extends PdmModel implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPassword, Notifiable;

    public const ESTATUS_ACTIVO = 'ACTIVO';

    protected $hidden = ['usua_contrasenia'];

    protected $table = 'anfeca_modelo.usuario';

    protected $primaryKey = 'usua_id_usuario';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'usua_id_usuario',
        'usua_id_persona',
        'usua_id_rol',
        'usua_nombre',
        'usua_contrasenia',
        'usua_estatus',
    ];

    protected $casts = [
        'usua_id_usuario' => 'integer',
        'usua_id_persona' => 'integer',
        'usua_id_rol' => 'integer',
    ];

    public function getAuthPasswordName()
    {
        return 'usua_contrasenia';
    }

    public function getRememberTokenName()
    {
        // El modelo aprobado solo admite sesión, sin token persistente.
        return '';
    }

    public function getEmailAttribute(): string
    {
        return $this->usua_nombre;
    }

    public function getEmailForPasswordReset()
    {
        return mb_strtolower($this->email);
    }

    public function getNameAttribute(): string
    {
        $persona = $this->persona;

        return implode(' ', array_filter([$persona->pers_nombre, $persona->pers_apellido_paterno, $persona->pers_apellido_materno]));
    }

    public function esCoordinador(): bool
    {
        return in_array($this->rol->rol_nombre, User::ROLES_COORDINACION, true);
    }

    /** FK FK_USUARIO_TIENE_ROL_ROL: usuario.usua_id_rol → rol.rol_id_rol */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'usua_id_rol', 'rol_id_rol');
    }

    /** FK FK_USUARIO_TIENE_USU_PERSONA: usuario.usua_id_persona → persona.pers_id_persona */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'usua_id_persona', 'pers_id_persona');
    }

    /** FK FK_COORDZON_USUARIO: coordinador_zona.cozo_id_usuario → usuario.usua_id_usuario */
    public function coordinadorZonas(): HasMany
    {
        return $this->hasMany(CoordinadorZona::class, 'cozo_id_usuario', 'usua_id_usuario');
    }

    /** FK FK_HCIM_USUARIO: historico_certificado_importacion.hcim_id_usuario → usuario.usua_id_usuario */
    public function historicoCertificadoImportaciones(): HasMany
    {
        return $this->hasMany(HistoricoCertificadoImportacion::class, 'hcim_id_usuario', 'usua_id_usuario');
    }

    /** FK FK_SECC_USUARIO: seleccion_certificacion.secc_id_usuario → usuario.usua_id_usuario */
    public function seleccionCertificaciones(): HasMany
    {
        return $this->hasMany(SeleccionCertificacion::class, 'secc_id_usuario', 'usua_id_usuario');
    }
}
