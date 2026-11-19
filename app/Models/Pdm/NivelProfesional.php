<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.nivel_profesional del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y anfeca_modelo_extensiones.sql.
 */
class NivelProfesional extends PdmModel
{
    protected $table = 'anfeca_modelo.nivel_profesional';

    protected $primaryKey = 'nipr_id_nivel_profesional';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'nipr_id_nivel_profesional',
        'nipr_nivel_profesional',
    ];

    protected $casts = [
        'nipr_id_nivel_profesional' => 'integer',
    ];

    /** FK FK_ESTUDIO__REFERENCE_NIVEL_PR: estudio_profesional.espr_id_nivel_profesional → nivel_profesional.nipr_id_nivel_profesional */
    public function estudioProfesionales(): HasMany
    {
        return $this->hasMany(EstudioProfesional::class, 'espr_id_nivel_profesional', 'nipr_id_nivel_profesional');
    }
}
