<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega exclusivamente «escuela_facultad» (string 255, nullable) a instituciones_afiliadas.
 *
 * - Idempotente: si la columna ya existe (p. ej. la base de ensayo, donde se agregó
 *   antes de versionar esta migración), no hace nada.
 * - «direccion» NO forma parte de esta migración: se agregó por error durante las
 *   pruebas y su retiro se tratará por separado (docs/anfeca/DESPLIEGUE_SERVIDOR_NUEVO.md).
 *
 * Reversión NO destructiva (decisión de esta entrega): down() no elimina la columna.
 * No es posible distinguir con certeza una columna creada por up() de una preexistente
 * con datos capturados; borrarla en un rollback perdería información. Si alguna vez se
 * requiere retirarla, debe hacerse con una migración explícita y previo respaldo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('instituciones_afiliadas', 'escuela_facultad')) {
            return;
        }

        Schema::table('instituciones_afiliadas', function (Blueprint $table) {
            $table->string('escuela_facultad', 255)->nullable();
        });
    }

    public function down(): void
    {
        // Intencionalmente vacío: reversión no destructiva (ver encabezado).
    }
};
