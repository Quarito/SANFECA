<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NombreConMayusculasYMinusculas implements ValidationRule
{
    /**
     * Determina si un string cumple con la condición de tener mayúsculas y minúsculas.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        // La regla "string" se encarga de rechazar otros tipos.
        if (!is_string($value)) {
            return;
        }

        $tieneMayuscula = preg_match('/\p{Lu}/u', $value) === 1;
        $tieneMinuscula = preg_match('/\p{Ll}/u', $value) === 1;

        if (!$tieneMayuscula || !$tieneMinuscula) {
            $etiqueta = match ($attribute) {
                'nombres' => 'Nombre(s)',
                'apellido_paterno' => 'Apellido paterno',
                'apellido_materno' => 'Apellido materno',
                'calle' => 'Calle',
                'colonia' => 'Colonia',
                'municipio' => 'Municipio',
                'entidad_federativa' => 'Entidad federativa',
                default => $attribute,
            };

            $fail(
                "El campo {$etiqueta} debe combinar mayúsculas y minúsculas."
            );
        }
    }
}
