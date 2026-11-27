<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla public.c_estado_solicitud del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class CEstadoSolicitud extends PdmModel
{
    protected $table = 'c_estado_solicitud';

    protected $primaryKey = 'ceso_id_c_estado_solicitud';

    protected $fillable = [
        'ceso_id_c_estado_solicitud',
        'ceso_estado_solicitud',
    ];

    protected $casts = [
        'ceso_id_c_estado_solicitud' => 'integer',
    ];

    /** fk_estado_s_relations_c_estado: estado_solicitud.esso_id_c_estado_solicitud → c_estado_solicitud.ceso_id_c_estado_solicitud */
    public function estadoSolicitudes(): HasMany
    {
        return $this->hasMany(EstadoSolicitud::class, 'esso_id_c_estado_solicitud', 'ceso_id_c_estado_solicitud');
    }
}
