<?php

namespace App\Services\Pdm;

/**
 * Resultado inmutable de una regla de normalización.
 */
final class ResultadoNormalizacion
{
    private function __construct(
        public readonly ?string $original,
        public readonly ?string $valor,
        public readonly string $regla,
        public readonly bool $revision,
        public readonly ?string $motivo = null,
        public readonly int|false|null $id = null,
    ) {
    }

    public static function valido(?string $original, ?string $valor, string $regla, int|false|null $id = null): self
    {
        return new self($original, $valor, $regla, false, null, $id);
    }

    public static function revision(?string $original, string $regla, string $motivo): self
    {
        return new self($original, null, $regla, true, $motivo);
    }
}
