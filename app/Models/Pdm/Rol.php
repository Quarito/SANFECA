<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla public.rol del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class Rol extends PdmModel
{
    protected $table = 'rol';

    protected $primaryKey = 'rol_id_rol';

    protected $fillable = [
        'rol_id_rol',
        'rol_nombre',
    ];

    protected $casts = [
        'rol_id_rol' => 'integer',
    ];

    /** fk_rol_priv_rol_privi_rol: rol_privilegio.ropr_id_rol → rol.rol_id_rol */
    public function rolPrivilegios(): HasMany
    {
        return $this->hasMany(RolPrivilegio::class, 'ropr_id_rol', 'rol_id_rol');
    }

    /** fk_usuario_tiene_rol_rol: usuario.usua_id_rol → rol.rol_id_rol */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'usua_id_rol', 'rol_id_rol');
    }
}
