<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla public.documento del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class Documento extends PdmModel
{
    protected $table = 'documento';

    protected $primaryKey = 'docu_id_documento';

    protected $fillable = [
        'docu_id_documento',
        'docu_descripcion',
        'docu_estatus',
    ];

    protected $casts = [
        'docu_id_documento' => 'integer',
    ];

    /** fk_solicitu_relations_document: solicitud_documento.socu_id_documento → documento.docu_id_documento */
    public function solicitudDocumentos(): HasMany
    {
        return $this->hasMany(SolicitudDocumento::class, 'socu_id_documento', 'docu_id_documento');
    }
}
