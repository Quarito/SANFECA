<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados_historicos', function (Blueprint $table) {
            $table->id();

            // Identificación histórica
            $table->string('zona')->nullable();
            $table->string('nombre_historico')->nullable();
            $table->string('correo_historico')->nullable();
            $table->string('apellido_paterno')->nullable();
            $table->string('apellido_materno')->nullable();
            $table->string('nombres')->nullable();
            $table->string('curp_historica', 18)->nullable();

            // Información institucional
            $table->string('institucion_historica')->nullable();

            // Certificación y refrendos
            $table->string('certificacion')->nullable();
            $table->string('primera_vez')->nullable();
            $table->string('refrendo_1')->nullable();
            $table->string('refrendo_2')->nullable();
            $table->string('refrendo_3')->nullable();
            $table->string('refrendo_4')->nullable();
            $table->string('refrendo_5')->nullable();
            $table->string('refrendo_6')->nullable();
            $table->string('refrendo_7')->nullable();

            // Control administrativo
            $table->text('columna_sin_encabezado')->nullable();
            $table->string('refrendo_correspondiente')->nullable();
            $table->string('numero_certificado')->nullable();

            // Trazabilidad de la importación
            $table->string('lote_importacion')->nullable();
            $table->unsignedInteger('fila_origen')->nullable();

            // Vinculación futura con usuarios
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('estado_vinculacion')
                ->default('pendiente');

            $table->timestamps();

            // Búsquedas administrativas
            $table->index('numero_certificado');
            $table->index('curp_historica');
            $table->index('correo_historico');

            // Evitar importar dos veces la misma fila del mismo lote
            $table->unique(['lote_importacion', 'fila_origen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_historicos');
    }
};
