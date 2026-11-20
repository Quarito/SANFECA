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
 *  principal → lee y escribe en el PDM a través de las vistas anfeca_compat; los disparadores
 *               replican en public (respaldo para revertir) (F4).
 *  exclusivo → solo PDM; public queda congelado y protegido por la guardia (F5).
 *
 * MODO EFECTIVO = DOS LLAVES:
 *  1) ANFECA_PDM_MODO=<modo> en .env (config anfeca_pdm.modo), y
 *  2) fila de activación en anfeca_modelo.integracion_control para ESTA base y el MISMO modo,
 *     escrita solo por "php artisan anfeca:pdm-modo <modo>" tras sus verificaciones.
 * Si falta cualquiera de las dos, el modo efectivo es "apagado" (comportamiento actual).
 * En principal/exclusivo, además, debe existir la capa anfeca_compat.
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
    public const IMPLEMENTADOS = [self::APAGADO, self::ESPEJO, self::PRINCIPAL, self::EXCLUSIVO];

    /** Modos que leen y escriben a través de las vistas anfeca_compat. */
    public const SOBRE_VISTAS = [self::PRINCIPAL, self::EXCLUSIVO];

    /** Modo solicitado en la configuración (.env). Lanza excepción si no es válido o no está implementado. */
    public static function actual(): string
    {
        $modo = self::configurado();

        if (!in_array($modo, self::VALIDOS, true)) {
            throw new RuntimeException("ANFECA_PDM_MODO='{$modo}' no es válido. Valores: ".implode(', ', self::VALIDOS).'.');
        }

        if (!in_array($modo, self::IMPLEMENTADOS, true)) {
            throw new RuntimeException("ANFECA_PDM_MODO='{$modo}' no está implementado.");
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

        if (!in_array($modo, self::IMPLEMENTADOS, true)) {
            Log::critical("ANFECA_PDM_MODO='{$modo}' no es válido: se opera en modo apagado.");

            return self::APAGADO;
        }

        $activacion = self::activacion();

        if ($activacion === null || $activacion->ic_modo !== $modo
            || $activacion->ic_base_datos !== DB::connection()->getDatabaseName()) {
            Log::warning("ANFECA_PDM_MODO={$modo} sin activación registrada para esta base: se opera en modo apagado. Use anfeca:pdm-modo {$modo}.");

            return self::APAGADO;
        }

        if (in_array($modo, self::SOBRE_VISTAS, true) && !self::capaCompatibilidad()) {
            Log::critical("ANFECA_PDM_MODO={$modo} sin la capa anfeca_compat instalada: se opera en modo apagado.");

            return self::APAGADO;
        }

        return $modo;
    }

    /** principal o exclusivo: la aplicación debe operar sobre las vistas anfeca_compat. */
    public static function sobreVistas(): bool
    {
        return in_array(self::efectivo(), self::SOBRE_VISTAS, true);
    }

    public static function capaCompatibilidad(): bool
    {
        try {
            return DB::connection()->getDriverName() === 'pgsql'
                && (bool) DB::selectOne("select to_regnamespace('anfeca_compat') as n")->n;
        } catch (Throwable) {
            return false;
        }
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
