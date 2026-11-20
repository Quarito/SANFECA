<?php

namespace Tests\Pdm;

/**
 * Reglas que deben cumplirse para ejecutar pruebas que reconstruyen una base PostgreSQL.
 * Lógica pura (sin conexión) para poder probarla en la suite normal.
 */
final class GuardiaBaseAislada
{
    /**
     * @return list<string> motivos de rechazo; vacío = base aislada válida
     */
    public static function motivosDeRechazo(
        string $driver,
        string $bdConfigurada,
        ?string $bdReal,
        string $host,
        ?string $direccionServidor,
        ?string $bdPermitida,
        string $sufijo,
        bool $configuracionEnCache,
        ?string $url,
    ): array {
        $motivos = [];

        if ($configuracionEnCache) {
            $motivos[] = 'la configuración de Laravel está en caché (bootstrap/cache/config.php); ejecute php artisan config:clear';
        }
        if ($url !== null && $url !== '') {
            $motivos[] = 'DB_URL está definida y tendría prioridad sobre host y base';
        }
        if ($driver !== 'pgsql') {
            $motivos[] = "la conexión es '{$driver}', no pgsql";
        }
        if ($sufijo === '' || !str_ends_with($bdConfigurada, $sufijo)) {
            $motivos[] = "la base configurada '{$bdConfigurada}' no termina en '{$sufijo}'";
        }
        if ($bdReal !== null && $bdReal !== $bdConfigurada) {
            $motivos[] = "PostgreSQL reporta la base '{$bdReal}', distinta de la configurada '{$bdConfigurada}'";
        }
        foreach (array_filter([$bdConfigurada, $bdReal]) as $bd) {
            if ($bdPermitida !== null && $bd === $bdPermitida) {
                $motivos[] = "'{$bd}' es la base de pruebas en uso";
            }
        }
        if (!in_array($host, ['127.0.0.1', 'localhost', '::1', ''], true) && !str_starts_with($host, '/')) {
            $motivos[] = "el host '{$host}' no es local";
        }
        if ($direccionServidor !== null && !in_array($direccionServidor, ['127.0.0.1', '::1'], true)) {
            $motivos[] = "el servidor PostgreSQL responde desde '{$direccionServidor}', no desde la máquina local";
        }

        return array_values(array_unique($motivos));
    }
}
