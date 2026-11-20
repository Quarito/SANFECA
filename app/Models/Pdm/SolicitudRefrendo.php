<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.solicitud_refrendo (EXTENSIÓN ANFECA, no está en el PDM original).
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class SolicitudRefrendo extends PdmModel
{
    protected $table = 'anfeca_modelo.solicitud_refrendo';

    protected $primaryKey = 'sore_id_solicitud_refrendo';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'sore_id_solicitud_refrendo',
        'sore_id_solicitud',
        'sore_numero_refrendo_asignado',
        'sore_refrendo_actual',
        'sore_creado_en',
        'sore_actualizado_en',
    ];

    protected $casts = [
        'sore_id_solicitud_refrendo' => 'integer',
        'sore_id_solicitud' => 'integer',
        'sore_refrendo_actual' => 'integer',
        'sore_creado_en' => 'datetime',
        'sore_actualizado_en' => 'datetime',
    ];

    /** FK FK_SORE_SOLICITUD: solicitud_refrendo.sore_id_solicitud → solicitud.soli_id_solicitud */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'sore_id_solicitud', 'soli_id_solicitud');
    }
}
