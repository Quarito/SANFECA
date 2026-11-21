<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class MigracionesAnfecaTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refreshDatabaseLaravel;
    }

    public function refreshDatabase(): void
    {
        if (
            !app()->environment('testing') ||
            config('database.default') !== 'sqlite' ||
            config('database.connections.sqlite.database') !== ':memory:' ||
            DB::connection()->getDriverName() !== 'sqlite'
        ) {
            throw new RuntimeException(
                'SEGURIDAD: las migraciones requieren SQLite en memoria.'
            );
        }

        // Tras la guardia, se delega en el método original del trait, que también
        // restaura la conexión en memoria entre pruebas (independiente del orden).
        $this->refreshDatabaseLaravel();
    }

    public function test_tablas_principales_existen(): void
    {
        $this->assertTrue(Schema::hasTable('users'));

        $this->assertTrue(
            Schema::hasTable('datos_solicitantes')
        );

        $this->assertTrue(
            Schema::hasTable('solicitudes_certificacion')
        );
    }
}
