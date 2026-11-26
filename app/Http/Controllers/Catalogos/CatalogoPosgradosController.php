<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Services\Catalogos\CatalogoPosgrados;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Títulos del catálogo de posgrados para el selector de «Último grado de estudios».
 * Autenticado (solicitantes), con límite de frecuencia y de resultados; entrega SOLO clave y título.
 * 404 mientras el catálogo no esté activo (bandera, archivo íntegro y migración aplicada).
 */
class CatalogoPosgradosController extends Controller
{
    public function titulos(Request $request, CatalogoPosgrados $catalogo): JsonResponse
    {
        abort_unless($catalogo->activo(), 404);
        $f = $request->validate([
            'nivel' => ['required', Rule::in(CatalogoPosgrados::NIVELES)],
            'area' => ['required', Rule::in(CatalogoPosgrados::AREAS)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json([
            'version' => $catalogo->version(),
            'titulos' => $catalogo->titulos($f['nivel'], $f['area'], $f['q'] ?? null),
        ])->header('Cache-Control', 'private, max-age=300');
    }
}
