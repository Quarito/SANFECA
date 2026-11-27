<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla public.persona_estudio_profesional del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class PersonaEstudioProfesional extends PdmModel
{
    protected $table = 'persona_estudio_profesional';

    protected $primaryKey = 'pees_id_persona_estudio_profesi';

    /** PK compuesta del modelo oficial. */
    protected array $columnasClaveCompuesta = ['pees_id_estuido_profesional', 'pees_id_persona'];

    protected $fillable = [
        'pees_id_persona_estudio_profesi',
        'pees_id_estuido_profesional',
        'pees_id_persona',
        'pees_url_titulo',
        'pees_cedula',
        'pees_url_cedula',
    ];

    protected $casts = [
        'pees_id_persona_estudio_profesi' => 'integer',
        'pees_id_estuido_profesional' => 'integer',
        'pees_id_persona' => 'integer',
    ];

    /** fk_persona__relations_estudio_: persona_estudio_profesional.pees_id_estuido_profesional → estudio_profesional.espr_id_estudio_profesional */
    public function estudioProfesional(): BelongsTo
    {
        return $this->belongsTo(EstudioProfesional::class, 'pees_id_estuido_profesional', 'espr_id_estudio_profesional');
    }

    /** fk_persona__relations_persona: persona_estudio_profesional.pees_id_persona → persona.pers_id_persona */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'pees_id_persona', 'pers_id_persona');
    }
}
