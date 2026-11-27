<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla public.persona del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class Persona extends PdmModel
{
    protected $table = 'persona';

    protected $primaryKey = 'pers_id_persona';

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
    ];

    protected $casts = [
        'pers_id_persona' => 'integer',
        'pers_id_institucion' => 'integer',
        'pers_activo' => 'boolean',
    ];

    /** fk_comunica_tiene_con_persona: comunicacion.comu_id_persona → persona.pers_id_persona */
    public function comunicaciones(): HasMany
    {
        return $this->hasMany(Comunicacion::class, 'comu_id_persona', 'pers_id_persona');
    }

    /** fk_domicili_tiene_dom_persona: domicilio.domi_id_persona → persona.pers_id_persona */
    public function domicilios(): HasMany
    {
        return $this->hasMany(Domicilio::class, 'domi_id_persona', 'pers_id_persona');
    }

    /** fk_persona_pertenece_instituc: persona.pers_id_institucion → institucion.inst_id_institucion */
    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'pers_id_institucion', 'inst_id_institucion');
    }

    /** fk_persona__relations_persona: persona_estudio_profesional.pees_id_persona → persona.pers_id_persona */
    public function personaEstudioProfesionales(): HasMany
    {
        return $this->hasMany(PersonaEstudioProfesional::class, 'pees_id_persona', 'pers_id_persona');
    }

    /** fk_solicitu_puede_hac_persona: solicitud.soli_id_persona → persona.pers_id_persona */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'soli_id_persona', 'pers_id_persona');
    }

    /** fk_usuario_tiene_usu_persona: usuario.usua_id_persona → persona.pers_id_persona */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'usua_id_persona', 'pers_id_persona');
    }
}
