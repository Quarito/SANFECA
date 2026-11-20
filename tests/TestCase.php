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

        if ($app->configurationIsCached()) {
            throw new RuntimeException(
                'SEGURIDAD: la configuración está en caché (bootstrap/cache/config.php). '
                .'Ejecute "php artisan config:clear" antes de las pruebas.'
            );
        }

        return $app;
    }
}
