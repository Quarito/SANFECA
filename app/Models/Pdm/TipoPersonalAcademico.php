<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla public.tipo_personal_academico del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class TipoPersonalAcademico extends PdmModel
{
    protected $table = 'tipo_personal_academico';

    protected $primaryKey = 'tipa_id_tipo_personal_academico';

    protected $fillable = [
        'tipa_id_tipo_personal_academico',
        'tipa_descripcion',
        'tipa_estado',
    ];

    protected $casts = [
        'tipa_id_tipo_personal_academico' => 'integer',
        'tipa_estado' => 'boolean',
    ];

    /** fk_solicitu_esta_asoc_tipo_per: solicitud.soli_id_tipo_personal_academico → tipo_personal_academico.tipa_id_tipo_personal_academico */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'soli_id_tipo_personal_academico', 'tipa_id_tipo_personal_academico');
    }
}
