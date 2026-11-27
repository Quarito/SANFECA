<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla public.evaluacion del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class Evaluacion extends PdmModel
{
    protected $table = 'evaluacion';

    protected $primaryKey = 'eval_id_evaluacion';

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
    ];

    /** fk_certific_relations_evaluaci: certificado.cert_id_evaluacion → evaluacion.eval_id_evaluacion */
    public function certificados(): HasMany
    {
        return $this->hasMany(Certificado::class, 'cert_id_evaluacion', 'eval_id_evaluacion');
    }

    /** fk_dictamen_reference_evaluaci: dictamen.dict_id_evaluacion → evaluacion.eval_id_evaluacion */
    public function dictamenes(): HasMany
    {
        return $this->hasMany(Dictamen::class, 'dict_id_evaluacion', 'eval_id_evaluacion');
    }

    /** fk_evaluaci_relations_solicitu: evaluacion.eval_id_solicitud → solicitud.soli_id_solicitud */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'eval_id_solicitud', 'soli_id_solicitud');
    }

    /** fk_refrendo_relations_evaluaci: refrendo_certificado.rece_id_evaluacion → evaluacion.eval_id_evaluacion */
    public function refrendoCertificados(): HasMany
    {
        return $this->hasMany(RefrendoCertificado::class, 'rece_id_evaluacion', 'eval_id_evaluacion');
    }
}
