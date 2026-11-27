<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla public.domicilio del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class Domicilio extends PdmModel
{
    protected $table = 'domicilio';

    protected $primaryKey = 'domi_id_domicilio';

    protected $fillable = [
        'domi_id_domicilio',
        'domi_id_persona',
        'domi_id_entidad_federativa',
        'domi_calle',
        'domi_numero_ext',
        'domi_numero_int',
    ];

    protected $casts = [
        'domi_id_domicilio' => 'integer',
        'domi_id_persona' => 'integer',
        'domi_id_entidad_federativa' => 'integer',
    ];

    /** fk_domicili_se_encuen_entidad_: domicilio.domi_id_entidad_federativa → entidad_federativa.enfe_id_entidad_federativa */
    public function entidadFederativa(): BelongsTo
    {
        return $this->belongsTo(EntidadFederativa::class, 'domi_id_entidad_federativa', 'enfe_id_entidad_federativa');
    }

    /** fk_domicili_tiene_dom_persona: domicilio.domi_id_persona → persona.pers_id_persona */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'domi_id_persona', 'pers_id_persona');
    }
}
