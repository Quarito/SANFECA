<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formaciones_academicas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('nivel_origen', 100);
            $table->string('titulo_origen', 200);
            $table->string('nivel_ultimo_grado', 100);
            $table->string('titulo_ultimo_grado', 200);

            $table->foreignId('institucion_afiliada_id')
                ->constrained('instituciones_afiliadas');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formaciones_academicas');
    }
};

