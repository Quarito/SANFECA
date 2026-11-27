<?php

namespace App\Auth;

use App\Models\Pdm\Comunicacion;
use App\Models\Pdm\Usuario;
use App\Models\User;
use Illuminate\Auth\Authenticatable as AutenticableTrait;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Usuario autenticable sobre el modelo aprobado (anfeca_modelo.usuario), sin la tabla users.
 *
 * Correspondencias (sin columnas nuevas):
 *  - usuario de acceso = usua_nombre (correo en minúsculas; el mismo correo figura en comunicacion «Correo electrónico»);
 *  - contraseña = usua_contrasenia (hash bcrypt, varchar 255);
 *  - rol = rol.rol_nombre: solicitante | coordinador_nacional | coordinador_zona_<n> (la zona del coordinador es su rol);
 *  - autorización de datos personales = usua_estatus 'DATOS_AUTORIZADOS' (antes 'REGISTRADO').
 * Expone id, name, email y rol con la misma semántica que App\Models\User para no cambiar controladores ni vistas.
 */
class UsuarioPdm36 extends Usuario implements Authenticatable
{
    use AutenticableTrait;

    public const ESTATUS_REGISTRADO = 'REGISTRADO';

    public const ESTATUS_AUTORIZADO = 'DATOS_AUTORIZADOS';

    protected $appends = [];

    public function getAuthPasswordName(): string
    {
        return 'usua_contrasenia';
    }

    /** Sin «recordarme»: el modelo no tiene columna de token. */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function getIdAttribute(): int
    {
        return (int) $this->usua_id_usuario;
    }

    public function getEmailAttribute(): string
    {
        return (string) $this->usua_nombre;
    }

    public function getNameAttribute(): string
    {
        $p = $this->persona;

        return $p ? trim(implode(' ', array_filter([$p->pers_nombre, $p->pers_apellido_paterno, $p->pers_apellido_materno]))) : (string) $this->usua_nombre;
    }

    /** Rol con los mismos valores que users.rol (solicitante, coordinador_nacional, coordinador_zona). */
    public function getRolAttribute(): string
    {
        $nombre = (string) $this->rol()->value('rol_nombre');

        return str_starts_with($nombre, 'coordinador_zona_') ? User::ROL_COORDINADOR_ZONA : $nombre;
    }

    public function zona(): ?int
    {
        $nombre = (string) $this->rol()->value('rol_nombre');

        return preg_match('/^coordinador_zona_([1-7])$/', $nombre, $m) ? (int) $m[1] : null;
    }

    public function esCoordinador(): bool
    {
        return in_array($this->rol, User::ROLES_COORDINACION, true);
    }

    public function esCoordinadorNacional(): bool
    {
        return $this->rol === User::ROL_COORDINADOR_NACIONAL;
    }

    public function esCoordinadorZona(): bool
    {
        return $this->rol === User::ROL_COORDINADOR_ZONA;
    }

    public function datosAutorizados(): bool
    {
        return $this->usua_estatus === self::ESTATUS_AUTORIZADO;
    }

    public function correoComunicacion(): ?string
    {
        return Comunicacion::query()
            ->join('anfeca_modelo.tipo_comunicacion as t', 't.tico_id_tipo_comunicacion', '=', 'anfeca_modelo.comunicacion.comu_id_tipo_comunicacion')
            ->where('comu_id_persona', $this->usua_id_persona)->where('t.tico_tipo_comunicacion', 'Correo electrónico')
            ->value('comu_valor');
    }
}
