<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.comunicacion del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 * PK compuesta en el PDM (comu_id_comunicacion, cumu_id_persona, comu_id_tipo_comunicacion); Eloquent usa comu_id_comunicacion (SERIAL único).
 */
class Comunicacion extends PdmModel
{
    protected $table = 'anfeca_modelo.comunicacion';

    protected $primaryKey = 'comu_id_comunicacion';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'comu_id_comunicacion',
        'cumu_id_persona',
        'comu_id_tipo_comunicacion',
        'comu_valor',
        'comu_visible',
    ];

    protected $casts = [
        'comu_id_comunicacion' => 'integer',
        'cumu_id_persona' => 'integer',
        'comu_id_tipo_comunicacion' => 'integer',
        'comu_visible' => 'boolean',
    ];

    /** FK FK_COMUNICA_CLASIFICA_TIPO_COM: comunicacion.comu_id_tipo_comunicacion → tipo_comunicacion.tico_id_tipo_comunicacion */
    public function tipoComunicacion(): BelongsTo
    {
        return $this->belongsTo(TipoComunicacion::class, 'comu_id_tipo_comunicacion', 'tico_id_tipo_comunicacion');
    }

    /** FK FK_COMUNICA_TIENE_CON_PERSONA: comunicacion.cumu_id_persona → persona.pers_id_persona */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'cumu_id_persona', 'pers_id_persona');
    }
}
