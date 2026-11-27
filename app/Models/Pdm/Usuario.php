<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla public.usuario del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class Usuario extends PdmModel
{
    protected $table = 'usuario';

    protected $primaryKey = 'usua_id_usuario';

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

    /** fk_usuario_tiene_rol_rol: usuario.usua_id_rol → rol.rol_id_rol */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'usua_id_rol', 'rol_id_rol');
    }

    /** fk_usuario_tiene_usu_persona: usuario.usua_id_persona → persona.pers_id_persona */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'usua_id_persona', 'pers_id_persona');
    }
}
