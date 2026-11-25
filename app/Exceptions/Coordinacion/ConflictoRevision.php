<?php

namespace App\Exceptions\Coordinacion;

use RuntimeException;

/** Conflicto de concurrencia, versión o idempotencia: se responde con HTTP 409. */
class ConflictoRevision extends RuntimeException
{
    public function __construct(
        public readonly string $motivo,
        string $mensaje,
        public readonly array $contexto = [],
    ) {
        parent::__construct($mensaje);
    }
}
