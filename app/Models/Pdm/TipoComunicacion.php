<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla public.tipo_comunicacion del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class TipoComunicacion extends PdmModel
{
    protected $table = 'tipo_comunicacion';

    protected $primaryKey = 'tico_id_tipo_comunicacion';

    protected $fillable = [
        'tico_id_tipo_comunicacion',
        'tico_tipo_comunicacion',
        'tico_estado',
    ];

    protected $casts = [
        'tico_id_tipo_comunicacion' => 'integer',
    ];

    /** fk_comunica_clasifica_tipo_com: comunicacion.comu_id_tipo_comunicacion → tipo_comunicacion.tico_id_tipo_comunicacion */
    public function comunicaciones(): HasMany
    {
        return $this->hasMany(Comunicacion::class, 'comu_id_tipo_comunicacion', 'tico_id_tipo_comunicacion');
    }
}
