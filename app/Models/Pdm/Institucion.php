<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla public.institucion del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class Institucion extends PdmModel
{
    protected $table = 'institucion';

    protected $primaryKey = 'inst_id_institucion';

    protected $fillable = [
        'inst_id_institucion',
        'inst_numero_afiliacion',
        'inst_nombre',
        'inst_facultad',
        'inst_zona',
        'ins_coord_regional_cert_academi',
    ];

    protected $casts = [
        'inst_id_institucion' => 'integer',
    ];

    /** fk_persona_pertenece_instituc: persona.pers_id_institucion → institucion.inst_id_institucion */
    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class, 'pers_id_institucion', 'inst_id_institucion');
    }

    /** fk_sede_tiene_sed_instituc: sede.inst_id_institucion → institucion.inst_id_institucion */
    public function sedes(): HasMany
    {
        return $this->hasMany(Sede::class, 'inst_id_institucion', 'inst_id_institucion');
    }
}
