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
        $driver = $conexion->getDriverName();
        $bdReal = null;
        $direccion = null;

        // Solo se consulta al servidor si la configuración ya es pgsql; la consulta es de lectura.
        if ($driver === 'pgsql') {
            $fila = $conexion->selectOne('select current_database() as bd, host(inet_server_addr()) as ip');
            $bdReal = $fila->bd;
            $direccion = $fila->ip; // nulo si la conexión es por socket local
        }

        $motivos = GuardiaBaseAislada::motivosDeRechazo(
            $driver,
            (string) $conexion->getDatabaseName(),
            $bdReal,
            (string) config('database.connections.pgsql.host'),
            $direccion,
            config('anfeca_modelo.bd_permitida'),
            (string) config('anfeca_pdm.sufijo_bd_pruebas'),
            $this->app->configurationIsCached(),
            config('database.connections.pgsql.url'),
        );

        if ($motivos !== []) {
            throw new RuntimeException('SEGURIDAD: pruebas PDM detenidas: '.implode('; ', $motivos).'.');
        }
    }

    private function prepararBase(): void
    {
        $bd = DB::connection()->getDatabaseName();

        // Base aislada verificada arriba: se puede reconstruir completa.
        DB::statement('drop schema if exists anfeca_compat cascade');
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
