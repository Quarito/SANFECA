<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_probatorios_solicitud', function (Blueprint $table) {
            $table->id();

            $table->foreignId('solicitud_id')
                ->unique()
                ->constrained('solicitudes_certificacion')
                ->restrictOnDelete();

            $table->string('constancia_antiguedad_archivo')
                ->nullable();

            $table->string('comprobante_pago_archivo')
                ->nullable();

            $table->text('link')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_probatorios_solicitud');
    }
};
