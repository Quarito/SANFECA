<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.estado_documento del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y anfeca_modelo_extensiones.sql.
 */
class EstadoDocumento extends PdmModel
{
    protected $table = 'anfeca_modelo.estado_documento';

    protected $primaryKey = 'esdo_id_estado_documento';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'esdo_id_estado_documento',
        'esdo_estado_documento',
        'esdo_estado',
    ];

    protected $casts = [
        'esdo_id_estado_documento' => 'integer',
    ];

    /** FK FK_ESTADO_S_RELATIONS_ESTADO_D: estado_solicitud_documento.esdo_id_estado_documento → estado_documento.esdo_id_estado_documento */
    public function estadoSolicitudDocumentos(): HasMany
    {
        return $this->hasMany(EstadoSolicitudDocumento::class, 'esdo_id_estado_documento', 'esdo_id_estado_documento');
    }
}
