<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla public.acta del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class Acta extends PdmModel
{
    protected $table = 'acta';

    protected $primaryKey = 'acta_id_acta';

    protected $fillable = [
        'acta_id_acta',
        'acta_id_solicitud',
        'acta_coordinador',
        'acta_secretario',
        'acta_sede',
    ];

    protected $casts = [
        'acta_id_acta' => 'integer',
        'acta_id_solicitud' => 'integer',
    ];

    /** fk_acta_proporcio_solicitu: acta.acta_id_solicitud → solicitud.soli_id_solicitud */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'acta_id_solicitud', 'soli_id_solicitud');
    }
}
