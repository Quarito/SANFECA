<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla public.convocatoria del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class Convocatoria extends PdmModel
{
    protected $table = 'convocatoria';

    protected $primaryKey = 'conv_id_convocatoria';

    protected $fillable = [
        'conv_id_convocatoria',
        'conv_id_tipo_convocatoria',
        'conv_nombre',
        'conv_monto_recuperacion',
        'conv_fecha_inicio_registro',
        'conv_fecha_fin_registro',
        'conv_fecha_resultado',
        'conv_fecha_entrega_certificados',
        'conv_puntuacion_max',
        'conv_puntuacion_min',
        'conv_lugar_entrega',
        'conv_evento_entrega',
    ];

    protected $casts = [
        'conv_id_convocatoria' => 'integer',
        'conv_id_tipo_convocatoria' => 'integer',
        'conv_puntuacion_max' => 'integer',
        'conv_puntuacion_min' => 'integer',
    ];

    /** fk_convocat_relations_tipo_con: convocatoria.conv_id_tipo_convocatoria → tipo_convocatoria.tico_id_tipo_convocatoria */
    public function tipoConvocatoria(): BelongsTo
    {
        return $this->belongsTo(TipoConvocatoria::class, 'conv_id_tipo_convocatoria', 'tico_id_tipo_convocatoria');
    }

    /** fk_solicitu_relations_convocat: solicitud.soli_id_convocatoria → convocatoria.conv_id_convocatoria */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'soli_id_convocatoria', 'conv_id_convocatoria');
    }
}
