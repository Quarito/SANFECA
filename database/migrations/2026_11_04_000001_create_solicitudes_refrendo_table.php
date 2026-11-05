<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_refrendo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('certificado_historico_id')->constrained('certificados_historicos')->restrictOnDelete();
            $table->unsignedSmallInteger('ciclo');
            $table->unsignedSmallInteger('numero_refrendo');
            $table->string('numero_certificado');
            $table->boolean('articulo_14');
            $table->string('inciso', 4)->nullable();
            $table->json('seleccion');
            $table->json('formacion')->nullable();
            $table->json('documentos')->nullable();
            $table->json('datos_personales')->nullable();
            $table->timestamp('completado_at')->nullable();
            $table->timestamps();
            $table->unique(['certificado_historico_id', 'ciclo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_refrendo');
    }
};
