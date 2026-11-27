<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla public.alcaldia_municipio del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class AlcaldiaMunicipio extends PdmModel
{
    protected $table = 'alcaldia_municipio';

    protected $primaryKey = 'almu_id_alcaldia';

    protected $fillable = [
        'almu_id_alcaldia',
        'almu_id_entidad_federativa',
        'almu_nombre',
    ];

    protected $casts = [
        'almu_id_alcaldia' => 'integer',
        'almu_id_entidad_federativa' => 'integer',
    ];

    /** fk_alcaldia_contiene_entidad_: alcaldia_municipio.almu_id_entidad_federativa → entidad_federativa.enfe_id_entidad_federativa */
    public function entidadFederativa(): BelongsTo
    {
        return $this->belongsTo(EntidadFederativa::class, 'almu_id_entidad_federativa', 'enfe_id_entidad_federativa');
    }

    /** fk_colonia_se_compon_alcaldia: colonia.colo_id_alcaldia → alcaldia_municipio.almu_id_alcaldia */
    public function colonias(): HasMany
    {
        return $this->hasMany(Colonia::class, 'colo_id_alcaldia', 'almu_id_alcaldia');
    }
}
