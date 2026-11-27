<?php

namespace App\Models\Pdm;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base de los modelos del esquema anfeca_modelo (PowerDesigner).
 *
 * - Misma conexión que Laravel (pgsql); las tablas van calificadas con el esquema,
 *   así una misma transacción abarca public y anfeca_modelo.
 * - El PDM no tiene created_at/updated_at: sus fechas son columnas de negocio.
 * - Solo funcionan sobre PostgreSQL con el esquema instalado.
 */
abstract class PdmModel extends Model
{
    public $timestamps = false;

    /** @var list<string> Columnas adicionales de la PK compuesta. */
    protected array $columnasClaveCompuesta = [];

    protected function setKeysForSelectQuery($query): Builder
    {
        return $this->agregarClaveCompuesta(parent::setKeysForSelectQuery($query));
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $this->agregarClaveCompuesta(parent::setKeysForSaveQuery($query));
    }

    private function agregarClaveCompuesta(Builder $query): Builder
    {
        foreach ($this->columnasClaveCompuesta as $columna) {
            $query->where($columna, $this->getRawOriginal($columna, $this->getAttribute($columna)));
        }

        return $query;
    }
}
