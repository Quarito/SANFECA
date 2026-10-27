<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formaciones_academicas', function (Blueprint $table) {
            $table->string('titulo_origen_archivo')->nullable();
            $table->string('cedula_origen_archivo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('formaciones_academicas', function (Blueprint $table) {
            $table->dropColumn([
                'titulo_origen_archivo',
                'cedula_origen_archivo'
            ]);
        });
    }
};

