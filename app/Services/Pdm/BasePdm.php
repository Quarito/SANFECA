<?php

namespace App\Services\Pdm;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BasePdm
{
    public const OFICIAL = 'certificacion_anfeca';

    public const INSTITUCIONALES = [
        self::OFICIAL,
        'bd_anfeca_ensayo_pruebas_pg',
        'bd_laravelanfeca_pruebas',
    ];

    public static function protegida(string $base): bool
    {
        return in_array($base, self::INSTITUCIONALES, true) || $base === config('anfeca_modelo.bd_permitida');
    }

    public static function descartable(string $base, ?string $direccion): bool
    {
        $sufijo = (string) config('anfeca_pdm.sufijo_bd_pruebas');

        $identidadTemporal = in_array($base, ['bd_anfeca_pruebas_pg', 'bd_anfeca_desechable_pruebas_pg'], true)
            || preg_match('/^bd_anfeca_copia_[0-9]{14}_[A-Za-z0-9]{8}_pruebas_pg$/D', $base) === 1;

        return $identidadTemporal && ! self::protegida($base) && $sufijo !== '' && str_ends_with($base, $sufijo)
            && in_array($direccion, [null, '127.0.0.1', '::1'], true);
    }

    public static function verificarEscritura(string $variable): void
    {
        $identidad = DB::selectOne('select current_database() as base, host(inet_server_addr()) as direccion');
        if ($identidad->base !== DB::connection()->getDatabaseName()) {
            throw new RuntimeException('PostgreSQL reporta una base distinta de la conexión configurada.');
        }

        if (self::descartable($identidad->base, $identidad->direccion)) {
            return;
        }

        if ($identidad->base !== config('anfeca_modelo.bd_permitida')) {
            throw new RuntimeException("Base '{$identidad->base}' no permitida para escritura PDM.");
        }

        if (env($variable) !== 'si') {
            throw new RuntimeException("Base protegida '{$identidad->base}': defina {$variable}=si solo en el comando autorizado, tras verificar el respaldo.");
        }
    }
}
