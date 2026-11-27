<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla public.entidad_federativa del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class EntidadFederativa extends PdmModel
{
    protected $table = 'entidad_federativa';

    protected $primaryKey = 'enfe_id_entidad_federativa';

    protected $fillable = [
        'enfe_id_entidad_federativa',
        'enfe_nombre',
    ];

    protected $casts = [
        'enfe_id_entidad_federativa' => 'integer',
    ];

    /** fk_alcaldia_contiene_entidad_: alcaldia_municipio.almu_id_entidad_federativa → entidad_federativa.enfe_id_entidad_federativa */
    public function alcaldiaMunicipios(): HasMany
    {
        return $this->hasMany(AlcaldiaMunicipio::class, 'almu_id_entidad_federativa', 'enfe_id_entidad_federativa');
    }

    /** fk_domicili_se_encuen_entidad_: domicilio.domi_id_entidad_federativa → entidad_federativa.enfe_id_entidad_federativa */
    public function domicilios(): HasMany
    {
        return $this->hasMany(Domicilio::class, 'domi_id_entidad_federativa', 'enfe_id_entidad_federativa');
    }
}
