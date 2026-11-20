<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.persona del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class Persona extends PdmModel
{
    protected $table = 'anfeca_modelo.persona';

    protected $primaryKey = 'pers_id_persona';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'pers_id_persona',
        'pers_id_institucion',
        'pers_nombre',
        'pers_apellido_paterno',
        'pers_apellido_materno',
        'pers_curp',
        'pers_rfc',
        'pers_genero',
        'pers_num_afiliacion_institucion',
        'pers_activo',
        'pers_cedula_profesional',
        'pers_datos_autorizados',
        'pers_fecha_autorizacion',
        'pers_creado_en',
        'pers_actualizado_en',
    ];

    protected $casts = [
        'pers_id_persona' => 'integer',
        'pers_id_institucion' => 'integer',
        'pers_activo' => 'boolean',
        'pers_datos_autorizados' => 'boolean',
        'pers_fecha_autorizacion' => 'datetime',
        'pers_creado_en' => 'datetime',
        'pers_actualizado_en' => 'datetime',
    ];

    /** FK FK_PERSONA_PERTENECE_INSTITUC: persona.pers_id_institucion → institucion.inst_id_institucion */
    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'pers_id_institucion', 'inst_id_institucion');
    }

    /** FK FK_COMUNICA_TIENE_CON_PERSONA: comunicacion.cumu_id_persona → persona.pers_id_persona */
    public function comunicaciones(): HasMany
    {
        return $this->hasMany(Comunicacion::class, 'cumu_id_persona', 'pers_id_persona');
    }

    /** FK FK_DOMICILI_TIENE_DOM_PERSONA: domicilio.domi_id_persona → persona.pers_id_persona */
    public function domicilios(): HasMany
    {
        return $this->hasMany(Domicilio::class, 'domi_id_persona', 'pers_id_persona');
    }

    /** FK FK_PERSONA__RELATIONS_PERSONA: persona_estudio_profesional.pees_id_persona → persona.pers_id_persona */
    public function personaEstudioProfesionales(): HasMany
    {
        return $this->hasMany(PersonaEstudioProfesional::class, 'pees_id_persona', 'pers_id_persona');
    }

    /** FK FK_SOLICITU_PUEDE_HAC_PERSONA: solicitud.soli_id_persona → persona.pers_id_persona */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'soli_id_persona', 'pers_id_persona');
    }

    /** FK FK_USUARIO_TIENE_USU_PERSONA: usuario.usua_id_persona → persona.pers_id_persona */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'usua_id_persona', 'pers_id_persona');
    }
}
