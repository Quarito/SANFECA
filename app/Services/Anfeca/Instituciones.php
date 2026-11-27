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
            'i.ins_coord_regional_cert_academi as coordinacion_regional',
        ])->selectSub(DB::table('sede as s')->whereColumn('s.inst_id_institucion', 'i.inst_id_institucion')
            ->orderBy('s.sede_id_sede')->limit(1)->select('s.sede_descripcion'), 'sede')
            ->selectRaw('null::varchar as campus');
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
