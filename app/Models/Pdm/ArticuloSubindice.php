<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla public.articulo_subindice del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class ArticuloSubindice extends PdmModel
{
    protected $table = 'articulo_subindice';

    protected $primaryKey = 'arce_id_articulo';

    protected $fillable = [
        'arce_id_articulo',
        'arce_subindice',
        'arce_descripcion',
    ];

    protected $casts = [
        'arce_id_articulo' => 'integer',
    ];

    /** fk_solicitu_tiene_art_articulo: solicitud.soli_id_articulo → articulo_subindice.arce_id_articulo */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'soli_id_articulo', 'arce_id_articulo');
    }
}
