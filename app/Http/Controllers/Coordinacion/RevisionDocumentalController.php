<?php

namespace App\Http\Controllers\Coordinacion;

use App\Http\Controllers\Controller;

/**
 * Revisión documental (desactivada también en 8086 con ANFECA_COORDINACION_REVISION=false).
 * Su versión anterior dependía de la tabla revisiones_documentales, ajena al modelo oficial. En las 36 tablas
 * corresponde a ESTADO_SOLICITUD_DOCUMENTO / ESTADO_DOCUMENTO (estados «Validado», «Con observaciones»,
 * «No válido» ya catalogados); su habilitación queda pendiente de autorización (DEF-02).
 */
class RevisionDocumentalController extends Controller
{
    public function resumen(int $solicitud)
    {
        abort(404);
    }

    public function historial(int $solicitud, string $campo)
    {
        abort(404);
    }

    public function registrar(int $solicitud, string $campo)
    {
        abort(404);
    }
}
