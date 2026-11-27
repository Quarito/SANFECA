<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla public.estado_solicitud_documento del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class EstadoSolicitudDocumento extends PdmModel
{
    protected $table = 'estado_solicitud_documento';

    protected $primaryKey = 'esdo_id_estado_solicitud_docume';

    protected $fillable = [
        'esdo_id_estado_solicitud_docume',
        'esdo_id_documentacion',
        'esdo_id_estado_documento',
        'esdo_observacion',
        'esdo_fecha',
        'esdo_hora',
    ];

    protected $casts = [
        'esdo_id_estado_solicitud_docume' => 'integer',
        'esdo_id_documentacion' => 'integer',
        'esdo_id_estado_documento' => 'integer',
    ];

    /** fk_estado_s_relations_solicitu: estado_solicitud_documento.esdo_id_documentacion → solicitud_documento.socu_id_documentacion */
    public function solicitudDocumento(): BelongsTo
    {
        return $this->belongsTo(SolicitudDocumento::class, 'esdo_id_documentacion', 'socu_id_documentacion');
    }

    /** fk_estado_s_relations_estado_d: estado_solicitud_documento.esdo_id_estado_documento → estado_documento.esdo_id_estado_documento */
    public function estadoDocumento(): BelongsTo
    {
        return $this->belongsTo(EstadoDocumento::class, 'esdo_id_estado_documento', 'esdo_id_estado_documento');
    }
}
