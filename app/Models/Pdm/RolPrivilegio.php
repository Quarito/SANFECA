<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.rol_privilegio del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class RolPrivilegio extends PdmModel
{
    protected $table = 'anfeca_modelo.rol_privilegio';

    protected $primaryKey = 'ropr_id_rol_privilegio';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'ropr_id_rol_privilegio',
        'ropr_id_rol',
        'ropr_id_privilegio',
    ];

    protected $casts = [
        'ropr_id_rol_privilegio' => 'integer',
        'ropr_id_rol' => 'integer',
        'ropr_id_privilegio' => 'integer',
    ];

    /** FK FK_ROL_PRIV_ROL_PRIVI_ROL: rol_privilegio.ropr_id_rol → rol.rol_id_rol */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'ropr_id_rol', 'rol_id_rol');
    }

    /** FK FK_ROL_PRIV_ROL_PRIVI_PRIVILEG: rol_privilegio.ropr_id_privilegio → privilegio.priv_id_privilegio */
    public function privilegio(): BelongsTo
    {
        return $this->belongsTo(Privilegio::class, 'ropr_id_privilegio', 'priv_id_privilegio');
    }
}
