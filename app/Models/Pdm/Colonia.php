<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla public.colonia del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class Colonia extends PdmModel
{
    protected $table = 'colonia';

    protected $primaryKey = 'colo_id_colonia';

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

    /** fk_colonia_se_compon_alcaldia: colonia.colo_id_alcaldia → alcaldia_municipio.almu_id_alcaldia */
    public function alcaldiaMunicipio(): BelongsTo
    {
        return $this->belongsTo(AlcaldiaMunicipio::class, 'colo_id_alcaldia', 'almu_id_alcaldia');
    }
}
