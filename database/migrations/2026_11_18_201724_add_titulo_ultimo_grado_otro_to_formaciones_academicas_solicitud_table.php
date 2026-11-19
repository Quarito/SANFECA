<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar el título personalizado del último grado.
     */
    public function up(): void
    {
        Schema::table('formaciones_academicas_solicitud', function (Blueprint $table) {
            $table->string('titulo_ultimo_grado_otro', 200)->nullable();
        });
    }

    /**
     * Revertir la modificación.
     */
    public function down(): void
    {
        Schema::table('formaciones_academicas_solicitud', function (Blueprint $table) {
            $table->dropColumn('titulo_ultimo_grado_otro');
        });
    }
};

