<?php

/**
 * Lector del SQL oficial PowerDesigner (36 tablas). Fuente única para generar los modelos
 * Eloquent y para comparar el esquema físico instalado. No modifica el archivo.
 */
final class ModeloOficial
{
    /** @return array{tablas: array<string, array>, fks: list<array>, indices: list<array>} */
    public static function leer(string $ruta): array
    {
        $sql = file_get_contents($ruta);
        $tablas = [];
        preg_match_all('/create table (\w+) \((.*?)\n\);/s', $sql, $m, PREG_SET_ORDER);
        foreach ($m as [$_, $tabla, $cuerpo]) {
            $columnas = [];
            $pk = [];
            foreach (explode("\n", $cuerpo) as $linea) {
                $linea = trim(rtrim(trim($linea), ','));
                if ($linea === '') {
                    continue;
                }
                if (preg_match('/^constraint (\w+) primary key \((.*)\)$/', $linea, $c)) {
                    $pk = array_map(fn ($x) => strtolower(trim($x)), explode(',', $c[2]));
                    continue;
                }
                if (!preg_match('/^(\w+)\s+([A-Z0-9]+)(?:\((\d+)\))?\s+(not null|null)$/', $linea, $c)) {
                    throw new RuntimeException("Línea no reconocida en {$tabla}: {$linea}");
                }
                $columnas[strtolower($c[1])] = ['tipo' => $c[2], 'longitud' => $c[3] !== '' ? (int) $c[3] : null, 'nulo' => $c[4] === 'null'];
            }
            $tablas[strtolower($tabla)] = ['columnas' => $columnas, 'pk' => $pk];
        }
        preg_match_all('/alter table (\w+)\s+add constraint (\w+) foreign key \((\w+)\)\s+references (\w+) \((\w+)\)/s', $sql, $f, PREG_SET_ORDER);
        $fks = array_map(fn ($x) => ['tabla' => strtolower($x[1]), 'nombre' => strtolower($x[2]), 'columna' => strtolower($x[3]),
            'referencia' => strtolower($x[4]), 'columna_referencia' => strtolower($x[5])], $f);
        preg_match_all('/create\s+(unique\s+)?index (\w+) on (\w+) \((.*?)\);/s', $sql, $i, PREG_SET_ORDER);
        $indices = array_map(fn ($x) => ['nombre' => strtolower($x[2]), 'tabla' => strtolower($x[3]), 'unico' => trim($x[1]) !== '',
            'columnas' => array_map(fn ($c) => strtolower(trim($c)), explode(',', trim($x[4])))], $i);

        return compact('tablas', 'fks', 'indices');
    }

    public static function clase(string $tabla): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $tabla)));
    }
}
