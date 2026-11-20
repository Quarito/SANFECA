<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.tipo_comunicacion del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class TipoComunicacion extends PdmModel
{
    protected $table = 'anfeca_modelo.tipo_comunicacion';

    protected $primaryKey = 'tico_id_tipo_comunicacion';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'tico_id_tipo_comunicacion',
        'tico_tipo_comunicacion',
        'tico_estado',
    ];

    protected $casts = [
        'tico_id_tipo_comunicacion' => 'integer',
    ];

    /** FK FK_COMUNICA_CLASIFICA_TIPO_COM: comunicacion.comu_id_tipo_comunicacion → tipo_comunicacion.tico_id_tipo_comunicacion */
    public function comunicaciones(): HasMany
    {
        return $this->hasMany(Comunicacion::class, 'comu_id_tipo_comunicacion', 'tico_id_tipo_comunicacion');
    }
}
