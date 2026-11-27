<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Consultas de SOLO LECTURA sobre el catálogo de PostgreSQL para el
 * esquema aislado del PDM (anfeca_modelo) y el esquema public de Laravel.
 */
class AnfecaModeloInspector
{
    public function __construct(private ?ConnectionInterface $db = null)
    {
        $this->db ??= DB::connection();
    }

    public function conexion(): ConnectionInterface
    {
        return $this->db;
    }

    public function esPostgres(): bool
    {
        return $this->db->getDriverName() === 'pgsql';
    }

    public function baseDeDatos(): string
    {
        return (string) $this->db->getDatabaseName();
    }

    public function esquemaExiste(string $esquema): bool
    {
        return (bool) $this->db->selectOne(
            'select exists(select 1 from pg_namespace where nspname = ?) as existe',
            [$esquema]
        )->existe;
    }

    /** @return list<string> */
    public function tablas(string $esquema): array
    {
        return array_map(
            fn ($fila) => $fila->tablename,
            $this->db->select(
                'select tablename from pg_tables where schemaname = ? order by tablename',
                [$esquema]
            )
        );
    }

    /** @return list<string> */
    public function columnas(string $esquema, string $tabla): array
    {
        return array_map(
            fn ($fila) => $fila->column_name,
            $this->db->select(
                'select column_name from information_schema.columns
                 where table_schema = ? and table_name = ?
                 order by ordinal_position',
                [$esquema, $tabla]
            )
        );
    }

    /** @param list<string>|null $nombres */
    public function totalLlavesForaneas(string $esquema, ?array $nombres = null): int
    {
        $consulta = $this->db->table('pg_constraint as c')
            ->join('pg_namespace as n', 'n.oid', '=', 'c.connamespace')
            ->where('c.contype', 'f')
            ->where('n.nspname', $esquema);

        if ($nombres !== null) {
            $consulta->whereIn('c.conname', $nombres);
        }

        return $consulta->count();
    }

    /**
     * Filas por tabla del esquema. Los nombres provienen de pg_tables y se
     * citan con comillas dobles, nunca de entrada del usuario.
     *
     * @return array<string,int>
     */
    public function filasPorTabla(string $esquema): array
    {
        $resultado = [];

        foreach ($this->tablas($esquema) as $tabla) {
            $identificador = '"'.str_replace('"', '""', $esquema).'"."'.str_replace('"', '""', $tabla).'"';
            $resultado[$tabla] = (int) $this->db->selectOne(
                "select count(*) as total from {$identificador}"
            )->total;
        }

        return $resultado;
    }

    /**
     * Revisión estática del script antes de ejecutarlo: devuelve la lista de
     * problemas encontrados (vacía si el script es aceptable).
     *
     * @return list<string>
     */
    public static function revisarScript(string $sql, string $esquema): array
    {
        $problemas = [];

        // Quitar comentarios para analizar solo sentencias ejecutables.
        $codigo = preg_replace('~/\*.*?\*/~s', '', $sql);
        $codigo = preg_replace('~--[^\n]*~', '', $codigo);
        $minus = strtolower($codigo);

        // Las acciones referenciales de las FK ("on delete restrict") no son sentencias.
        $sentencias = preg_replace('/\bon\s+(delete|update)\s+(restrict|cascade|set\s+null|no\s+action)\b/', '', $minus);

        foreach (['drop ', 'truncate', 'delete ', 'update ', 'insert ', 'grant ', 'revoke ', 'search_path', 'begin', 'commit', 'rollback'] as $prohibido) {
            if (str_contains($sentencias, $prohibido)) {
                $problemas[] = "Contiene una instrucción no permitida: '".trim($prohibido)."'.";
            }
        }

        if (str_contains($minus, 'public.') || preg_match('/\bpublic\b/', $minus)) {
            $problemas[] = 'Hace referencia al esquema public.';
        }

        $prefijo = preg_quote(strtolower($esquema).'.', '/');

        $patrones = [
            'create table' => '/create\s+table\s+(\S+)/',
            'create index' => '/create\s+(?:unique\s+)?index\s+\w+\s+on\s+(\S+)/',
            'comment on table' => '/comment\s+on\s+table\s+(\S+)/',
            'alter table' => '/alter\s+table\s+(\S+)/',
            'references' => '/references\s+(\S+)/',
        ];

        foreach ($patrones as $nombre => $patron) {
            preg_match_all($patron, $minus, $coincidencias);

            foreach ($coincidencias[1] as $objeto) {
                if (!preg_match('/^'.$prefijo.'/', $objeto)) {
                    $problemas[] = "'{$nombre} {$objeto}' no está calificado con el esquema {$esquema}.";
                }
            }
        }

        if (!preg_match('/create\s+schema\s+'.preg_quote(strtolower($esquema), '/').'\s*;/', $minus)) {
            $problemas[] = "No crea el esquema {$esquema} con 'create schema {$esquema};'.";
        }

        return array_values(array_unique($problemas));
    }

    /** @return array{tablas:int, llaves_foraneas:int} */
    public static function contarEnScript(string $sql): array
    {
        return [
            'tablas' => preg_match_all('/^\s*create\s+table\s/im', $sql),
            'llaves_foraneas' => preg_match_all('/\bforeign\s+key\b/i', $sql),
        ];
    }
}
