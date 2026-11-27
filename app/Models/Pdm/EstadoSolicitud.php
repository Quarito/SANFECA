<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla public.estado_solicitud del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class EstadoSolicitud extends PdmModel
{
    protected $table = 'estado_solicitud';

    protected $primaryKey = 'esso_id__estado_solicitud';

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
    ];

    /** fk_estado_s_reference_solicitu: estado_solicitud.esso_id_solicitud → solicitud.soli_id_solicitud */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'esso_id_solicitud', 'soli_id_solicitud');
    }

    /** fk_estado_s_relations_c_estado: estado_solicitud.esso_id_c_estado_solicitud → c_estado_solicitud.ceso_id_c_estado_solicitud */
    public function cEstadoSolicitud(): BelongsTo
    {
        return $this->belongsTo(CEstadoSolicitud::class, 'esso_id_c_estado_solicitud', 'ceso_id_c_estado_solicitud');
    }
}
