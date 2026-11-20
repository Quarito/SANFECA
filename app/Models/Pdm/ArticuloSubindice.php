<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.articulo_subindice del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class ArticuloSubindice extends PdmModel
{
    protected $table = 'anfeca_modelo.articulo_subindice';

    protected $primaryKey = 'arce_id_articulo';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'arce_id_articulo',
        'arce_subindice',
        'arce_descripcion',
    ];

    protected $casts = [
        'arce_id_articulo' => 'integer',
    ];

    /** FK FK_SOLICITU_TIENE_ART_ARTICULO: solicitud.soli_id_articulo → articulo_subindice.arce_id_articulo */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'soli_id_articulo', 'arce_id_articulo');
    }
}
