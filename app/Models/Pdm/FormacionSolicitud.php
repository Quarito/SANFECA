<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.formacion_solicitud (EXTENSIÓN ANFECA, no está en el PDM original).
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class FormacionSolicitud extends PdmModel
{
    protected $table = 'anfeca_modelo.formacion_solicitud';

    protected $primaryKey = 'fosc_id_formacion';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'fosc_id_formacion',
        'fosc_id_solicitud',
        'fosc_id_institucion',
        'fosc_creado_en',
        'fosc_actualizado_en',
        'titulo_origen_especifico',
        'origen_titulo_origen',
        'clave_licenciatura_catalogo',
        'version_catalogo_licenciaturas',
        'titulo_ultimo_grado_especifico',
        'origen_titulo_ultimo_grado',
        'clave_posgrado_catalogo',
        'version_catalogo_posgrados',
        'cuenta_con_posgrado',
    ];

    protected $casts = [
        'fosc_id_formacion' => 'integer',
        'fosc_id_solicitud' => 'integer',
        'fosc_id_institucion' => 'integer',
        'fosc_creado_en' => 'datetime',
        'fosc_actualizado_en' => 'datetime',
        'cuenta_con_posgrado' => 'boolean',
    ];

    /** FK FK_FOSC_SOLICITUD: formacion_solicitud.fosc_id_solicitud → solicitud.soli_id_solicitud */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'fosc_id_solicitud', 'soli_id_solicitud');
    }

    /** FK FK_FOSC_INSTITUCION: formacion_solicitud.fosc_id_institucion → institucion.inst_id_institucion */
    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'fosc_id_institucion', 'inst_id_institucion');
    }
}
