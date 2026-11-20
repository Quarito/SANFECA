<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.entidad_federativa del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class EntidadFederativa extends PdmModel
{
    protected $table = 'anfeca_modelo.entidad_federativa';

    protected $primaryKey = 'enfe_id_entidad_federativa';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'enfe_id_entidad_federativa',
        'enfe_nombre',
    ];

    protected $casts = [
        'enfe_id_entidad_federativa' => 'integer',
    ];

    /** FK FK_ALCALDIA_CONTIENE_ENTIDAD_: alcaldia_municipio.almu_id_entidad_federativa → entidad_federativa.enfe_id_entidad_federativa */
    public function alcaldiaMunicipios(): HasMany
    {
        return $this->hasMany(AlcaldiaMunicipio::class, 'almu_id_entidad_federativa', 'enfe_id_entidad_federativa');
    }

    /** FK FK_DOMICILI_SE_ENCUEN_ENTIDAD_: domicilio.domi_id_entidad_federativa → entidad_federativa.enfe_id_entidad_federativa */
    public function domicilios(): HasMany
    {
        return $this->hasMany(Domicilio::class, 'domi_id_entidad_federativa', 'enfe_id_entidad_federativa');
    }
}
