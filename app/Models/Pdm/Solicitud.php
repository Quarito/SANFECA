<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla public.solicitud del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class Solicitud extends PdmModel
{
    protected $table = 'solicitud';

    protected $primaryKey = 'soli_id_solicitud';

    protected $fillable = [
        'soli_id_solicitud',
        'soli_id_persona',
        'soli_id_area_certificacion',
        'soli_id_tipo_personal_academico',
        'soli_id_convocatoria',
        'soli_id_articulo',
        'soli_numero_certificado',
        'soli_fecha_registro_solicitud',
        'soli_folio_solicitud',
        'soli_art13',
        'soli_art14',
    ];

    protected $casts = [
        'soli_id_solicitud' => 'integer',
        'soli_id_persona' => 'integer',
        'soli_id_area_certificacion' => 'integer',
        'soli_id_tipo_personal_academico' => 'integer',
        'soli_id_convocatoria' => 'integer',
        'soli_id_articulo' => 'integer',
        'soli_folio_solicitud' => 'integer',
        'soli_art13' => 'boolean',
        'soli_art14' => 'boolean',
    ];

    /** fk_acta_proporcio_solicitu: acta.acta_id_solicitud → solicitud.soli_id_solicitud */
    public function actas(): HasMany
    {
        return $this->hasMany(Acta::class, 'acta_id_solicitud', 'soli_id_solicitud');
    }

    /** fk_estado_s_reference_solicitu: estado_solicitud.esso_id_solicitud → solicitud.soli_id_solicitud */
    public function estadoSolicitudes(): HasMany
    {
        return $this->hasMany(EstadoSolicitud::class, 'esso_id_solicitud', 'soli_id_solicitud');
    }

    /** fk_evaluaci_relations_solicitu: evaluacion.eval_id_solicitud → solicitud.soli_id_solicitud */
    public function evaluaciones(): HasMany
    {
        return $this->hasMany(Evaluacion::class, 'eval_id_solicitud', 'soli_id_solicitud');
    }

    /** fk_pago_reference_solicitu: pago.pago_id_solicitud → solicitud.soli_id_solicitud */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'pago_id_solicitud', 'soli_id_solicitud');
    }

    /** fk_solicitu_esta_asoc_tipo_per: solicitud.soli_id_tipo_personal_academico → tipo_personal_academico.tipa_id_tipo_personal_academico */
    public function tipoPersonalAcademico(): BelongsTo
    {
        return $this->belongsTo(TipoPersonalAcademico::class, 'soli_id_tipo_personal_academico', 'tipa_id_tipo_personal_academico');
    }

    /** fk_solicitu_pertenece_area_cer: solicitud.soli_id_area_certificacion → area_certificacion.area_id_area_certificacion */
    public function areaCertificacion(): BelongsTo
    {
        return $this->belongsTo(AreaCertificacion::class, 'soli_id_area_certificacion', 'area_id_area_certificacion');
    }

    /** fk_solicitu_puede_hac_persona: solicitud.soli_id_persona → persona.pers_id_persona */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'soli_id_persona', 'pers_id_persona');
    }

    /** fk_solicitu_relations_convocat: solicitud.soli_id_convocatoria → convocatoria.conv_id_convocatoria */
    public function convocatoria(): BelongsTo
    {
        return $this->belongsTo(Convocatoria::class, 'soli_id_convocatoria', 'conv_id_convocatoria');
    }

    /** fk_solicitu_tiene_art_articulo: solicitud.soli_id_articulo → articulo_subindice.arce_id_articulo */
    public function articuloSubindice(): BelongsTo
    {
        return $this->belongsTo(ArticuloSubindice::class, 'soli_id_articulo', 'arce_id_articulo');
    }

    /** fk_solicitu_tiene_as_solicitu: solicitud_documento.socu_id_solicitud → solicitud.soli_id_solicitud */
    public function solicitudDocumentos(): HasMany
    {
        return $this->hasMany(SolicitudDocumento::class, 'socu_id_solicitud', 'soli_id_solicitud');
    }
}
