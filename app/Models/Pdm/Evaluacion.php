<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.evaluacion del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y anfeca_modelo_extensiones.sql.
 */
class Evaluacion extends PdmModel
{
    protected $table = 'anfeca_modelo.evaluacion';

    protected $primaryKey = 'eval_id_evaluacion';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'eval_id_evaluacion',
        'eval_id_solicitud',
        'eval_puntuacion',
        'eval_fecha_evaluacion',
        'eval_hora_evaluacion',
    ];

    protected $casts = [
        'eval_id_evaluacion' => 'integer',
        'eval_id_solicitud' => 'integer',
        'eval_puntuacion' => 'integer',
        'eval_fecha_evaluacion' => 'date',
    ];

    /** FK FK_EVALUACI_RELATIONS_SOLICITU: evaluacion.eval_id_solicitud → solicitud.soli_id_solicitud */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'eval_id_solicitud', 'soli_id_solicitud');
    }

    /** FK FK_CERTIFIC_RELATIONS_EVALUACI: certificado.cert_id_evaluacion → evaluacion.eval_id_evaluacion */
    public function certificados(): HasMany
    {
        return $this->hasMany(Certificado::class, 'cert_id_evaluacion', 'eval_id_evaluacion');
    }

    /** FK FK_DICTAMEN_REFERENCE_EVALUACI: dictamen.dict_id_evaluacion → evaluacion.eval_id_evaluacion */
    public function dictamenes(): HasMany
    {
        return $this->hasMany(Dictamen::class, 'dict_id_evaluacion', 'eval_id_evaluacion');
    }

    /** FK FK_REFRENDO_RELATIONS_EVALUACI: refrendo_certificado.rece_id_evaluacion → evaluacion.eval_id_evaluacion */
    public function refrendoCertificados(): HasMany
    {
        return $this->hasMany(RefrendoCertificado::class, 'rece_id_evaluacion', 'eval_id_evaluacion');
    }
}
