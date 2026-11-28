<?php

namespace App\Services\Anfeca\Sepomex;

use Illuminate\Support\Facades\DB;

/**
 * Consulta por código postal sobre el modelo oficial: COLONIA → ALCALDIA_MUNICIPIO → ENTIDAD_FEDERATIVA.
 * Lee directamente de PostgreSQL; no usa configuración ni otras tablas como fuente de datos geográficos.
 */
final class ConsultaCodigoPostal
{
    /** @return array{codigo_postal:string, municipios:list<array>}|null null si el código no existe en el catálogo. */
    public function consultar(string $codigoPostal): ?array
    {
        if (!preg_match('/^[0-9]{5}$/D', $codigoPostal)) {
            return null;
        }
        $filas = DB::table('colonia as c')
            ->join('alcaldia_municipio as a', 'a.almu_id_alcaldia', '=', 'c.colo_id_alcaldia')
            ->join('entidad_federativa as e', 'e.enfe_id_entidad_federativa', '=', 'a.almu_id_entidad_federativa')
            ->where('c.colo_codigo_postal', $codigoPostal)
            ->orderBy('a.almu_nombre')->orderBy('c.colo_nombre')
            ->get(['c.colo_id_colonia', 'c.colo_nombre', 'a.almu_id_alcaldia', 'a.almu_nombre',
                'e.enfe_id_entidad_federativa', 'e.enfe_nombre']);
        if ($filas->isEmpty()) {
            return null;
        }

        return [
            'codigo_postal' => $codigoPostal,
            // Un código postal pertenece a un municipio; se devuelve una lista para no suponerlo.
            'municipios' => $filas->groupBy('almu_id_alcaldia')->map(fn ($g) => [
                'id' => (int) $g->first()->almu_id_alcaldia,
                'nombre' => $g->first()->almu_nombre,
                'entidad_federativa' => ['id' => (int) $g->first()->enfe_id_entidad_federativa, 'nombre' => $g->first()->enfe_nombre],
                'colonias' => $g->map(fn ($c) => ['id' => (int) $c->colo_id_colonia, 'nombre' => $c->colo_nombre])->values()->all(),
            ])->values()->all(),
        ];
    }

    /** Colonia con su municipio y entidad, para validar en el servidor una selección (o null). */
    public function colonia(int $id, ?string $codigoPostal = null): ?object
    {
        return DB::table('colonia as c')
            ->join('alcaldia_municipio as a', 'a.almu_id_alcaldia', '=', 'c.colo_id_alcaldia')
            ->join('entidad_federativa as e', 'e.enfe_id_entidad_federativa', '=', 'a.almu_id_entidad_federativa')
            ->where('c.colo_id_colonia', $id)
            ->when($codigoPostal !== null, fn ($q) => $q->where('c.colo_codigo_postal', $codigoPostal))
            ->first(['c.colo_id_colonia', 'c.colo_nombre', 'c.colo_codigo_postal', 'a.almu_id_alcaldia', 'a.almu_nombre',
                'e.enfe_id_entidad_federativa', 'e.enfe_nombre']);
    }
}
