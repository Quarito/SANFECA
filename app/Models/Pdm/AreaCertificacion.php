<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla public.area_certificacion del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class AreaCertificacion extends PdmModel
{
    protected $table = 'area_certificacion';

    protected $primaryKey = 'area_id_area_certificacion';

    protected $fillable = [
        'area_id_area_certificacion',
        'area_descripcion',
    ];

    protected $casts = [
        'area_id_area_certificacion' => 'integer',
    ];

    /** fk_solicitu_pertenece_area_cer: solicitud.soli_id_area_certificacion → area_certificacion.area_id_area_certificacion */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'soli_id_area_certificacion', 'area_id_area_certificacion');
    }
}
