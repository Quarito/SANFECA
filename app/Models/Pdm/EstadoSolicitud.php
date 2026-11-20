<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.estado_solicitud del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y anfeca_modelo_extensiones.sql.
 */
class EstadoSolicitud extends PdmModel
{
    protected $table = 'anfeca_modelo.estado_solicitud';

    protected $primaryKey = 'esso_id__estado_solicitud';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'esso_id__estado_solicitud',
        'esso_id_c_estado_solicitud',
        'esso_id_solicitud',
        'esso_descripcion',
        'esso_siglas',
        'esso_fecha',
        'esso_hora',
        'esso_motivo_rechazo',
    ];

    protected $casts = [
        'esso_id__estado_solicitud' => 'integer',
        'esso_id_c_estado_solicitud' => 'integer',
        'esso_id_solicitud' => 'integer',
        'esso_fecha' => 'date',
    ];

    /** FK FK_ESTADO_S_REFERENCE_SOLICITU: estado_solicitud.esso_id_solicitud → solicitud.soli_id_solicitud */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'esso_id_solicitud', 'soli_id_solicitud');
    }

    /** FK FK_ESTADO_S_RELATIONS_C_ESTADO: estado_solicitud.esso_id_c_estado_solicitud → c_estado_solicitud.ceso_id_c_estado_solicitud */
    public function cEstadoSolicitud(): BelongsTo
    {
        return $this->belongsTo(CEstadoSolicitud::class, 'esso_id_c_estado_solicitud', 'ceso_id_c_estado_solicitud');
    }
}
