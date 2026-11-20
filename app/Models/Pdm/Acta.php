<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.acta del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class Acta extends PdmModel
{
    protected $table = 'anfeca_modelo.acta';

    protected $primaryKey = 'acta_id_acta';

    public $incrementing = true;

    protected $keyType = 'int';

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

    /** FK FK_ACTA_PROPORCIO_SOLICITU: acta.acta_id_solicitud → solicitud.soli_id_solicitud */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'acta_id_solicitud', 'soli_id_solicitud');
    }
}
