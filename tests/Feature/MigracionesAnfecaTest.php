<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class MigracionesAnfecaTest extends TestCase
{
    use RefreshDatabase;

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

        $this->refreshTestDatabase();
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
