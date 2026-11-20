<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.domicilio del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class Domicilio extends PdmModel
{
    protected $table = 'anfeca_modelo.domicilio';

    protected $primaryKey = 'domi_id_domicilio';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'domi_id_domicilio',
        'domi_id_persona',
        'domi_id_entidad_federativa',
        'domi_calle',
        'domi_numero_ext',
        'domi_numero_int',
        'domi_colonia',
        'domi_municipio',
        'domi_codigo_postal',
    ];

    protected $casts = [
        'domi_id_domicilio' => 'integer',
        'domi_id_persona' => 'integer',
        'domi_id_entidad_federativa' => 'integer',
    ];

    /** FK FK_DOMICILI_SE_ENCUEN_ENTIDAD_: domicilio.domi_id_entidad_federativa → entidad_federativa.enfe_id_entidad_federativa */
    public function entidadFederativa(): BelongsTo
    {
        return $this->belongsTo(EntidadFederativa::class, 'domi_id_entidad_federativa', 'enfe_id_entidad_federativa');
    }

    /** FK FK_DOMICILI_TIENE_DOM_PERSONA: domicilio.domi_id_persona → persona.pers_id_persona */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'domi_id_persona', 'pers_id_persona');
    }
}
