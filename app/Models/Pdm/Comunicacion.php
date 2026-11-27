<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla public.comunicacion del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class Comunicacion extends PdmModel
{
    protected $table = 'comunicacion';

    protected $primaryKey = 'comu_id_comunicacion';

    /** PK compuesta del modelo oficial. */
    protected array $columnasClaveCompuesta = ['comu_id_persona', 'comu_id_tipo_comunicacion'];

    protected $fillable = [
        'comu_id_comunicacion',
        'comu_id_persona',
        'comu_id_tipo_comunicacion',
        'comu_valor',
        'comu_visible',
    ];

    protected $casts = [
        'comu_id_comunicacion' => 'integer',
        'comu_id_persona' => 'integer',
        'comu_id_tipo_comunicacion' => 'integer',
        'comu_visible' => 'boolean',
    ];

    /** fk_comunica_clasifica_tipo_com: comunicacion.comu_id_tipo_comunicacion → tipo_comunicacion.tico_id_tipo_comunicacion */
    public function tipoComunicacion(): BelongsTo
    {
        return $this->belongsTo(TipoComunicacion::class, 'comu_id_tipo_comunicacion', 'tico_id_tipo_comunicacion');
    }

    /** fk_comunica_tiene_con_persona: comunicacion.comu_id_persona → persona.pers_id_persona */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'comu_id_persona', 'pers_id_persona');
    }
}
