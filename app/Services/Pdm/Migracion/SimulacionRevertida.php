<?php

namespace App\Services\Pdm\Migracion;

use RuntimeException;

/** Señal interna para revertir la transacción de una simulación correcta. */
final class SimulacionRevertida extends RuntimeException
{
}
