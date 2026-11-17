<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_certificacion', function (Blueprint $table) {
            $table->string('tipo_personal', 100)->nullable();
            $table->string('area_certificacion', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_certificacion', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_personal',
                'area_certificacion',
            ]);
        });
    }
};
