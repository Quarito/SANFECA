<?php

namespace App\Services\Pdm\Compat;

use App\Services\Pdm\ModoPdm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FormacionCatalogoPdm
{
    public const COLUMNAS = [
        'titulo_origen_especifico' => 300,
        'origen_titulo_origen' => 20,
        'clave_licenciatura_catalogo' => 40,
        'version_catalogo_licenciaturas' => 60,
        'titulo_ultimo_grado_especifico' => 300,
        'origen_titulo_ultimo_grado' => 20,
        'clave_posgrado_catalogo' => 40,
        'version_catalogo_posgrados' => 40,
        'cuenta_con_posgrado' => null,
    ];

    public static function persistenciaDisponible(array $columnas): bool
    {
        $tabla = 'formaciones_academicas_solicitud';
        if (DB::getDriverName() !== 'pgsql') {
            return Schema::hasColumns($tabla, $columnas);
        }

        return Schema::hasColumns("public.{$tabla}", $columnas)
            && (! ModoPdm::sobreVistas() || Schema::hasColumns("anfeca_compat.{$tabla}", $columnas));
    }

    public static function actualizarVista(bool $conCatalogo): void
    {
        $sql = file_get_contents(database_path('sql/anfeca_modelo/anfeca_compat_formacion_vista.sql'));
        $columnas = array_map(fn (string $columna) => "f.{$columna}", array_keys(self::COLUMNAS));
        $sql = strtr($sql, [
            '/* columnas_catalogo */' => $conCatalogo ? ",\n    ".implode(",\n    ", $columnas) : '',
            '/* titulo_otro_origen */' => self::expresionOtro('ORIGEN', 'titulo_origen_especifico', $conCatalogo),
            '/* titulo_otro_ultimo */' => self::expresionOtro('ULTIMO', 'titulo_ultimo_grado_especifico', $conCatalogo),
        ]);

        DB::unprepared($sql);
    }

    private static function expresionOtro(string $tipo, string $columna, bool $conCatalogo): string
    {
        $tradicional = "anfeca_compat.estudio(f.fosc_id_solicitud, '{$tipo}', 'otro')";
        if (! $conCatalogo) {
            return $tradicional;
        }

        return "case when f.{$columna} is not null and anfeca_compat.estudio(f.fosc_id_solicitud, '{$tipo}', 'titulo') = 'Otro'
                    then left(f.{$columna}, 200) else {$tradicional} end";
    }

    public static function actualizarDisparador(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/anfeca_modelo/anfeca_compat_formacion_escritura.sql')));
    }
}
