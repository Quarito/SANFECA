<?php

/*
|--------------------------------------------------------------------------
| PROPUESTA — Catálogo de posgrados · columnas ADITIVAS del último grado
|--------------------------------------------------------------------------
| ESTADO: NO aplicada en 8086 (fuera de database/migrations: «php artisan migrate» no la ejecuta).
| Aplicación autorizada futura, SOLO con --path, después de un respaldo verificado
| (docs/anfeca/catalogos/PROPUESTA_CATALOGO_POSGRADOS.md §9).
|
| Tabla: formaciones_academicas_solicitud (una fila por solicitud; ahí viven nivel y área del último grado).
| No cambia el significado de los campos existentes:
|   nivel_ultimo_grado          → nivel                   (sin cambios)
|   titulo_ultimo_grado         → ÁREA académica          (sin cambios: 5 valores)
|   titulo_ultimo_grado_otro    → título libre del área «Otro» (sin cambios; con el catálogo activo se
|                                 refleja aquí el título del área Otro para que el formulario anterior,
|                                 el acuse y la coordinación lo sigan mostrando)
| Columnas nuevas (todas NULL; los registros históricos quedan en NULL):
|   titulo_ultimo_grado_especifico varchar(300) → nombre EXACTO (del catálogo o capturado a mano)
|   origen_titulo_ultimo_grado     varchar(20)  → 'catalogo' | 'manual'
|   clave_posgrado_catalogo        varchar(40)  → clave estable SOLO si proviene del catálogo
|   version_catalogo_posgrados     varchar(40)  → versión SOLO si proviene del catálogo
| Se usa «titulo_ultimo_grado_especifico» (en lugar de «_catalogo») porque también guarda títulos manuales.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNAS = ['titulo_ultimo_grado_especifico', 'origen_titulo_ultimo_grado', 'clave_posgrado_catalogo', 'version_catalogo_posgrados'];

    public function up(): void
    {
        Schema::table('formaciones_academicas_solicitud', function (Blueprint $t) {
            $t->string('titulo_ultimo_grado_especifico', 300)->nullable();
            $t->string('origen_titulo_ultimo_grado', 20)->nullable();
            $t->string('clave_posgrado_catalogo', 40)->nullable();
            $t->string('version_catalogo_posgrados', 40)->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            // Coherencia (coalesce: un NULL nunca hace pasar el CHECK): catálogo ⇒ título, clave y versión; manual ⇒ título sin clave ni versión; sin origen ⇒ todo NULL.
            DB::statement(<<<'SQL'
                ALTER TABLE public.formaciones_academicas_solicitud
                  ADD CONSTRAINT fas_titulo_especifico_coherente_ck CHECK (
                       (origen_titulo_ultimo_grado IS NULL AND titulo_ultimo_grado_especifico IS NULL
                            AND clave_posgrado_catalogo IS NULL AND version_catalogo_posgrados IS NULL)
                    OR (coalesce(origen_titulo_ultimo_grado, '') = 'catalogo' AND coalesce(btrim(titulo_ultimo_grado_especifico), '') <> ''
                            AND coalesce(clave_posgrado_catalogo, '') ~ '^(ESP|MAE|DOC)-[0-9A-F]{12}$'
                            AND coalesce(btrim(version_catalogo_posgrados), '') <> '')
                    OR (coalesce(origen_titulo_ultimo_grado, '') = 'manual' AND coalesce(btrim(titulo_ultimo_grado_especifico), '') <> ''
                            AND clave_posgrado_catalogo IS NULL AND version_catalogo_posgrados IS NULL)
                  )
                SQL);
        }
    }

    public function down(): void
    {
        // Nunca elimina columnas con datos: la reversión estructural exige que estén vacías.
        $conDatos = DB::table('formaciones_academicas_solicitud')
            ->where(fn ($q) => collect(self::COLUMNAS)->each(fn ($c) => $q->orWhereNotNull($c)))->count();
        if ($conDatos > 0) {
            throw new RuntimeException("Hay {$conDatos} formaciones con títulos específicos registrados; la reversión estructural se niega. Use la desactivación funcional (bandera).");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE public.formaciones_academicas_solicitud DROP CONSTRAINT IF EXISTS fas_titulo_especifico_coherente_ck');
        }
        Schema::table('formaciones_academicas_solicitud', fn (Blueprint $t) => $t->dropColumn(self::COLUMNAS));
    }
};
