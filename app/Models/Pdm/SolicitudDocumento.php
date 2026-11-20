<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.solicitud_documento del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y anfeca_modelo_extensiones.sql.
 */
class SolicitudDocumento extends PdmModel
{
    protected $table = 'anfeca_modelo.solicitud_documento';

    protected $primaryKey = 'socu_id_documentacion';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'socu_id_documentacion',
        'socu_id_solicitud',
        'docu_id_documento',
        'socu_fecha',
        'socu_hora',
        'socu_url_archivo',
    ];

    protected $casts = [
        'socu_id_documentacion' => 'integer',
        'socu_id_solicitud' => 'integer',
        'docu_id_documento' => 'integer',
        'socu_fecha' => 'date',
    ];

    /** FK FK_SOLICITU_RELATIONS_DOCUMENT: solicitud_documento.docu_id_documento → documento.docu_id_documento */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'docu_id_documento', 'docu_id_documento');
    }

    /** FK FK_SOLICITU_TIENE_AS_SOLICITU: solicitud_documento.socu_id_solicitud → solicitud.soli_id_solicitud */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'socu_id_solicitud', 'soli_id_solicitud');
    }

    /** FK FK_ESTADO_S_RELATIONS_SOLICITU: estado_solicitud_documento.esdo_id_documentacion → solicitud_documento.socu_id_documentacion */
    public function estadoSolicitudDocumentos(): HasMany
    {
        return $this->hasMany(EstadoSolicitudDocumento::class, 'esdo_id_documentacion', 'socu_id_documentacion');
    }
}
