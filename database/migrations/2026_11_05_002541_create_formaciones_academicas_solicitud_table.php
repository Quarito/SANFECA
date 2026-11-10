<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formaciones_academicas_solicitud', function (Blueprint $table) {
            $table->id();

            $table->foreignId('solicitud_id')
                ->unique()
                ->constrained('solicitudes_certificacion')
                ->restrictOnDelete();

            $table->string('nivel_origen')->nullable();
            $table->string('titulo_origen')->nullable();

            $table->string('nivel_ultimo_grado')->nullable();
            $table->string('titulo_ultimo_grado')->nullable();

            $table->foreignId('institucion_afiliada_id')
                ->nullable()
                ->constrained('instituciones_afiliadas')
                ->restrictOnDelete();

            $table->string('titulo_origen_archivo')->nullable();
            $table->string('cedula_origen_archivo')->nullable();

            $table->string('titulo_ultimo_grado_archivo')->nullable();
            $table->string('cedula_ultimo_grado_archivo')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formaciones_academicas_solicitud');
    }
};
