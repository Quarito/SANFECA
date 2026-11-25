<?php

namespace App\Enums;

/**
 * Resultados EXPLÍCITOS de una revisión documental (los únicos que se guardan).
 * «Por revisar» y «No aplica» son estados CALCULADOS: ver EstadoRevision.
 */
enum ResultadoRevision: string
{
    case Validado = 'validado';
    case ConObservaciones = 'con_observaciones';
    case NoValido = 'no_valido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Validado => 'Validado',
            self::ConObservaciones => 'Con observaciones',
            self::NoValido => 'No válido',
        };
    }

    /** Los resultados desfavorables exigen observaciones. */
    public function exigeObservaciones(): bool
    {
        return $this !== self::Validado;
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
