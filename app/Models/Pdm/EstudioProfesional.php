<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla public.estudio_profesional del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class EstudioProfesional extends PdmModel
{
    protected $table = 'estudio_profesional';

    protected $primaryKey = 'espr_id_estudio_profesional';

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

    /** fk_estudio__reference_nivel_pr: estudio_profesional.espr_id_nivel_profesional → nivel_profesional.nipr_id_nivel_profesional */
    public function nivelProfesional(): BelongsTo
    {
        return $this->belongsTo(NivelProfesional::class, 'espr_id_nivel_profesional', 'nipr_id_nivel_profesional');
    }

    /** fk_persona__relations_estudio_: persona_estudio_profesional.pees_id_estuido_profesional → estudio_profesional.espr_id_estudio_profesional */
    public function personaEstudioProfesionales(): HasMany
    {
        return $this->hasMany(PersonaEstudioProfesional::class, 'pees_id_estuido_profesional', 'espr_id_estudio_profesional');
    }
}
