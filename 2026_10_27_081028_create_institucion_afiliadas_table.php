<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instituciones_afiliadas', function (Blueprint $table) {
            $table->id();
            $table->string('numero_afiliacion', 30)->unique();
            $table->string('nombre', 255);
            $table->string('sede', 150)->nullable();
            $table->string('zona_anfeca', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instituciones_afiliadas');
    }
};

