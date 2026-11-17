<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_probatorios_solicitud', function (Blueprint $table) {
            $table->string('solicitud_articulo_14_archivo')->nullable();
            $table->string('constancia_articulo_14_archivo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('documentos_probatorios_solicitud', function (Blueprint $table) {
            $table->dropColumn([
                'solicitud_articulo_14_archivo',
                'constancia_articulo_14_archivo',
            ]);
        });
    }
};
