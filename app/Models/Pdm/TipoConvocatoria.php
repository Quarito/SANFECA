<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.tipo_convocatoria del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class TipoConvocatoria extends PdmModel
{
    protected $table = 'anfeca_modelo.tipo_convocatoria';

    protected $primaryKey = 'tico_id_tipo_convocatoria';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'tico_id_tipo_convocatoria',
        'tico_tipo_convocatoria',
        'tico_estado',
        'tico_siglas',
    ];

    protected $casts = [
        'tico_id_tipo_convocatoria' => 'integer',
    ];

    /** FK FK_CONVOCAT_RELATIONS_TIPO_CON: convocatoria.conv_id_tipo_convocatoria → tipo_convocatoria.tico_id_tipo_convocatoria */
    public function convocatorias(): HasMany
    {
        return $this->hasMany(Convocatoria::class, 'conv_id_tipo_convocatoria', 'tico_id_tipo_convocatoria');
    }
}
