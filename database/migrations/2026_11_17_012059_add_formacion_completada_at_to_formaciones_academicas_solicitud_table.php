<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formaciones_academicas_solicitud', function (Blueprint $table) {
            $table->timestamp('formacion_completada_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('formaciones_academicas_solicitud', function (Blueprint $table) {
            $table->dropColumn('formacion_completada_at');
        });
    }
};

