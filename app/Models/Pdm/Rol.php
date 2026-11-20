<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.rol del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y anfeca_modelo_extensiones.sql.
 */
class Rol extends PdmModel
{
    protected $table = 'anfeca_modelo.rol';

    protected $primaryKey = 'rol_id_rol';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'rol_id_rol',
        'rol_nombre',
    ];

    protected $casts = [
        'rol_id_rol' => 'integer',
    ];

    /** FK FK_ROL_PRIV_ROL_PRIVI_ROL: rol_privilegio.ropr_id_rol → rol.rol_id_rol */
    public function rolPrivilegios(): HasMany
    {
        return $this->hasMany(RolPrivilegio::class, 'ropr_id_rol', 'rol_id_rol');
    }

    /** FK FK_USUARIO_TIENE_ROL_ROL: usuario.usua_id_rol → rol.rol_id_rol */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'usua_id_rol', 'rol_id_rol');
    }
}
