<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_certificacion', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('tipo_tramite', 20);

            $table->string('certificacion', 20);

            $table->string('estado', 20)
                ->default('borrador');

            $table->timestamp('fecha_envio')->nullable();

            $table->timestamps();

            $table->index(
                ['user_id', 'tipo_tramite'],
                'solicitudes_usuario_tipo_idx'
            );

            $table->index(
                ['user_id', 'estado'],
                'solicitudes_usuario_estado_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_certificacion');
    }
};
