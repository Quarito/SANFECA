<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Con la configuración en caché, Laravel ignora las variables de phpunit.xml
     * y las pruebas usarían la base real configurada en .env. Se detiene todo.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        // Aislamiento (incidente 2026-11-25): un enlace simbólico a vendor hizo que Laravel tomara como
        // raíz /var/www/ANFECA_ENSAYO mientras las pruebas corrían en una copia. Se exige que la raíz de
        // la aplicación sea el directorio desde el que se ejecutan las pruebas y que no sea un sitio publicado.
        $raiz = realpath($app->basePath()) ?: $app->basePath();
        $actual = realpath((string) getcwd()) ?: (string) getcwd();
        if ($raiz !== $actual) {
            throw new RuntimeException("SEGURIDAD: la raíz de Laravel ({$raiz}) no coincide con el directorio de las pruebas ({$actual}). "
                .'¿Hay enlaces simbólicos a vendor? Use scripts/anfeca/pruebas/arbol_aislado.sh.');
        }
        if (str_starts_with($raiz, '/var/www/') && getenv('ANFECA_PRUEBAS_EN_SITIO') !== 'autorizado') {
            throw new RuntimeException("SEGURIDAD: no se ejecutan pruebas dentro de un sitio publicado ({$raiz}). "
                .'Use un árbol aislado (scripts/anfeca/pruebas/arbol_aislado.sh).');
        }

        if ($app->configurationIsCached()) {
            throw new RuntimeException(
                'SEGURIDAD: la configuración está en caché (bootstrap/cache/config.php). '
                .'Ejecute "php artisan config:clear" antes de las pruebas.'
            );
        }

        return $app;
    }
}
