<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.dictamen del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y anfeca_modelo_extensiones.sql.
 */
class Dictamen extends PdmModel
{
    protected $table = 'anfeca_modelo.dictamen';

    protected $primaryKey = 'dict_id_dictamen';

    public $incrementing = true;

    protected $keyType = 'int';

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
        'dict_fecha_dictamen_creacion' => 'date',
        'dict_fecha_dictamen_ultima_impr' => 'date',
    ];

    /** FK FK_DICTAMEN_REFERENCE_TIPO_DIC: dictamen.dict_id_tipo_dictamen → tipo_dictamen.tidi_id_tipo_dictamen */
    public function tipoDictamen(): BelongsTo
    {
        return $this->belongsTo(TipoDictamen::class, 'dict_id_tipo_dictamen', 'tidi_id_tipo_dictamen');
    }

    /** FK FK_DICTAMEN_REFERENCE_EVALUACI: dictamen.dict_id_evaluacion → evaluacion.eval_id_evaluacion */
    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class, 'dict_id_evaluacion', 'eval_id_evaluacion');
    }
}
