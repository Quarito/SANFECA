<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.institucion del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class Institucion extends PdmModel
{
    protected $table = 'anfeca_modelo.institucion';

    protected $primaryKey = 'inst_id_institucion';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'inst_id_institucion',
        'inst_numero_afiliacion',
        'inst_nombre',
        'inst_facultad',
        'inst_zona',
        'ins_coord_regional_cert_academi',
        'inst_id_zona',
        'inst_campus',
    ];

    protected $casts = [
        'inst_id_institucion' => 'integer',
        'inst_id_zona' => 'integer',
    ];

    /** FK FK_INSTITUC_EXT_ZONA: institucion.inst_id_zona → zona.zona_id_zona */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'inst_id_zona', 'zona_id_zona');
    }

    /** FK FK_PERSONA_PERTENECE_INSTITUC: persona.pers_id_institucion → institucion.inst_id_institucion */
    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class, 'pers_id_institucion', 'inst_id_institucion');
    }

    /** FK FK_SEDE_TIENE_SED_INSTITUC: sede.inst_id_institucion → institucion.inst_id_institucion */
    public function sedes(): HasMany
    {
        return $this->hasMany(Sede::class, 'inst_id_institucion', 'inst_id_institucion');
    }

    /** FK FK_FOSC_INSTITUCION: formacion_solicitud.fosc_id_institucion → institucion.inst_id_institucion */
    public function formacionSolicitudes(): HasMany
    {
        return $this->hasMany(FormacionSolicitud::class, 'fosc_id_institucion', 'inst_id_institucion');
    }
}
