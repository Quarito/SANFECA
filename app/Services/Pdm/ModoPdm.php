<?php

namespace App\Services\Pdm;

use RuntimeException;

/**
 * Modo de integración con el esquema anfeca_modelo (ANFECA_PDM_MODO).
 *
 *  apagado   → Laravel usa solo public (comportamiento actual).
 *  espejo    → escribe en public y en el PDM en la misma transacción (fase F3).
 *  principal → lee del PDM y mantiene public como espejo (fase F4).
 *  exclusivo → solo PDM; public de dominio queda como archivo (fase F5).
 *
 * En F1 solo existe la infraestructura: cualquier modo distinto de "apagado"
 * se rechaza para que nadie lo active antes de tiempo.
 */
class ModoPdm
{
    public const APAGADO = 'apagado';
    public const ESPEJO = 'espejo';
    public const PRINCIPAL = 'principal';
    public const EXCLUSIVO = 'exclusivo';

    public const VALIDOS = [self::APAGADO, self::ESPEJO, self::PRINCIPAL, self::EXCLUSIVO];

    /** Modos ya implementados y probados. Se amplía fase a fase. */
    public const IMPLEMENTADOS = [self::APAGADO];

    public static function actual(): string
    {
        $modo = strtolower(trim((string) config('anfeca_pdm.modo', self::APAGADO)));

        if (!in_array($modo, self::VALIDOS, true)) {
            throw new RuntimeException("ANFECA_PDM_MODO='{$modo}' no es válido. Valores: ".implode(', ', self::VALIDOS).'.');
        }

        if (!in_array($modo, self::IMPLEMENTADOS, true)) {
            throw new RuntimeException("ANFECA_PDM_MODO='{$modo}' aún no está implementado en esta fase. Use 'apagado'.");
        }

        return $modo;
    }

    public static function apagado(): bool
    {
        return self::actual() === self::APAGADO;
    }
}
