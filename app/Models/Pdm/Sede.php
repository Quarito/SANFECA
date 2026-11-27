<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla public.sede del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class Sede extends PdmModel
{
    protected $table = 'sede';

    protected $primaryKey = 'sede_id_sede';

    protected $fillable = [
        'sede_id_sede',
        'inst_id_institucion',
        'sede_descripcion',
    ];

    protected $casts = [
        'sede_id_sede' => 'integer',
        'inst_id_institucion' => 'integer',
    ];

    /** fk_sede_tiene_sed_instituc: sede.inst_id_institucion → institucion.inst_id_institucion */
    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'inst_id_institucion', 'inst_id_institucion');
    }
}
