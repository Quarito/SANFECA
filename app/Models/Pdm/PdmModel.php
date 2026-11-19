<?php

namespace App\Models\Pdm;

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
}
