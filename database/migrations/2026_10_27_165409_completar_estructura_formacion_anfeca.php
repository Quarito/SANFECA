<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Completar instituciones
        Schema::table('instituciones_afiliadas', function (Blueprint $table) {
            $table->string('numero_afiliacion')->nullable()->unique();
            $table->string('nombre')->nullable();
            $table->string('sede')->nullable();
            $table->string('zona_anfeca')->nullable();
        });

        // 2. Completar formación académica
        Schema::table('formaciones_academicas', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->constrained('users');

            $table->string('nivel_origen')->nullable();
            $table->string('titulo_origen')->nullable();

            $table->string('nivel_ultimo_grado')->nullable();
            $table->string('titulo_ultimo_grado')->nullable();

            $table->foreignId('institucion_afiliada_id')
                ->nullable()
                ->constrained('instituciones_afiliadas');
        });

        // 3. Agregar documentos únicamente si faltan
        if (!Schema::hasColumn(
            'formaciones_academicas',
            'titulo_origen_archivo'
        )) {
            Schema::table('formaciones_academicas', function (Blueprint $table) {
                $table->string('titulo_origen_archivo')->nullable();
            });
        }

        if (!Schema::hasColumn(
            'formaciones_academicas',
            'cedula_origen_archivo'
        )) {
            Schema::table('formaciones_academicas', function (Blueprint $table) {
                $table->string('cedula_origen_archivo')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Reversión de los campos agregados por esta migración.
        // No elimina las tablas ni los registros existentes.
        Schema::table('formaciones_academicas', function (Blueprint $table) {
            $table->dropForeign(['institucion_afiliada_id']);
            $table->dropForeign(['user_id']);

            $table->dropUnique(['user_id']);

            $table->dropColumn([
                'user_id',
                'nivel_origen',
                'titulo_origen',
                'nivel_ultimo_grado',
                'titulo_ultimo_grado',
                'institucion_afiliada_id',
            ]);
        });

        Schema::table('instituciones_afiliadas', function (Blueprint $table) {
            $table->dropUnique(['numero_afiliacion']);

            $table->dropColumn([
                'numero_afiliacion',
                'nombre',
                'sede',
                'zona_anfeca',
            ]);
        });

        // Las columnas de documentos se conservan para evitar
        // eliminar datos de una migración anterior.
    }
};
