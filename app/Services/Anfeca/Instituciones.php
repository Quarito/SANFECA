<?php

namespace App\Services\Anfeca;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Instituciones afiliadas sobre INSTITUCION y SEDE, con los nombres que usa la pantalla:
 * numero_afiliacion = INST_NUMERO_AFILIACION, nombre = INST_NOMBRE, escuela_facultad = INST_FACULTAD,
 * zona_anfeca = INST_ZONA, sede = SEDE.SEDE_DESCRIPCION (primera sede registrada). «campus» no tiene
 * atributo propio en el modelo oficial (INC-03).
 */
final class Instituciones
{
    public function consulta(): \Illuminate\Database\Query\Builder
    {
        return DB::table('institucion as i')->select([
            'i.inst_id_institucion as id', 'i.inst_numero_afiliacion as numero_afiliacion', 'i.inst_nombre as nombre',
            'i.inst_facultad as escuela_facultad', 'i.inst_zona as zona_anfeca',
            'i.inst_coord_regional_cert_academica as coordinacion_regional',
        ])->selectSub(DB::table('sede as s')->whereColumn('s.inst_id_institucion', 'i.inst_id_institucion')
            ->orderBy('s.sede_id_sede')->limit(1)->select('s.sede_descripcion'), 'sede')
            ->selectRaw('null::varchar as campus');
    }

    /** Hasta dos resultados para distinguir una afiliación inexistente de una ambigua. */
    public function porAfiliacion(string $numero): Collection
    {
        return DB::table('institucion')->where('inst_numero_afiliacion', $numero)->limit(2)->pluck('inst_id_institucion');
    }

    public function detalleFormacion(?int $id): ?object
    {
        $institucion = $this->porId($id);
        if ($institucion === null) {
            return null;
        }

        $institucion->sede = DB::table('sede')->where('inst_id_institucion', $id)
            ->orderBy('sede_id_sede')->pluck('sede_descripcion')
            ->map(fn ($sede) => trim((string) $sede))->filter(fn ($sede) => $sede !== '')->unique()->implode(' · ');
        unset($institucion->coordinacion_regional);

        return $institucion;
    }

    public function lista(): Collection
    {
        return $this->consulta()->orderBy('i.inst_numero_afiliacion')->get();
    }

    public function porId(?int $id): ?object
    {
        return $id ? $this->consulta()->where('i.inst_id_institucion', $id)->first() : null;
    }
}
