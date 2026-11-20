<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.privilegio del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class Privilegio extends PdmModel
{
    protected $table = 'anfeca_modelo.privilegio';

    protected $primaryKey = 'priv_id_privilegio';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'priv_id_privilegio',
        'priv_nombre',
        'priv_descripcion',
    ];

    protected $casts = [
        'priv_id_privilegio' => 'integer',
    ];

    /** FK FK_ROL_PRIV_ROL_PRIVI_PRIVILEG: rol_privilegio.ropr_id_privilegio → privilegio.priv_id_privilegio */
    public function rolPrivilegios(): HasMany
    {
        return $this->hasMany(RolPrivilegio::class, 'ropr_id_privilegio', 'priv_id_privilegio');
    }
}
