<?php

namespace App\Services\Pdm;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * Convierte los errores controlados de los disparadores anfeca_compat
 * ('ANFECA_VALIDACION|campo|mensaje') en errores de validación del formulario.
 * Cualquier otra falla de base de datos se deja tal cual (y se registra en el log).
 */
class ErrorValidacionPdm
{
    public const MARCA = 'ANFECA_VALIDACION|';

    public static function desde(QueryException $e): ?ValidationException
    {
        $texto = $e->getPrevious()?->getMessage() ?? $e->getMessage();
        $inicio = strpos($texto, self::MARCA);
        if ($inicio === false) {
            return null;
        }

        // Formato: ANFECA_VALIDACION|campo|mensaje (el mensaje termina en el salto de línea de PostgreSQL).
        $partes = explode('|', strtok(substr($texto, $inicio), "\n"), 3);
        $campo = preg_match('/^[a-z0-9_]+$/', $partes[1] ?? '') ? $partes[1] : 'pdm';
        $mensaje = trim($partes[2] ?? '') ?: \App\Services\Pdm\Espejo\EspejoPdm::MENSAJE_GENERAL;

        return ValidationException::withMessages([$campo => $mensaje]);
    }
}
