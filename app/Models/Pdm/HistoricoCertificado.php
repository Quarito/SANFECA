<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla anfeca_modelo.historico_certificado del modelo PowerDesigner.
 * Generado a partir de database/sql/anfeca_modelo/anfeca_modelo.sql y sus extensiones (F1, F4).
 * La PK compuesta se aplica al recargar y guardar la instancia.
 */
class HistoricoCertificado extends PdmModel
{
    protected $table = 'anfeca_modelo.historico_certificado';

    protected $primaryKey = 'hice_id_historico_certificado';

    protected array $columnasClaveCompuesta = ['hice_folio_certificado'];

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'hice_id_historico_certificado',
        'hice_anio_certificacion',
        'hice_numero_certificacion',
        'hice_tipo',
        'hice_folio_certificado',
        'hice_curp',
        'hice_area_certificacion',
        'hice_id_importacion',
        'hice_certificacion',
    ];

    protected $casts = [
        'hice_id_historico_certificado' => 'integer',
        'hice_id_importacion' => 'integer',
    ];

    /** FK FK_HICE_EXT_IMPORTACION: historico_certificado.hice_id_importacion → historico_certificado_importacion.hcim_id_importacion */
    public function historicoCertificadoImportacion(): BelongsTo
    {
        return $this->belongsTo(HistoricoCertificadoImportacion::class, 'hice_id_importacion', 'hcim_id_importacion');
    }
}
