<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formaciones_academicas', function (Blueprint $table) {
            $table->string('titulo_ultimo_grado_archivo')->nullable();
            $table->string('cedula_ultimo_grado_archivo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('formaciones_academicas', function (Blueprint $table) {
            $table->dropColumn([
                'titulo_ultimo_grado_archivo',
                'cedula_ultimo_grado_archivo',
            ]);
        });
    }
};
