<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.tipo_dictamen del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class TipoDictamen extends PdmModel
{
    protected $table = 'anfeca_modelo.tipo_dictamen';

    protected $primaryKey = 'tidi_id_tipo_dictamen';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'tidi_id_tipo_dictamen',
        'tidi_descripcion',
        'tidi_tipo_dictamen_estado',
    ];

    protected $casts = [
        'tidi_id_tipo_dictamen' => 'integer',
        'tidi_tipo_dictamen_estado' => 'boolean',
    ];

    /** FK FK_DICTAMEN_REFERENCE_TIPO_DIC: dictamen.dict_id_tipo_dictamen → tipo_dictamen.tidi_id_tipo_dictamen */
    public function dictamenes(): HasMany
    {
        return $this->hasMany(Dictamen::class, 'dict_id_tipo_dictamen', 'tidi_id_tipo_dictamen');
    }
}
