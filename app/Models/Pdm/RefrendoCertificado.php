<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla public.refrendo_certificado del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class RefrendoCertificado extends PdmModel
{
    protected $table = 'refrendo_certificado';

    protected $primaryKey = 'rece_id_refrendo_certificacion';

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

    /** fk_refrendo_relations_evaluaci: refrendo_certificado.rece_id_evaluacion → evaluacion.eval_id_evaluacion */
    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class, 'rece_id_evaluacion', 'eval_id_evaluacion');
    }

    /** fk_refrendo_relations_certific: refrendo_certificado.rece_id_certificado → certificado.cert_id_certificado */
    public function certificado(): BelongsTo
    {
        return $this->belongsTo(Certificado::class, 'rece_id_certificado', 'cert_id_certificado');
    }
}
