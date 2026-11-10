<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instituciones_afiliadas', function (Blueprint $table) {
            $table->foreignId('zona_id')
                ->nullable()
                ->constrained('zonas')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('instituciones_afiliadas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zona_id');
        });
    }
};
