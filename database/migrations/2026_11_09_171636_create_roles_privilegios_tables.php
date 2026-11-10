<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 20)->unique();
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('privilegios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('rol_privilegio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rol_id')
                ->constrained('roles')
                ->cascadeOnDelete();

            $table->foreignId('privilegio_id')
                ->constrained('privilegios')
                ->cascadeOnDelete();

            $table->unique(['rol_id', 'privilegio_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_privilegio');
        Schema::dropIfExists('privilegios');
        Schema::dropIfExists('roles');
    }
};

