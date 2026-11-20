<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.documentos_solicitud (EXTENSIÓN ANFECA, no está en el PDM original).
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class DocumentosSolicitud extends PdmModel
{
    protected $table = 'anfeca_modelo.documentos_solicitud';

    protected $primaryKey = 'dosc_id_documentos';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'dosc_id_documentos',
        'dosc_id_solicitud',
        'dosc_creado_en',
        'dosc_actualizado_en',
    ];

    protected $casts = [
        'dosc_id_documentos' => 'integer',
        'dosc_id_solicitud' => 'integer',
        'dosc_creado_en' => 'datetime',
        'dosc_actualizado_en' => 'datetime',
    ];

    /** FK FK_DOSC_SOLICITUD: documentos_solicitud.dosc_id_solicitud → solicitud.soli_id_solicitud */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'dosc_id_solicitud', 'soli_id_solicitud');
    }
}
