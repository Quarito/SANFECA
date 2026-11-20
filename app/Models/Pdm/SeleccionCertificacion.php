<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.seleccion_certificacion (EXTENSIÓN ANFECA, no está en el PDM original).
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class SeleccionCertificacion extends PdmModel
{
    protected $table = 'anfeca_modelo.seleccion_certificacion';

    protected $primaryKey = 'secc_id_seleccion';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'secc_id_seleccion',
        'secc_id_usuario',
        'secc_certificacion',
        'secc_tipo_personal',
        'secc_area_certificacion',
        'secc_creado_en',
        'secc_actualizado_en',
    ];

    protected $casts = [
        'secc_id_seleccion' => 'integer',
        'secc_id_usuario' => 'integer',
        'secc_creado_en' => 'datetime',
        'secc_actualizado_en' => 'datetime',
    ];

    /** FK FK_SECC_USUARIO: seleccion_certificacion.secc_id_usuario → usuario.usua_id_usuario */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'secc_id_usuario', 'usua_id_usuario');
    }
}
