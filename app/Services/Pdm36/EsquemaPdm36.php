<?php

namespace App\Services\Pdm36;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Modelo físico APROBADO de 36 tablas (PowerDesigner) como única estructura de la base.
 *
 * Fuente: database/sql/anfeca_modelo/anfeca_certifica_1.original.sql (PDM literal). Se aplican solo:
 *  - la retirada de los DROP iniciales del script literal;
 *  - la omisión del índice TIENE_ARTICULO_FK, que apunta a EVALUACION.SOLI_ID_ARTICULO (columna inexistente);
 *  - las DOS correcciones autorizadas: CUMU_ID_PERSONA → COMU_ID_PERSONA (comunicacion) y
 *    DOCU_ID_DOCUMENTO → SOCU_ID_DOCUMENTO (solicitud_documento).
 * Los renombres con_lugar_entrega, con_evento_entrega y solo_art13 NO se aplican (pendientes de autorización).
 * Sin extensiones, sin anfeca_compat y sin tablas en public.
 */
final class EsquemaPdm36
{
    public const ESQUEMA = 'anfeca_modelo';

    public const ESPERADO = ['tablas' => 36, 'columnas' => 173, 'pk' => 36, 'fk' => 35, 'indices' => 96, 'secuencias' => 36];

    public static function sql(): string
    {
        $s = self::textoOriginal();
        $s = preg_replace('/^\s*drop\s+(index|table)\s+[^;]+;\s*$/im', '', $s);
        $s = preg_replace('/create\s+index\s+TIENE_ARTICULO_FK\s+on\s+EVALUACION\s*\([^)]*\);/is',
            '-- TIENE_ARTICULO_FK omitido: EVALUACION no tiene SOLI_ID_ARTICULO', $s, 1, $omitidos);
        if ($omitidos !== 1) {
            throw new RuntimeException('El SQL original no contiene el índice TIENE_ARTICULO_FK esperado: no se instala.');
        }

        return 'create schema '.self::ESQUEMA.";\nset local search_path to ".self::ESQUEMA.";\n".$s
            ."\nalter table comunicacion rename column cumu_id_persona to comu_id_persona;"
            ."\nalter table solicitud_documento rename column docu_id_documento to socu_id_documento;\n";
    }

    /**
     * El archivo tiene codificación MIXTA: el encabezado agregado en el repositorio es UTF-8 y el cuerpo exportado por
     * PowerDesigner es Windows-1252 (p. ej., «definirá» en el comentario de PERSONA). Se decodifica línea por línea
     * (UTF-8 si es válida; si no, Windows-1252): no hay ambigüedad ni pérdida de caracteres.
     */
    public static function textoOriginal(): string
    {
        $lineas = explode("\n", file_get_contents(database_path('sql/anfeca_modelo/anfeca_certifica_1.original.sql')));

        return implode("\n", array_map(fn (string $l) => mb_check_encoding($l, 'UTF-8') ? $l : mb_convert_encoding($l, 'UTF-8', 'Windows-1252'), $lineas));
    }

    /** Instala en una base SIN tablas de usuario. Se niega si existe cualquier tabla (no sobrescribe nada). */
    public static function instalar(): void
    {
        $tablas = (int) DB::selectOne("select count(*) as n from pg_tables where schemaname not in ('pg_catalog', 'information_schema')")->n;
        if ($tablas > 0) {
            throw new RuntimeException("La base ya contiene {$tablas} tablas: el modelo de 36 tablas solo se instala en una base vacía.");
        }
        DB::transaction(fn () => DB::unprepared(self::sql()));
        $real = self::inventario();
        if ($real !== self::ESPERADO) {
            throw new RuntimeException('Inventario distinto del aprobado: '.json_encode($real));
        }
    }

    /** @return array<string, int> */
    public static function inventario(): array
    {
        $e = self::ESQUEMA;
        $r = DB::selectOne("select
            (select count(*) from pg_tables where schemaname = '{$e}') as tablas,
            (select count(*) from information_schema.columns where table_schema = '{$e}') as columnas,
            (select count(*) from pg_constraint where connamespace = '{$e}'::regnamespace and contype = 'p') as pk,
            (select count(*) from pg_constraint where connamespace = '{$e}'::regnamespace and contype = 'f') as fk,
            (select count(*) from pg_indexes where schemaname = '{$e}') as indices,
            (select count(*) from pg_class where relnamespace = '{$e}'::regnamespace and relkind = 'S') as secuencias");

        return array_map('intval', (array) $r);
    }

    /** Tablas de usuario fuera del modelo (deben ser 0). */
    public static function tablasAjenas(): int
    {
        return (int) DB::selectOne("select count(*) as n from pg_tables
            where schemaname not in ('pg_catalog', 'information_schema', '".self::ESQUEMA."')")->n;
    }
}
