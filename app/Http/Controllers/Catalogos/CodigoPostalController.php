<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Services\Anfeca\Sepomex\ConsultaCodigoPostal;
use Illuminate\Http\JsonResponse;

/** GET /catalogos/codigo-postal/{cp}: entidad, alcaldía o municipio y colonias del código postal (catálogo SEPOMEX). */
class CodigoPostalController extends Controller
{
    public function __invoke(string $codigoPostal, ConsultaCodigoPostal $consulta): JsonResponse
    {
        $resultado = $consulta->consultar($codigoPostal);

        return $resultado === null
            ? response()->json(['codigo_postal' => $codigoPostal, 'mensaje' => 'El código postal no se encuentra en el catálogo nacional.'], 404)
            : response()->json($resultado)->header('Cache-Control', 'private, max-age=3600');
    }
}
