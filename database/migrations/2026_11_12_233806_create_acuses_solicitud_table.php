<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acuses_solicitud', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->unique()
                ->constrained('solicitudes_certificacion')->restrictOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->json('datos');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acuses_solicitud');
    }
};
