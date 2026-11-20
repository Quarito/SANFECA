<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.documento del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class Documento extends PdmModel
{
    protected $table = 'anfeca_modelo.documento';

    protected $primaryKey = 'docu_id_documento';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'docu_id_documento',
        'docu_descripcion',
        'docu_estatus',
    ];

    protected $casts = [
        'docu_id_documento' => 'integer',
    ];

    /** FK FK_SOLICITU_RELATIONS_DOCUMENT: solicitud_documento.docu_id_documento → documento.docu_id_documento */
    public function solicitudDocumentos(): HasMany
    {
        return $this->hasMany(SolicitudDocumento::class, 'docu_id_documento', 'docu_id_documento');
    }
}
