<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla public.pago del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class Pago extends PdmModel
{
    protected $table = 'pago';

    protected $primaryKey = 'pago_id_pago';

    protected $fillable = [
        'pago_id_pago',
        'pago_id_solicitud',
        'pago_url_comprobante',
    ];

    protected $casts = [
        'pago_id_pago' => 'integer',
        'pago_id_solicitud' => 'integer',
    ];

    /** fk_pago_reference_solicitu: pago.pago_id_solicitud → solicitud.soli_id_solicitud */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'pago_id_solicitud', 'soli_id_solicitud');
    }
}
