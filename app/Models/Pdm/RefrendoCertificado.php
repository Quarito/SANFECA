<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.refrendo_certificado del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class RefrendoCertificado extends PdmModel
{
    protected $table = 'anfeca_modelo.refrendo_certificado';

    protected $primaryKey = 'rece_id_refrendo_certificacion';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'rece_id_refrendo_certificacion',
        'rece_id_evaluacion',
        'rece_id_certificado',
        'rece_numero_refrendo',
        'rece_anio_refrendo',
        'rece_art14',
        'rece_art13',
    ];

    protected $casts = [
        'rece_id_refrendo_certificacion' => 'integer',
        'rece_id_evaluacion' => 'integer',
        'rece_id_certificado' => 'integer',
        'rece_numero_refrendo' => 'integer',
        'rece_anio_refrendo' => 'integer',
        'rece_art14' => 'boolean',
        'rece_art13' => 'boolean',
    ];

    /** FK FK_REFRENDO_RELATIONS_EVALUACI: refrendo_certificado.rece_id_evaluacion → evaluacion.eval_id_evaluacion */
    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class, 'rece_id_evaluacion', 'eval_id_evaluacion');
    }

    /** FK FK_REFRENDO_RELATIONS_CERTIFIC: refrendo_certificado.rece_id_certificado → certificado.cert_id_certificado */
    public function certificado(): BelongsTo
    {
        return $this->belongsTo(Certificado::class, 'rece_id_certificado', 'cert_id_certificado');
    }
}
