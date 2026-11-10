<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatoRefrendo extends Model
{
    protected $table = 'datos_refrendo';

    protected $fillable = [
        'solicitud_id',
        'numero_refrendo_asignado',
        'refrendo_actual',
        'aplica_articulo_14',
        'inciso_articulo_14',
    ];

    protected $casts = [
        'refrendo_actual' => 'integer',
        'aplica_articulo_14' => 'boolean',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCertificacion::class, 'solicitud_id');
    }
}
