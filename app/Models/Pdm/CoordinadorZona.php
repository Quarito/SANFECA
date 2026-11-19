<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.coordinador_zona (EXTENSIÓN ANFECA, no está en el PDM original).
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y anfeca_modelo_extensiones.sql.
 */
class CoordinadorZona extends PdmModel
{
    protected $table = 'anfeca_modelo.coordinador_zona';

    protected $primaryKey = 'cozo_id_coordinador_zona';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'cozo_id_coordinador_zona',
        'cozo_id_zona',
        'cozo_id_usuario',
    ];

    protected $casts = [
        'cozo_id_coordinador_zona' => 'integer',
        'cozo_id_zona' => 'integer',
        'cozo_id_usuario' => 'integer',
    ];

    /** FK FK_COORDZON_ZONA: coordinador_zona.cozo_id_zona → zona.zona_id_zona */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'cozo_id_zona', 'zona_id_zona');
    }

    /** FK FK_COORDZON_USUARIO: coordinador_zona.cozo_id_usuario → usuario.usua_id_usuario */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cozo_id_usuario', 'usua_id_usuario');
    }
}
