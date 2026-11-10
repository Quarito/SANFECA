<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoProbatorioSolicitud extends Model
{
    protected $table = 'documentos_probatorios_solicitud';

    protected $fillable = [
        'solicitud_id',
        'constancia_antiguedad_archivo',
        'comprobante_pago_archivo',
        'link',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(
            SolicitudCertificacion::class,
            'solicitud_id'
        );
    }
}
