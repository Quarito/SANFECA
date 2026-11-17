<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('historial_documentos_academicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')
                ->constrained('solicitudes_certificacion')
                ->restrictOnDelete();
            $table->string('tipo_documento', 80);
            $table->text('ruta_archivo');
            $table->timestamps();

            $table->index(['solicitud_id', 'tipo_documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_documentos_academicos');
    }
};
