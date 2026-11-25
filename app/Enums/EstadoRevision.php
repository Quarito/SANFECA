<?php

namespace App\Enums;

/** Estado de revisión mostrado por documento: resultado vigente o estado calculado. */
enum EstadoRevision: string
{
    case PorRevisar = 'por_revisar';
    case Validado = 'validado';
    case ConObservaciones = 'con_observaciones';
    case NoValido = 'no_valido';
    case NoAplica = 'no_aplica';

    public static function desdeResultado(ResultadoRevision $r): self
    {
        return self::from($r->value);
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::PorRevisar => 'Por revisar',
            self::Validado => 'Validado',
            self::ConObservaciones => 'Con observaciones',
            self::NoValido => 'No válido',
            self::NoAplica => 'No aplica',
        };
    }
}
