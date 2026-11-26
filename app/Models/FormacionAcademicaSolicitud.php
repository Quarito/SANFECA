<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormacionAcademicaSolicitud extends Model
{
    protected $table = 'formaciones_academicas_solicitud';

    protected $fillable = [
        'solicitud_id',
        'nivel_origen',
        'titulo_origen',
        'titulo_origen_otro',
        'nivel_ultimo_grado',
        'titulo_ultimo_grado',
        'titulo_ultimo_grado_otro',
        'institucion_afiliada_id',
        'titulo_origen_archivo',
        'cedula_origen_archivo',
        'titulo_ultimo_grado_archivo',
        'cedula_ultimo_grado_archivo',
        'formacion_completada_at',
        // Columnas aditivas del catálogo de posgrados (solo se asignan si la migración está aplicada).
        'titulo_ultimo_grado_especifico',
        'origen_titulo_ultimo_grado',
        'clave_posgrado_catalogo',
        'version_catalogo_posgrados',
        // Columnas aditivas del catálogo de licenciaturas y declaración de posgrado (migración 2026_11_28_000000).
        'titulo_origen_especifico',
        'origen_titulo_origen',
        'clave_licenciatura_catalogo',
        'version_catalogo_licenciaturas',
        'cuenta_con_posgrado',
    ];

    protected $casts = [
        // TRUE = licenciatura con posgrado · FALSE = solo licenciatura · NULL = histórico o no declarado.
        'cuenta_con_posgrado' => 'boolean',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCertificacion::class, 'solicitud_id');
    }

    public function institucionAfiliada(): BelongsTo
    {
        return $this->belongsTo(
            InstitucionAfiliada::class,
            'institucion_afiliada_id'
        );
    }
}
