<?php

namespace App\Http\Controllers\Coordinacion;

use App\Http\Controllers\Controller;

/**
 * Prototipo visual AISLADO de la revisión documental (fase 6.3.0).
 * No consulta ni escribe la base de datos: el componente Vue usa datos ficticios
 * fijos y estado en memoria. Disponible solo con ANFECA_COORDINACION_PROTOTIPOS=true.
 */
class PrototipoRevisionController extends Controller
{
    public function index()
    {
        abort_unless(config('anfeca_coordinacion.prototipos') === true, 404);

        return view('coordinacion.prototipos.revision-documental');
    }
}
