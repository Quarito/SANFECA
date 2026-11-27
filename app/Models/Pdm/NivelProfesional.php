<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla public.nivel_profesional del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class NivelProfesional extends PdmModel
{
    protected $table = 'nivel_profesional';

    protected $primaryKey = 'nipr_id_nivel_profesional';

    protected $fillable = [
        'nipr_id_nivel_profesional',
        'nipr_nivel_profesional',
    ];

    protected $casts = [
        'nipr_id_nivel_profesional' => 'integer',
    ];

    /** fk_estudio__reference_nivel_pr: estudio_profesional.espr_id_nivel_profesional → nivel_profesional.nipr_id_nivel_profesional */
    public function estudioProfesionales(): HasMany
    {
        return $this->hasMany(EstudioProfesional::class, 'espr_id_nivel_profesional', 'nipr_id_nivel_profesional');
    }
}
