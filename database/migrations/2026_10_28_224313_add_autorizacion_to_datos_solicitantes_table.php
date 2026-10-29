<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('datos_solicitantes', function (Blueprint $table) {
        $table->boolean('datos_autorizados')->default(false);
        $table->timestamp('fecha_autorizacion')->nullable();
    });
}

public function down(): void
{
    Schema::table('datos_solicitantes', function (Blueprint $table) {
        $table->dropColumn([
            'datos_autorizados',
            'fecha_autorizacion',
        ]);
    });
}
};
