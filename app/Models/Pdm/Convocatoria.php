<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.convocatoria del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y anfeca_modelo_extensiones.sql.
 */
class Convocatoria extends PdmModel
{
    protected $table = 'anfeca_modelo.convocatoria';

    protected $primaryKey = 'conv_id_convocatoria';

    public $incrementing = true;

    protected $keyType = 'int';

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
        'con_lugar_entrega',
        'con_evento_entrega',
    ];

    protected $casts = [
        'conv_id_convocatoria' => 'integer',
        'conv_id_tipo_convocatoria' => 'integer',
        'conv_fecha_inicio_registro' => 'date',
        'conv_fecha_fin_registro' => 'date',
        'conv_fecha_resultado' => 'date',
        'conv_fecha_entrega_certificados' => 'date',
        'conv_puntuacion_max' => 'integer',
        'conv_puntuacion_min' => 'integer',
    ];

    /** FK FK_CONVOCAT_RELATIONS_TIPO_CON: convocatoria.conv_id_tipo_convocatoria → tipo_convocatoria.tico_id_tipo_convocatoria */
    public function tipoConvocatoria(): BelongsTo
    {
        return $this->belongsTo(TipoConvocatoria::class, 'conv_id_tipo_convocatoria', 'tico_id_tipo_convocatoria');
    }

    /** FK FK_SOLICITU_RELATIONS_CONVOCAT: solicitud.soli_id_convocatoria → convocatoria.conv_id_convocatoria */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'soli_id_convocatoria', 'conv_id_convocatoria');
    }
}
