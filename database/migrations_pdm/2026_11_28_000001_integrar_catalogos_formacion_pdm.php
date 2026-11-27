<?php

use App\Services\Pdm\Compat\FormacionCatalogoPdm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasColumn('anfeca_compat.formaciones_academicas_solicitud', 'id')) {
            throw new RuntimeException('Instale las extensiones y la capa de compatibilidad PDM antes de integrar los catálogos.');
        }

        if (DB::connection()->getDatabaseName() === config('anfeca_modelo.bd_permitida')
            && env('ANFECA_PDM_AUTORIZAR_EXTENSIONES') !== 'si') {
            throw new RuntimeException('Defina ANFECA_PDM_AUTORIZAR_EXTENSIONES=si tras verificar el respaldo.');
        }

        if (DB::table('anfeca_modelo.integracion_control')->where('ic_modo', '<>', 'apagado')->exists()) {
            throw new RuntimeException('La extensión de formación requiere un esquema con PDM apagado.');
        }

        Schema::table('anfeca_modelo.formacion_solicitud', function (Blueprint $tabla): void {
            foreach (FormacionCatalogoPdm::COLUMNAS as $columna => $longitud) {
                if ($longitud === null) {
                    $tabla->boolean($columna)->nullable();
                } else {
                    $tabla->string($columna, $longitud)->nullable();
                }
            }
        });

        // Instalaciones anteriores: únicamente recuperar metadatos de la formación correspondiente
        // por ambos identificadores. El resto se comprueba con la conciliación antes de activar.
        $columnas = array_intersect(array_keys(FormacionCatalogoPdm::COLUMNAS),
            Schema::getColumnListing('public.formaciones_academicas_solicitud'));
        if ($columnas !== []) {
            $asignaciones = implode(', ', array_map(fn (string $columna) => "{$columna} = p.{$columna}", $columnas));
            DB::statement("update anfeca_modelo.formacion_solicitud f set {$asignaciones}
                from public.formaciones_academicas_solicitud p
                where p.id = f.fosc_id_formacion and p.solicitud_id = f.fosc_id_solicitud");
        }

        FormacionCatalogoPdm::actualizarVista(true);
        FormacionCatalogoPdm::actualizarDisparador();
    }

    public function down(): void
    {
        throw new RuntimeException('La reversión de datos debe hacerse con anfeca:pdm-exportar-public antes de cambiar de modo. La extensión se conserva.');
    }
};
