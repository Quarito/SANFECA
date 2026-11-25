<?php

namespace App\Models;

use App\Enums\ResultadoRevision;
use App\Services\Coordinacion\VersionDocumento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Evento inmutable del historial de revisión documental (tabla de SOLO INSERCIÓN).
 * La base de datos lo protege con disparadores; el modelo lo refuerza en la aplicación.
 */
class RevisionDocumental extends Model
{
    protected $table = 'revisiones_documentales';

    public $timestamps = false;

    /** Nada es asignable masivamente: el servicio fija cada columna explícitamente. */
    protected $guarded = ['*'];

    protected $casts = [
        'resultado' => ResultadoRevision::class,
        'revisado_en' => 'immutable_datetime',
        'version_bytes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('revisiones_documentales es de solo inserción.'));
        static::deleting(fn () => throw new LogicException('revisiones_documentales es de solo inserción.'));
    }

    public function coordinador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinador_id');
    }

    public function anterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revision_anterior_id');
    }

    /** Revisiones de un documento. */
    public function scopeDelDocumento(Builder $q, int $solicitud, string $campo): Builder
    {
        return $q->where('solicitud_id', $solicitud)->where('documento_clave', $campo);
    }

    /** Vigentes: las que no tienen sucesor (equivale a la vista revisiones_documentales_vigentes). */
    public function scopeVigentes(Builder $q): Builder
    {
        return $q->whereNotExists(fn ($s) => $s->from('revisiones_documentales as sucesor')
            ->whereColumn('sucesor.revision_anterior_id', 'revisiones_documentales.id'));
    }

    /** Versión documental que se revisó. */
    public function version(): VersionDocumento
    {
        return new VersionDocumento($this->archivo_estado, $this->version_ruta, $this->version_sha256, $this->version_bytes);
    }
}
