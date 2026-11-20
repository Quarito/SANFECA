<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.historico_certificado_importacion (EXTENSIÓN ANFECA, no está en el PDM original).
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y anfeca_modelo_extensiones.sql.
 */
class HistoricoCertificadoImportacion extends PdmModel
{
    protected $table = 'anfeca_modelo.historico_certificado_importacion';

    protected $primaryKey = 'hcim_id_importacion';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'hcim_id_importacion',
        'hcim_id_usuario',
        'hcim_zona',
        'hcim_nombre_historico',
        'hcim_correo_historico',
        'hcim_apellido_paterno',
        'hcim_apellido_materno',
        'hcim_nombres',
        'hcim_curp_historica',
        'hcim_institucion_historica',
        'hcim_certificacion',
        'hcim_primera_vez',
        'hcim_refrendo_1',
        'hcim_refrendo_2',
        'hcim_refrendo_3',
        'hcim_refrendo_4',
        'hcim_refrendo_5',
        'hcim_refrendo_6',
        'hcim_refrendo_7',
        'hcim_columna_sin_encabezado',
        'hcim_refrendo_correspondiente',
        'hcim_numero_certificado',
        'hcim_lote_importacion',
        'hcim_fila_origen',
        'hcim_estado_vinculacion',
        'hcim_estado_normalizacion',
        'hcim_motivo_normalizacion',
        'hcim_creado_en',
        'hcim_actualizado_en',
    ];

    protected $casts = [
        'hcim_id_importacion' => 'integer',
        'hcim_id_usuario' => 'integer',
        'hcim_fila_origen' => 'integer',
        'hcim_creado_en' => 'datetime',
        'hcim_actualizado_en' => 'datetime',
    ];

    /** FK FK_HCIM_USUARIO: historico_certificado_importacion.hcim_id_usuario → usuario.usua_id_usuario */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'hcim_id_usuario', 'usua_id_usuario');
    }

    /** FK FK_HICE_EXT_IMPORTACION: historico_certificado.hice_id_importacion → historico_certificado_importacion.hcim_id_importacion */
    public function historicoCertificados(): HasMany
    {
        return $this->hasMany(HistoricoCertificado::class, 'hice_id_importacion', 'hcim_id_importacion');
    }

    /** FK FK_SOLI_EXT_HISTORICO: solicitud.soli_id_historico_importacion → historico_certificado_importacion.hcim_id_importacion */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'soli_id_historico_importacion', 'hcim_id_importacion');
    }
}
