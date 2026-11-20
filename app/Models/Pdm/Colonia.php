<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.colonia del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class Colonia extends PdmModel
{
    protected $table = 'anfeca_modelo.colonia';

    protected $primaryKey = 'colo_id_colonia';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'colo_id_colonia',
        'colo_id_alcaldia',
        'colo_nombre',
        'colo_codigo_postal',
    ];

    protected $casts = [
        'colo_id_colonia' => 'integer',
        'colo_id_alcaldia' => 'integer',
    ];

    /** FK FK_COLONIA_SE_COMPON_ALCALDIA: colonia.colo_id_alcaldia → alcaldia_municipio.almu_id_alcaldia */
    public function alcaldiaMunicipio(): BelongsTo
    {
        return $this->belongsTo(AlcaldiaMunicipio::class, 'colo_id_alcaldia', 'almu_id_alcaldia');
    }
}
