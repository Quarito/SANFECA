<?php

namespace Tests\Pdm;

use Database\Seeders\Pdm\CatalogosPdmSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Base de las pruebas contra PostgreSQL AISLADO.
 *
 * Protecciones (si alguna falla, ninguna prueba se ejecuta):
 *  - Conexión pgsql, host local (127.0.0.1 / localhost / socket).
 *  - Nombre de base terminado en config('anfeca_pdm.sufijo_bd_pruebas') ("_pruebas_pg").
 *  - Nunca la base configurada como real (anfeca_modelo.bd_permitida).
 *
 * Preparación (una vez por proceso), SOLO en esa base aislada:
 *  1. Elimina el esquema anfeca_modelo y recrea public con todas las migraciones vigentes.
 *  2. Instala el PDM original con anfeca:modelo-instalar (el mismo comando del servidor).
 *  3. Aplica las extensiones (database/migrations_pdm) y siembra los catálogos.
 * Cada prueba corre dentro de una transacción que se revierte.
 */
abstract class PdmTestCase extends TestCase
{
    use DatabaseTransactions;

    private static bool $preparada = false;

    protected function setUpTraits()
    {
        $this->verificarBaseAislada();

        if (!self::$preparada) {
            $this->prepararBase();
            self::$preparada = true;
        }

        return parent::setUpTraits();
    }

    private function verificarBaseAislada(): void
    {
        $conexion = DB::connection();
        $bd = (string) $conexion->getDatabaseName();
        $host = (string) config('database.connections.pgsql.host');
        $sufijo = (string) config('anfeca_pdm.sufijo_bd_pruebas');

        $fallas = [];
        if ($conexion->getDriverName() !== 'pgsql') {
            $fallas[] = 'la conexión no es pgsql';
        }
        if (!str_ends_with($bd, $sufijo)) {
            $fallas[] = "la base '{$bd}' no termina en '{$sufijo}'";
        }
        if ($bd === config('anfeca_modelo.bd_permitida')) {
            $fallas[] = "la base '{$bd}' es la base de pruebas en uso";
        }
        if (!in_array($host, ['127.0.0.1', 'localhost', '::1', ''], true) && !str_starts_with($host, '/')) {
            $fallas[] = "el host '{$host}' no es local";
        }

        if ($fallas !== []) {
            throw new RuntimeException('SEGURIDAD: pruebas PDM detenidas: '.implode('; ', $fallas).'.');
        }
    }

    private function prepararBase(): void
    {
        $bd = DB::connection()->getDatabaseName();

        // Base aislada verificada arriba: se puede reconstruir completa.
        DB::statement('drop schema if exists anfeca_modelo cascade');
        $this->ejecutar('migrate:fresh', ['--force' => true]);

        // El instalador solo actúa en la base "permitida": se habilita la aislada solo para este paso.
        $permitidaOriginal = config('anfeca_modelo.bd_permitida');
        config(['anfeca_modelo.bd_permitida' => $bd]);
        $this->ejecutar('anfeca:modelo-instalar', ['--ejecutar' => true, '--confirmar' => true]);
        config(['anfeca_modelo.bd_permitida' => $permitidaOriginal]);

        $this->ejecutar('migrate', ['--path' => 'database/migrations_pdm', '--force' => true]);
        $this->ejecutar('db:seed', ['--class' => CatalogosPdmSeeder::class, '--force' => true]);
    }

    private function ejecutar(string $comando, array $parametros): void
    {
        if (Artisan::call($comando, $parametros) !== 0) {
            throw new RuntimeException("Preparación fallida en '{$comando}': ".Artisan::output());
        }
    }

    /** ID de catálogo por su clave estable. */
    protected function idCatalogo(string $tabla, string $columna, string $valor): int
    {
        $pk = DB::selectOne(
            "select a.attname as pk from pg_index i
             join pg_attribute a on a.attrelid = i.indrelid and a.attnum = any(i.indkey)
             where i.indrelid = ?::regclass and i.indisprimary limit 1",
            ["anfeca_modelo.{$tabla}"]
        )->pk;

        return (int) DB::table("anfeca_modelo.{$tabla}")->where($columna, $valor)->value($pk);
    }
}
