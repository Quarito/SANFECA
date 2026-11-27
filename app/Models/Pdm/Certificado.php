<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla public.certificado del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class Certificado extends PdmModel
{
    protected $table = 'certificado';

    protected $primaryKey = 'cert_id_certificado';

    protected $fillable = [
        'cert_id_certificado',
        'cert_id_evaluacion',
        'cert_folio',
        'cert_anio',
        'cert_estado',
    ];

    protected $casts = [
        'cert_id_certificado' => 'integer',
        'cert_id_evaluacion' => 'integer',
    ];

    /** fk_certific_relations_evaluaci: certificado.cert_id_evaluacion → evaluacion.eval_id_evaluacion */
    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class, 'cert_id_evaluacion', 'eval_id_evaluacion');
    }

    /** fk_refrendo_relations_certific: refrendo_certificado.rece_id_certificado → certificado.cert_id_certificado */
    public function refrendoCertificados(): HasMany
    {
        return $this->hasMany(RefrendoCertificado::class, 'rece_id_certificado', 'cert_id_certificado');
    }
}
