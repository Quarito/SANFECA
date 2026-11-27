<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tabla anfeca_modelo.solicitud del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 */
class Solicitud extends PdmModel
{
    protected $table = 'anfeca_modelo.solicitud';

    protected $primaryKey = 'soli_id_solicitud';

    public $incrementing = true;

    protected $keyType = 'int';

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
        'soli_tipo_tramite',
        'soli_certificacion',
        'soli_id_historico_importacion',
        'soli_creado_en',
        'soli_actualizado_en',
    ];

    protected $casts = [
        'soli_id_solicitud' => 'integer',
        'soli_id_persona' => 'integer',
        'soli_id_area_certificacion' => 'integer',
        'soli_id_tipo_personal_academico' => 'integer',
        'soli_id_convocatoria' => 'integer',
        'soli_id_articulo' => 'integer',
        'soli_fecha_registro_solicitud' => 'date',
        'soli_folio_solicitud' => 'integer',
        'soli_art13' => 'boolean',
        'soli_art14' => 'boolean',
        'soli_id_historico_importacion' => 'integer',
        'soli_creado_en' => 'datetime',
        'soli_actualizado_en' => 'datetime',
    ];

    /** FK FK_SOLICITU_ESTA_ASOC_TIPO_PER: solicitud.soli_id_tipo_personal_academico → tipo_personal_academico.tipa_id_tipo_personal_academico */
    public function tipoPersonalAcademico(): BelongsTo
    {
        return $this->belongsTo(TipoPersonalAcademico::class, 'soli_id_tipo_personal_academico', 'tipa_id_tipo_personal_academico');
    }

    /** FK FK_SOLICITU_PERTENECE_AREA_CER: solicitud.soli_id_area_certificacion → area_certificacion.area_id_area_certificacion */
    public function areaCertificacion(): BelongsTo
    {
        return $this->belongsTo(AreaCertificacion::class, 'soli_id_area_certificacion', 'area_id_area_certificacion');
    }

    /** FK FK_SOLICITU_PUEDE_HAC_PERSONA: solicitud.soli_id_persona → persona.pers_id_persona */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'soli_id_persona', 'pers_id_persona');
    }

    /** FK FK_SOLICITU_RELATIONS_CONVOCAT: solicitud.soli_id_convocatoria → convocatoria.conv_id_convocatoria */
    public function convocatoria(): BelongsTo
    {
        return $this->belongsTo(Convocatoria::class, 'soli_id_convocatoria', 'conv_id_convocatoria');
    }

    /** FK FK_SOLICITU_TIENE_ART_ARTICULO: solicitud.soli_id_articulo → articulo_subindice.arce_id_articulo */
    public function articuloSubindice(): BelongsTo
    {
        return $this->belongsTo(ArticuloSubindice::class, 'soli_id_articulo', 'arce_id_articulo');
    }

    /** FK FK_SOLI_EXT_HISTORICO: solicitud.soli_id_historico_importacion → historico_certificado_importacion.hcim_id_importacion */
    public function historicoCertificadoImportacion(): BelongsTo
    {
        return $this->belongsTo(HistoricoCertificadoImportacion::class, 'soli_id_historico_importacion', 'hcim_id_importacion');
    }

    /** FK FK_ACTA_PROPORCIO_SOLICITU: acta.acta_id_solicitud → solicitud.soli_id_solicitud */
    public function actas(): HasMany
    {
        return $this->hasMany(Acta::class, 'acta_id_solicitud', 'soli_id_solicitud');
    }

    /** FK FK_ESTADO_S_REFERENCE_SOLICITU: estado_solicitud.esso_id_solicitud → solicitud.soli_id_solicitud */
    public function estadoSolicitudes(): HasMany
    {
        return $this->hasMany(EstadoSolicitud::class, 'esso_id_solicitud', 'soli_id_solicitud');
    }

    public function ultimoEstado(): HasOne
    {
        return $this->estadoSolicitudes()->one()->ofMany([
            'esso_fecha' => 'max',
            'esso_hora' => 'max',
            'esso_id__estado_solicitud' => 'max',
        ]);
    }

    /** FK FK_EVALUACI_RELATIONS_SOLICITU: evaluacion.eval_id_solicitud → solicitud.soli_id_solicitud */
    public function evaluaciones(): HasMany
    {
        return $this->hasMany(Evaluacion::class, 'eval_id_solicitud', 'soli_id_solicitud');
    }

    /** FK FK_PAGO_REFERENCE_SOLICITU: pago.pago_id_solicitud → solicitud.soli_id_solicitud */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'pago_id_solicitud', 'soli_id_solicitud');
    }

    /** FK FK_SOLICITU_TIENE_AS_SOLICITU: solicitud_documento.socu_id_solicitud → solicitud.soli_id_solicitud */
    public function solicitudDocumentos(): HasMany
    {
        return $this->hasMany(SolicitudDocumento::class, 'socu_id_solicitud', 'soli_id_solicitud');
    }

    /** FK FK_PEES_EXT_SOLICITUD: persona_estudio_profesional.pees_id_solicitud → solicitud.soli_id_solicitud */
    public function personaEstudioProfesionales(): HasMany
    {
        return $this->hasMany(PersonaEstudioProfesional::class, 'pees_id_solicitud', 'soli_id_solicitud');
    }

    /** FK FK_SORE_SOLICITUD: solicitud_refrendo.sore_id_solicitud → solicitud.soli_id_solicitud */
    public function solicitudRefrendos(): HasMany
    {
        return $this->hasMany(SolicitudRefrendo::class, 'sore_id_solicitud', 'soli_id_solicitud');
    }

    /** FK FK_FOSC_SOLICITUD: formacion_solicitud.fosc_id_solicitud → solicitud.soli_id_solicitud */
    public function formacionSolicitudes(): HasMany
    {
        return $this->hasMany(FormacionSolicitud::class, 'fosc_id_solicitud', 'soli_id_solicitud');
    }

    /** FK FK_DOSC_SOLICITUD: documentos_solicitud.dosc_id_solicitud → solicitud.soli_id_solicitud */
    public function documentosSolicitudes(): HasMany
    {
        return $this->hasMany(DocumentosSolicitud::class, 'dosc_id_solicitud', 'soli_id_solicitud');
    }
}
