<?php

// Genera app/Models/Pdm/<Clase>.php para las 36 tablas del SQL oficial.
// Uso: php scripts/anfeca_8088/generar_modelos.php database/sql/oficial/<archivo>.sql
require __DIR__.'/ModeloOficial.php';

$modelo = ModeloOficial::leer($argv[1]);
$destino = dirname(__DIR__, 2).'/app/Models/Pdm';

$plural = fn (string $s) => preg_match('/[aeiou]$/', $s) ? $s.'s' : $s.'es';
$casts = ['INT4' => 'integer', 'SERIAL' => 'integer', 'BOOL' => 'boolean'];

foreach ($modelo['tablas'] as $tabla => $def) {
    $clase = ModeloOficial::clase($tabla);
    $pk = $def['pk'];
    $relaciones = [];
    $usos = [];
    foreach ($modelo['fks'] as $fk) {
        if ($fk['tabla'] === $tabla) {
            $ref = ModeloOficial::clase($fk['referencia']);
            $metodo = lcfirst($ref);
            $usos['BelongsTo'] = true;
            $relaciones[] = "    /** {$fk['nombre']}: {$tabla}.{$fk['columna']} → {$fk['referencia']}.{$fk['columna_referencia']} */\n"
                ."    public function {$metodo}(): BelongsTo\n    {\n        return \$this->belongsTo({$ref}::class, '{$fk['columna']}', '{$fk['columna_referencia']}');\n    }";
        }
        if ($fk['referencia'] === $tabla) {
            $hija = ModeloOficial::clase($fk['tabla']);
            $metodo = $plural(lcfirst($hija));
            $usos['HasMany'] = true;
            $relaciones[] = "    /** {$fk['nombre']}: {$fk['tabla']}.{$fk['columna']} → {$tabla}.{$fk['columna_referencia']} */\n"
                ."    public function {$metodo}(): HasMany\n    {\n        return \$this->hasMany({$hija}::class, '{$fk['columna']}', '{$fk['columna_referencia']}');\n    }";
        }
    }
    $fillable = implode("\n", array_map(fn ($c) => "        '{$c}',", array_keys($def['columnas'])));
    $castLineas = [];
    foreach ($def['columnas'] as $c => $d) {
        if (isset($casts[$d['tipo']])) {
            $castLineas[] = "        '{$c}' => '{$casts[$d['tipo']]}',";
        }
    }
    $compuesta = count($pk) > 1
        ? "\n    /** PK compuesta del modelo oficial. */\n    protected array \$columnasClaveCompuesta = ['".implode("', '", array_slice($pk, 1))."'];\n"
        : '';
    $use = '';
    foreach (array_keys($usos) as $u) {
        $use .= "use Illuminate\\Database\\Eloquent\\Relations\\{$u};\n";
    }
    $codigo = "<?php\n\nnamespace App\\Models\\Pdm;\n\n{$use}\n/**\n * Tabla public.{$tabla} del modelo oficial PowerDesigner (36 tablas).\n"
        ." * Generado por scripts/anfeca_8088/generar_modelos.php: no agregar columnas que no existan en el SQL aprobado.\n */\n"
        ."class {$clase} extends PdmModel\n{\n    protected \$table = '{$tabla}';\n\n    protected \$primaryKey = '{$pk[0]}';\n{$compuesta}\n"
        ."    protected \$fillable = [\n{$fillable}\n    ];\n\n    protected \$casts = [\n".implode("\n", $castLineas)."\n    ];\n"
        .($relaciones ? "\n".implode("\n\n", $relaciones)."\n" : '')."}\n";
    file_put_contents("{$destino}/{$clase}.php", $codigo);
    echo "{$clase}\n";
}
