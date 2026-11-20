<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.sede del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y anfeca_modelo_extensiones.sql.
 */
class Sede extends PdmModel
{
    protected $table = 'anfeca_modelo.sede';

    protected $primaryKey = 'sede_id_sede';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'sede_id_sede',
        'inst_id_institucion',
        'sede_descripcion',
    ];

    protected $casts = [
        'sede_id_sede' => 'integer',
        'inst_id_institucion' => 'integer',
    ];

    /** FK FK_SEDE_TIENE_SED_INSTITUC: sede.inst_id_institucion → institucion.inst_id_institucion */
    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'inst_id_institucion', 'inst_id_institucion');
    }
}
