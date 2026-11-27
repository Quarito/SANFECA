<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla public.tipo_dictamen del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class TipoDictamen extends PdmModel
{
    protected $table = 'tipo_dictamen';

    protected $primaryKey = 'tidi_id_tipo_dictamen';

    protected $fillable = [
        'tidi_id_tipo_dictamen',
        'tidi_descripcion',
        'tidi_tipo_dictamen_estado',
    ];

    protected $casts = [
        'tidi_id_tipo_dictamen' => 'integer',
        'tidi_tipo_dictamen_estado' => 'boolean',
    ];

    /** fk_dictamen_reference_tipo_dic: dictamen.dict_id_tipo_dictamen → tipo_dictamen.tidi_id_tipo_dictamen */
    public function dictamenes(): HasMany
    {
        return $this->hasMany(Dictamen::class, 'dict_id_tipo_dictamen', 'tidi_id_tipo_dictamen');
    }
}
