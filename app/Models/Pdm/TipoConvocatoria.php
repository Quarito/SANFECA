<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla public.tipo_convocatoria del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class TipoConvocatoria extends PdmModel
{
    protected $table = 'tipo_convocatoria';

    protected $primaryKey = 'tico_id_tipo_convocatoria';

    protected $fillable = [
        'tico_id_tipo_convocatoria',
        'tico_tipo_convocatoria',
        'tico_estado',
        'tico_siglas',
    ];

    protected $casts = [
        'tico_id_tipo_convocatoria' => 'integer',
    ];

    /** fk_convocat_relations_tipo_con: convocatoria.conv_id_tipo_convocatoria → tipo_convocatoria.tico_id_tipo_convocatoria */
    public function convocatorias(): HasMany
    {
        return $this->hasMany(Convocatoria::class, 'conv_id_tipo_convocatoria', 'tico_id_tipo_convocatoria');
    }
}
