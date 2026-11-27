<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla public.rol_privilegio del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class RolPrivilegio extends PdmModel
{
    protected $table = 'rol_privilegio';

    protected $primaryKey = 'ropr_id_rol_privilegio';

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

    /** fk_rol_priv_rol_privi_rol: rol_privilegio.ropr_id_rol → rol.rol_id_rol */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'ropr_id_rol', 'rol_id_rol');
    }

    /** fk_rol_priv_rol_privi_privileg: rol_privilegio.ropr_id_privilegio → privilegio.priv_id_privilegio */
    public function privilegio(): BelongsTo
    {
        return $this->belongsTo(Privilegio::class, 'ropr_id_privilegio', 'priv_id_privilegio');
    }
}
