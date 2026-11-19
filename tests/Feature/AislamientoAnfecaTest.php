<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class AislamientoAnfecaTest extends TestCase
{
    public function test_utiliza_sqlite_en_memoria(): void
    {
        $this->assertSame(
            'testing',
            app()->environment()
        );

        $this->assertSame(
            'sqlite',
            config('database.default')
        );

        $this->assertSame(
            ':memory:',
            config('database.connections.sqlite.database')
        );

        $this->assertSame(
            'sqlite',
            DB::connection()->getDriverName()
        );
    }
}
