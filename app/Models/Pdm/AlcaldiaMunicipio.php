<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.alcaldia_municipio del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class AlcaldiaMunicipio extends PdmModel
{
    protected $table = 'anfeca_modelo.alcaldia_municipio';

    protected $primaryKey = 'almu_id_alcaldia';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'almu_id_alcaldia',
        'almu_id_entidad_federativa',
        'almu_nombre',
    ];

    protected $casts = [
        'almu_id_alcaldia' => 'integer',
        'almu_id_entidad_federativa' => 'integer',
    ];

    /** FK FK_ALCALDIA_CONTIENE_ENTIDAD_: alcaldia_municipio.almu_id_entidad_federativa → entidad_federativa.enfe_id_entidad_federativa */
    public function entidadFederativa(): BelongsTo
    {
        return $this->belongsTo(EntidadFederativa::class, 'almu_id_entidad_federativa', 'enfe_id_entidad_federativa');
    }

    /** FK FK_COLONIA_SE_COMPON_ALCALDIA: colonia.colo_id_alcaldia → alcaldia_municipio.almu_id_alcaldia */
    public function colonias(): HasMany
    {
        return $this->hasMany(Colonia::class, 'colo_id_alcaldia', 'almu_id_alcaldia');
    }
}
