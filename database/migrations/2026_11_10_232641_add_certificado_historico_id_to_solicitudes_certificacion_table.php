<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_certificacion', function (Blueprint $table) {
            $table->foreignId('certificado_historico_id')
                ->nullable()
                ->constrained('certificados_historicos')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_certificacion', function (Blueprint $table) {
            $table->dropConstrainedForeignId('certificado_historico_id');
        });
    }
};
