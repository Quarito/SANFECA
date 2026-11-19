<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_certificacion', function (Blueprint $table) {
            $table->boolean('compromiso_aceptado')
                ->default(false);

            $table->timestamp('compromiso_aceptado_en')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_certificacion', function (Blueprint $table) {
            $table->dropColumn([
                'compromiso_aceptado',
                'compromiso_aceptado_en',
            ]);
        });
    }
};
