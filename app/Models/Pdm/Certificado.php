<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.certificado del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y anfeca_modelo_extensiones.sql.
 */
class Certificado extends PdmModel
{
    protected $table = 'anfeca_modelo.certificado';

    protected $primaryKey = 'cert_id_certificado';

    public $incrementing = true;

    protected $keyType = 'int';

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

    /** FK FK_CERTIFIC_RELATIONS_EVALUACI: certificado.cert_id_evaluacion → evaluacion.eval_id_evaluacion */
    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class, 'cert_id_evaluacion', 'eval_id_evaluacion');
    }

    /** FK FK_REFRENDO_RELATIONS_CERTIFIC: refrendo_certificado.rece_id_certificado → certificado.cert_id_certificado */
    public function refrendoCertificados(): HasMany
    {
        return $this->hasMany(RefrendoCertificado::class, 'rece_id_certificado', 'cert_id_certificado');
    }
}
