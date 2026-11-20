<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.tipo_personal_academico del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class TipoPersonalAcademico extends PdmModel
{
    protected $table = 'anfeca_modelo.tipo_personal_academico';

    protected $primaryKey = 'tipa_id_tipo_personal_academico';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'tipa_id_tipo_personal_academico',
        'tipa_descripcion',
        'tipa_estado',
    ];

    protected $casts = [
        'tipa_id_tipo_personal_academico' => 'integer',
        'tipa_estado' => 'boolean',
    ];

    /** FK FK_SOLICITU_ESTA_ASOC_TIPO_PER: solicitud.soli_id_tipo_personal_academico → tipo_personal_academico.tipa_id_tipo_personal_academico */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'soli_id_tipo_personal_academico', 'tipa_id_tipo_personal_academico');
    }
}
