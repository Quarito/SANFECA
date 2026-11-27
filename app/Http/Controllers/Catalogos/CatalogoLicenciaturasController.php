<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Services\Catalogos\CatalogoLicenciaturas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Búsqueda de denominaciones de licenciatura para «Nombre del Título» (Formación Académica de Origen).
 * Autenticado (solicitantes), con límite de frecuencia y de resultados; entrega SOLO clave y título.
 * Busca en las cinco áreas o en el área seleccionada; el total permite pedir que se afine la búsqueda.
 * 404 mientras el catálogo no esté activo (bandera, archivo íntegro y migración aplicada).
 */
class CatalogoLicenciaturasController extends Controller
{
    public function buscar(Request $request, CatalogoLicenciaturas $catalogo): JsonResponse
    {
        abort_unless($catalogo->activo(), 404);
        $minimo = (int) config('anfeca_catalogos.licenciaturas.busqueda_minima', 3);
        $f = $request->validate([
            'q' => ['required', 'string', "min:{$minimo}", 'max:100'],
            'area' => ['nullable', 'string', Rule::in(CatalogoLicenciaturas::AREAS)],
        ]);
        $resultado = $catalogo->buscar($f['q'], null, $f['area'] ?? null);

        return response()->json([
            'version' => $catalogo->version(),
            'total' => $resultado['total'],
            'limite' => (int) config('anfeca_catalogos.licenciaturas.maximo_resultados', 60),
            'titulos' => $resultado['titulos'],
        ])->header('Cache-Control', 'private, max-age=300');
    }
}
