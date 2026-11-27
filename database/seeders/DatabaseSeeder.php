<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * ANFECA sobre las 36 tablas oficiales: los catálogos se cargan con «php artisan anfeca:catalogos».
 * No se crean usuarios de prueba ni estructuras.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('anfeca:catalogos', [], $this->command?->getOutput());
    }
}
