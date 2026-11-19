<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.estudio_profesional del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y anfeca_modelo_extensiones.sql.
 */
class EstudioProfesional extends PdmModel
{
    protected $table = 'anfeca_modelo.estudio_profesional';

    protected $primaryKey = 'espr_id_estudio_profesional';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'espr_id_estudio_profesional',
        'espr_id_nivel_profesional',
        'espr_nombre',
        'espr_estado',
    ];

    protected $casts = [
        'espr_id_estudio_profesional' => 'integer',
        'espr_id_nivel_profesional' => 'integer',
    ];

    /** FK FK_ESTUDIO__REFERENCE_NIVEL_PR: estudio_profesional.espr_id_nivel_profesional → nivel_profesional.nipr_id_nivel_profesional */
    public function nivelProfesional(): BelongsTo
    {
        return $this->belongsTo(NivelProfesional::class, 'espr_id_nivel_profesional', 'nipr_id_nivel_profesional');
    }

    /** FK FK_PERSONA__RELATIONS_ESTUDIO_: persona_estudio_profesional.pees_id_estuido_profesional → estudio_profesional.espr_id_estudio_profesional */
    public function personaEstudioProfesionales(): HasMany
    {
        return $this->hasMany(PersonaEstudioProfesional::class, 'pees_id_estuido_profesional', 'espr_id_estudio_profesional');
    }
}
