<?php

namespace App\Services\Pdm;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Modo de integración con el esquema anfeca_modelo.
 *
 *  apagado   → Laravel usa solo public (comportamiento actual).
 *  espejo    → escribe en public y en el PDM en la misma transacción; lee de public (F3).
 *  principal → lee del PDM (F4, NO implementado).
 *  exclusivo → solo PDM (F5, NO implementado).
 *
 * MODO EFECTIVO = DOS LLAVES:
 *  1) ANFECA_PDM_MODO=espejo en .env (config anfeca_pdm.modo), y
 *  2) fila de activación en anfeca_modelo.integracion_control para ESTA base, escrita
 *     solo por "php artisan anfeca:pdm-modo espejo" tras conciliar con 0 diferencias.
 * Si falta cualquiera de las dos, el modo efectivo es "apagado" (comportamiento actual).
 * Con ANFECA_PDM_MODO=apagado no se consulta la base: costo cero para la aplicación actual.
 */
class ModoPdm
{
    public const APAGADO = 'apagado';
    public const ESPEJO = 'espejo';
    public const PRINCIPAL = 'principal';
    public const EXCLUSIVO = 'exclusivo';

    public const VALIDOS = [self::APAGADO, self::ESPEJO, self::PRINCIPAL, self::EXCLUSIVO];

    /** Modos implementados y probados. Se amplía fase a fase. */
    public const IMPLEMENTADOS = [self::APAGADO, self::ESPEJO];

    /** Modo solicitado en la configuración (.env). Lanza excepción si no es válido o no está implementado. */
    public static function actual(): string
    {
        $modo = self::configurado();

        if (!in_array($modo, self::VALIDOS, true)) {
            throw new RuntimeException("ANFECA_PDM_MODO='{$modo}' no es válido. Valores: ".implode(', ', self::VALIDOS).'.');
        }

        if (!in_array($modo, self::IMPLEMENTADOS, true)) {
            throw new RuntimeException("ANFECA_PDM_MODO='{$modo}' aún no está implementado en esta fase. Use 'apagado' o 'espejo'.");
        }

        return $modo;
    }

    public static function configurado(): string
    {
        return strtolower(trim((string) config('anfeca_pdm.modo', self::APAGADO)));
    }

    /**
     * Modo con el que opera la aplicación. Nunca lanza excepción: ante cualquier
     * duda devuelve "apagado" (la aplicación sigue funcionando sobre public).
     */
    public static function efectivo(): string
    {
        $modo = self::configurado();

        if ($modo === self::APAGADO) {
            return self::APAGADO;
        }

        if ($modo !== self::ESPEJO) {
            Log::critical("ANFECA_PDM_MODO='{$modo}' no está implementado: se opera en modo apagado.");

            return self::APAGADO;
        }

        $activacion = self::activacion();

        if ($activacion === null || $activacion->ic_modo !== self::ESPEJO
            || $activacion->ic_base_datos !== DB::connection()->getDatabaseName()) {
            Log::warning('ANFECA_PDM_MODO=espejo sin activación registrada para esta base: se opera en modo apagado. Use anfeca:pdm-modo espejo.');

            return self::APAGADO;
        }

        return self::ESPEJO;
    }

    public static function apagado(): bool
    {
        return self::efectivo() === self::APAGADO;
    }

    public static function espejo(): bool
    {
        return self::efectivo() === self::ESPEJO;
    }

    /** Fila de anfeca_modelo.integracion_control, o null si no existe la tabla o la fila. */
    public static function activacion(): ?object
    {
        try {
            if (DB::connection()->getDriverName() !== 'pgsql'
                || !DB::selectOne("select to_regclass('anfeca_modelo.integracion_control') as t")->t) {
                return null;
            }

            return DB::table('anfeca_modelo.integracion_control')->where('ic_id', 1)->first();
        } catch (Throwable $e) {
            Log::warning('No se pudo leer la activación del modo PDM: '.$e->getMessage());

            return null;
        }
    }
}
