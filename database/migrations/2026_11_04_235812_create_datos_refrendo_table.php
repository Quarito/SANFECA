<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datos_refrendo', function (Blueprint $table) {
            $table->id();

            $table->foreignId('solicitud_id')
                ->unique()
                ->constrained('solicitudes_certificacion')
                ->restrictOnDelete();

            $table->string('numero_refrendo_asignado', 50)
                ->nullable();

            $table->unsignedInteger('refrendo_actual')
                ->nullable();

            $table->boolean('aplica_articulo_14')
                ->nullable();

            $table->string('inciso_articulo_14', 20)
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datos_refrendo');
    }
};
