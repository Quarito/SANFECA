<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AcuseSolicitud extends Model
{
    protected $table = 'acuses_solicitud';

    protected $fillable = ['solicitud_id', 'version', 'datos'];

    protected function casts(): array
    {
        return ['datos' => 'array', 'version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Los datos de un acuse emitido no pueden modificarse.');
        });
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCertificacion::class, 'solicitud_id');
    }
}
