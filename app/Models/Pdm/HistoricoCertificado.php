<?php

namespace App\Models\Pdm;


/**
 * Tabla public.historico_certificado del modelo oficial PowerDesigner (36 tablas).
 * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.
 */
class HistoricoCertificado extends PdmModel
{
    protected $table = 'historico_certificado';

    protected $primaryKey = 'hice_id_historico_certificado';

    /** PK compuesta del modelo oficial. */
    protected array $columnasClaveCompuesta = ['hice_folio_certificado'];

    protected $fillable = [
        'hice_id_historico_certificado',
        'hice_anio_certificacion',
        'hice_numero_certificacion',
        'hice_tipo',
        'hice_folio_certificado',
        'hice_curp',
        'hice_area_certificacion',
    ];

    protected $casts = [
        'hice_id_historico_certificado' => 'integer',
    ];
}
