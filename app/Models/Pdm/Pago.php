<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.pago del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y anfeca_modelo_extensiones.sql.
 */
class Pago extends PdmModel
{
    protected $table = 'anfeca_modelo.pago';

    protected $primaryKey = 'pago_id_pago';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'pago_id_pago',
        'pago_id_solicitud',
        'pago_url_comprobante',
    ];

    protected $casts = [
        'pago_id_pago' => 'integer',
        'pago_id_solicitud' => 'integer',
    ];

    /** FK FK_PAGO_REFERENCE_SOLICITU: pago.pago_id_solicitud → solicitud.soli_id_solicitud */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'pago_id_solicitud', 'soli_id_solicitud');
    }
}
