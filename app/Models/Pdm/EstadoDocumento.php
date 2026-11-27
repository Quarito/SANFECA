<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla public.estado_documento del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class EstadoDocumento extends PdmModel
{
    protected $table = 'estado_documento';

    protected $primaryKey = 'esdo_id_estado_documento';

    protected $fillable = [
        'esdo_id_estado_documento',
        'esdo_estado_documento',
        'esdo_estado',
    ];

    protected $casts = [
        'esdo_id_estado_documento' => 'integer',
    ];

    /** fk_estado_s_relations_estado_d: estado_solicitud_documento.esdo_id_estado_documento → estado_documento.esdo_id_estado_documento */
    public function estadoSolicitudDocumentos(): HasMany
    {
        return $this->hasMany(EstadoSolicitudDocumento::class, 'esdo_id_estado_documento', 'esdo_id_estado_documento');
    }
}
