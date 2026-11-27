<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla public.solicitud_documento del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class SolicitudDocumento extends PdmModel
{
    protected $table = 'solicitud_documento';

    protected $primaryKey = 'socu_id_documentacion';

    protected $fillable = [
        'socu_id_documentacion',
        'socu_id_solicitud',
        'socu_id_documento',
        'socu_fecha',
        'socu_hora',
    ];

    protected $casts = [
        'socu_id_documentacion' => 'integer',
        'socu_id_solicitud' => 'integer',
        'socu_id_documento' => 'integer',
    ];

    /** fk_estado_s_relations_solicitu: estado_solicitud_documento.esdo_id_documentacion → solicitud_documento.socu_id_documentacion */
    public function estadoSolicitudDocumentos(): HasMany
    {
        return $this->hasMany(EstadoSolicitudDocumento::class, 'esdo_id_documentacion', 'socu_id_documentacion');
    }

    /** fk_solicitu_relations_document: solicitud_documento.socu_id_documento → documento.docu_id_documento */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'socu_id_documento', 'docu_id_documento');
    }

    /** fk_solicitu_tiene_as_solicitu: solicitud_documento.socu_id_solicitud → solicitud.soli_id_solicitud */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'socu_id_solicitud', 'soli_id_solicitud');
    }
}
