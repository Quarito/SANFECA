<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.persona_estudio_profesional del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 * La PK compuesta se aplica al recargar y guardar la instancia.
 */
class PersonaEstudioProfesional extends PdmModel
{
    protected $table = 'anfeca_modelo.persona_estudio_profesional';

    protected $primaryKey = 'pees_id_persona_estudio_profesi';

    protected array $columnasClaveCompuesta = ['pees_id_estuido_profesional', 'pees_id_persona'];

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'pees_id_persona_estudio_profesi',
        'pees_id_estuido_profesional',
        'pees_id_persona',
        'pees_url_titulo',
        'pees_cedula',
        'pees_url_cedula',
        'pees_id_solicitud',
        'pees_tipo',
    ];

    protected $casts = [
        'pees_id_persona_estudio_profesi' => 'integer',
        'pees_id_estuido_profesional' => 'integer',
        'pees_id_persona' => 'integer',
        'pees_id_solicitud' => 'integer',
    ];

    /** FK FK_PERSONA__RELATIONS_ESTUDIO_: persona_estudio_profesional.pees_id_estuido_profesional → estudio_profesional.espr_id_estudio_profesional */
    public function estudioProfesional(): BelongsTo
    {
        return $this->belongsTo(EstudioProfesional::class, 'pees_id_estuido_profesional', 'espr_id_estudio_profesional');
    }

    /** FK FK_PERSONA__RELATIONS_PERSONA: persona_estudio_profesional.pees_id_persona → persona.pers_id_persona */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'pees_id_persona', 'pers_id_persona');
    }

    /** FK FK_PEES_EXT_SOLICITUD: persona_estudio_profesional.pees_id_solicitud → solicitud.soli_id_solicitud */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'pees_id_solicitud', 'soli_id_solicitud');
    }
}
