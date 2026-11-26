<?php

/*
|--------------------------------------------------------------------------
| PROPUESTA — Catálogo de licenciaturas y declaración de posgrado · columnas ADITIVAS
|--------------------------------------------------------------------------
| Fuera de la ruta automática (database/migrations_propuestas): «php artisan migrate» NO la aplica.
| Solo se aplica con --path y autorización expresa (procedimiento en PROPUESTA_CATALOGO_LICENCIATURAS.md).
|
| formaciones_academicas_solicitud (todas NULL; no se modifican filas existentes):
|  - titulo_origen_especifico        varchar(300)  nombre exacto de la licenciatura (catálogo o captura manual)
|  - origen_titulo_origen            varchar(20)   'catalogo' | 'manual'
|  - clave_licenciatura_catalogo     varchar(40)   clave estable LIC-xxxxxxxxxxxx (solo selecciones del catálogo)
|  - version_catalogo_licenciaturas  varchar(60)   versión del catálogo (solo selecciones del catálogo)
|  - cuenta_con_posgrado             boolean       TRUE = licenciatura con posgrado · FALSE = solo licenciatura ·
|                                                  NULL = registro histórico o declaración todavía no realizada
| Se conservan sin cambios nivel_origen, titulo_origen (área), titulo_origen_otro y las 4 columnas de posgrado.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNAS = ['titulo_origen_especifico', 'origen_titulo_origen', 'clave_licenciatura_catalogo',
        'version_catalogo_licenciaturas', 'cuenta_con_posgrado'];

    public function up(): void
    {
        Schema::table('formaciones_academicas_solicitud', function (Blueprint $t) {
            $t->string('titulo_origen_especifico', 300)->nullable();
            $t->string('origen_titulo_origen', 20)->nullable();
            $t->string('clave_licenciatura_catalogo', 40)->nullable();
            $t->string('version_catalogo_licenciaturas', 60)->nullable();
            $t->boolean('cuenta_con_posgrado')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            // Coherencia del título de origen (coalesce: un NULL nunca hace pasar el CHECK).
            DB::statement(<<<'SQL'
                ALTER TABLE public.formaciones_academicas_solicitud
                  ADD CONSTRAINT fas_titulo_origen_coherente_ck CHECK (
                       (origen_titulo_origen IS NULL AND titulo_origen_especifico IS NULL
                            AND clave_licenciatura_catalogo IS NULL AND version_catalogo_licenciaturas IS NULL)
                    OR (coalesce(origen_titulo_origen, '') = 'catalogo' AND coalesce(btrim(titulo_origen_especifico), '') <> ''
                            AND coalesce(clave_licenciatura_catalogo, '') ~ '^LIC-[0-9A-F]{12}$'
                            AND coalesce(btrim(version_catalogo_licenciaturas), '') <> '')
                    OR (coalesce(origen_titulo_origen, '') = 'manual' AND coalesce(btrim(titulo_origen_especifico), '') <> ''
                            AND clave_licenciatura_catalogo IS NULL AND version_catalogo_licenciaturas IS NULL)
                  )
                SQL);
            // Con posgrado declarado, el último grado SOLO puede ser Especialidad, Maestría o Doctorado y tener área.
            // Con FALSE no se exige nada: los datos de posgrado guardados antes se CONSERVAN (no vigentes).
            DB::statement(<<<'SQL'
                ALTER TABLE public.formaciones_academicas_solicitud
                  ADD CONSTRAINT fas_declaracion_posgrado_ck CHECK (
                       cuenta_con_posgrado IS DISTINCT FROM true
                    OR (coalesce(nivel_ultimo_grado, '') IN ('Especialidad', 'Maestría', 'Doctorado')
                            AND coalesce(btrim(titulo_ultimo_grado), '') <> '')
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
            throw new RuntimeException("Hay {$conDatos} formaciones con título de origen específico o declaración de posgrado; la reversión estructural se niega. Use la desactivación funcional (banderas).");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE public.formaciones_academicas_solicitud DROP CONSTRAINT IF EXISTS fas_titulo_origen_coherente_ck');
            DB::statement('ALTER TABLE public.formaciones_academicas_solicitud DROP CONSTRAINT IF EXISTS fas_declaracion_posgrado_ck');
        }
        Schema::table('formaciones_academicas_solicitud', fn (Blueprint $t) => $t->dropColumn(self::COLUMNAS));
    }
};
