<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.estado_solicitud_documento del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y anfeca_modelo_extensiones.sql.
 */
class EstadoSolicitudDocumento extends PdmModel
{
    protected $table = 'anfeca_modelo.estado_solicitud_documento';

    protected $primaryKey = 'esdo_id_estado_solicitud_docume';

    public $incrementing = true;

    protected $keyType = 'int';

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
        'esdo_fecha' => 'date',
    ];

    /** FK FK_ESTADO_S_RELATIONS_SOLICITU: estado_solicitud_documento.esdo_id_documentacion → solicitud_documento.socu_id_documentacion */
    public function solicitudDocumento(): BelongsTo
    {
        return $this->belongsTo(SolicitudDocumento::class, 'esdo_id_documentacion', 'socu_id_documentacion');
    }

    /** FK FK_ESTADO_S_RELATIONS_ESTADO_D: estado_solicitud_documento.esdo_id_estado_documento → estado_documento.esdo_id_estado_documento */
    public function estadoDocumento(): BelongsTo
    {
        return $this->belongsTo(EstadoDocumento::class, 'esdo_id_estado_documento', 'esdo_id_estado_documento');
    }
}
