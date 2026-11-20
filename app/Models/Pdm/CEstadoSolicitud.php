<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.c_estado_solicitud del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class CEstadoSolicitud extends PdmModel
{
    protected $table = 'anfeca_modelo.c_estado_solicitud';

    protected $primaryKey = 'ceso_id_c_estado_solicitud';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'ceso_id_c_estado_solicitud',
        'ceso_estado_solicitud',
    ];

    protected $casts = [
        'ceso_id_c_estado_solicitud' => 'integer',
    ];

    /** FK FK_ESTADO_S_RELATIONS_C_ESTADO: estado_solicitud.esso_id_c_estado_solicitud → c_estado_solicitud.ceso_id_c_estado_solicitud */
    public function estadoSolicitudes(): HasMany
    {
        return $this->hasMany(EstadoSolicitud::class, 'esso_id_c_estado_solicitud', 'ceso_id_c_estado_solicitud');
    }
}
