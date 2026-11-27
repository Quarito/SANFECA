<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla public.dictamen del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class Dictamen extends PdmModel
{
    protected $table = 'dictamen';

    protected $primaryKey = 'dict_id_dictamen';

    protected $fillable = [
        'dict_id_dictamen',
        'dict_id_tipo_dictamen',
        'dict_id_evaluacion',
        'dict_encabezado',
        'dict_folio_dictamen',
        'dict_fecha_dictamen_creacion',
        'dict_hora_dictamen_creacion',
        'dict_fecha_dictamen_ultima_impr',
    ];

    protected $casts = [
        'dict_id_dictamen' => 'integer',
        'dict_id_tipo_dictamen' => 'integer',
        'dict_id_evaluacion' => 'integer',
    ];

    /** fk_dictamen_reference_tipo_dic: dictamen.dict_id_tipo_dictamen → tipo_dictamen.tidi_id_tipo_dictamen */
    public function tipoDictamen(): BelongsTo
    {
        return $this->belongsTo(TipoDictamen::class, 'dict_id_tipo_dictamen', 'tidi_id_tipo_dictamen');
    }

    /** fk_dictamen_reference_evaluaci: dictamen.dict_id_evaluacion → evaluacion.eval_id_evaluacion */
    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class, 'dict_id_evaluacion', 'eval_id_evaluacion');
    }
}
