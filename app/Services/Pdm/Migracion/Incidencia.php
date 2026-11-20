<?php

namespace App\Services\Pdm\Migracion;

/**
 * Hallazgo de la planeación de la migración.
 *
 * - Bloqueante: impide "--ejecutar" hasta que exista una decisión registrada.
 * - Informativa: se documenta, pero no impide la migración (no hay pérdida de datos:
 *   public conserva el dato original).
 *
 * Los mensajes contienen solo IDs y valores no personales.
 */
final class Incidencia
{
    public function __construct(
        public readonly string $codigo,
        public readonly bool $bloqueante,
        public readonly string $tabla,
        public readonly int|string|null $id,
        public readonly string $mensaje,
        public readonly ?string $decision = null,
    ) {
    }

    public function aArreglo(): array
    {
        return [
            'codigo' => $this->codigo,
            'bloqueante' => $this->bloqueante,
            'decision' => $this->decision,
            'tabla' => $this->tabla,
            'id' => $this->id,
            'mensaje' => $this->mensaje,
        ];
    }
}
