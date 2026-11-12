<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SolicitudCertificacion extends Model
{
    protected $table = 'solicitudes_certificacion';

    protected $fillable = [
    'user_id',
    'tipo_tramite',
    'certificacion',
    'estado',
    'fecha_envio',
    'certificado_historico_id',
];

    protected $casts = [
        'fecha_envio' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function certificadoHistorico(): BelongsTo
    {
        return $this->belongsTo(
        CertificadoHistorico::class,
        'certificado_historico_id'
    );
    }

    public function datosRefrendo(): HasOne
    {
        return $this->hasOne(DatoRefrendo::class, 'solicitud_id');
    }

    public function formacionAcademica(): HasOne
    {
        return $this->hasOne(FormacionAcademicaSolicitud::class, 'solicitud_id');
    }

    public function documentosProbatorios(): HasOne
    {
        return $this->hasOne(DocumentoProbatorioSolicitud::class, 'solicitud_id');
    }
}
