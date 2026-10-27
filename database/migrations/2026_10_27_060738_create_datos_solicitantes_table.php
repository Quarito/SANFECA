<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datos_solicitantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('curp', 18)->unique();
            $table->string('rfc', 13)->unique();
            $table->string('cedula_profesional', 30)->nullable();
            $table->string('nombres', 150);
            $table->string('apellido_paterno', 100);
            $table->string('apellido_materno', 100)->nullable();

            $table->string('calle', 200);
            $table->string('numero_exterior', 20);
            $table->string('numero_interior', 20)->nullable();
            $table->string('codigo_postal', 5);
            $table->string('entidad_federativa', 100);
            $table->string('colonia', 150);
            $table->string('municipio', 150);

            $table->string('correo_electronico', 255);
            $table->string('telefono_particular', 20)->nullable();
            $table->string('telefono_celular', 20)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datos_solicitantes');
    }
};

