<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla public.privilegio del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class Privilegio extends PdmModel
{
    protected $table = 'privilegio';

    protected $primaryKey = 'priv_id_privilegio';

    protected $fillable = [
        'priv_id_privilegio',
        'priv_nombre',
        'priv_descripcion',
    ];

    protected $casts = [
        'priv_id_privilegio' => 'integer',
    ];

    /** fk_rol_priv_rol_privi_privileg: rol_privilegio.ropr_id_privilegio → privilegio.priv_id_privilegio */
    public function rolPrivilegios(): HasMany
    {
        return $this->hasMany(RolPrivilegio::class, 'ropr_id_privilegio', 'priv_id_privilegio');
    }
}
