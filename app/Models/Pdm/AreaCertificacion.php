<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.area_certificacion del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y anfeca_modelo_extensiones.sql.
 */
class AreaCertificacion extends PdmModel
{
    protected $table = 'anfeca_modelo.area_certificacion';

    protected $primaryKey = 'area_id_area_certificacion';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'area_id_area_certificacion',
        'area_descripcion',
    ];

    protected $casts = [
        'area_id_area_certificacion' => 'integer',
    ];

    /** FK FK_SOLICITU_PERTENECE_AREA_CER: solicitud.soli_id_area_certificacion → area_certificacion.area_id_area_certificacion */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'soli_id_area_certificacion', 'area_id_area_certificacion');
    }
}
