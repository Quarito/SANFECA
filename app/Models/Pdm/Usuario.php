<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.usuario del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y anfeca_modelo_extensiones.sql.
 */
class Usuario extends PdmModel
{
    protected $table = 'anfeca_modelo.usuario';

    protected $primaryKey = 'usua_id_usuario';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'usua_id_usuario',
        'usua_id_persona',
        'usua_id_rol',
        'usua_nombre',
        'usua_contrasenia',
        'usua_estatus',
    ];

    protected $casts = [
        'usua_id_usuario' => 'integer',
        'usua_id_persona' => 'integer',
        'usua_id_rol' => 'integer',
    ];

    /** FK FK_USUARIO_TIENE_ROL_ROL: usuario.usua_id_rol → rol.rol_id_rol */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'usua_id_rol', 'rol_id_rol');
    }

    /** FK FK_USUARIO_TIENE_USU_PERSONA: usuario.usua_id_persona → persona.pers_id_persona */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'usua_id_persona', 'pers_id_persona');
    }

    /** FK FK_COORDZON_USUARIO: coordinador_zona.cozo_id_usuario → usuario.usua_id_usuario */
    public function coordinadorZonas(): HasMany
    {
        return $this->hasMany(CoordinadorZona::class, 'cozo_id_usuario', 'usua_id_usuario');
    }

    /** FK FK_HCIM_USUARIO: historico_certificado_importacion.hcim_id_usuario → usuario.usua_id_usuario */
    public function historicoCertificadoImportaciones(): HasMany
    {
        return $this->hasMany(HistoricoCertificadoImportacion::class, 'hcim_id_usuario', 'usua_id_usuario');
    }
}
