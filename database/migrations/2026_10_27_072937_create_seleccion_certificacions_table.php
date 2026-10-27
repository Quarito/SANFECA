<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selecciones_certificacion', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('certificacion', 20);
            $table->string('tipo_personal', 100);
            $table->string('area_certificacion', 150);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('selecciones_certificacion');
    }
};

