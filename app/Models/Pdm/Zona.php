<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.zona (EXTENSIÓN ANFECA, no está en el PDM original).
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class Zona extends PdmModel
{
    protected $table = 'anfeca_modelo.zona';

    protected $primaryKey = 'zona_id_zona';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'zona_id_zona',
        'zona_siglas',
        'zona_descripcion',
    ];

    protected $casts = [
        'zona_id_zona' => 'integer',
    ];

    /** FK FK_COORDZON_ZONA: coordinador_zona.cozo_id_zona → zona.zona_id_zona */
    public function coordinadorZonas(): HasMany
    {
        return $this->hasMany(CoordinadorZona::class, 'cozo_id_zona', 'zona_id_zona');
    }

    /** FK FK_INSTITUC_EXT_ZONA: institucion.inst_id_zona → zona.zona_id_zona */
    public function instituciones(): HasMany
    {
        return $this->hasMany(Institucion::class, 'inst_id_zona', 'zona_id_zona');
    }
}
