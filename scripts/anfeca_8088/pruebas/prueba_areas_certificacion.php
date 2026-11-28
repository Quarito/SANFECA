<?php
/**
 * ANFECA · Regresión de AREA_CERTIFICACION (datos ficticios, base de PRUEBA): el código se adapta a las descripciones
 * existentes en la base sin duplicar áreas. Escenarios: «8088» (descripciones esperadas en 8088, área 5 «Otras
 * Disciplinas Vinculadas al Ámbito Económico Administrativo»), «laboratorio» (las de config) y «vacio» (sin áreas).
 * Uso: php prueba_areas_certificacion.php <copia de ANFECA> <escenario>
 */
[$_, $dir, $escenario] = $argv + [null, null, null];
require $dir.'/vendor/autoload.php';
$app = require $dir.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Anfeca\Catalogo;
use App\Services\Formacion\RequisitosFormacion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$base = DB::selectOne('select current_database() as b')->b;
if ($base === 'certificacion_anfeca' || str_starts_with($base, 'bd_') || str_ends_with($base, '_8090')) {
    fwrite(STDERR, "DETENIDO: use una base de PRUEBA desechable, no {$base}.\n");
    exit(1);
}
$ok = $total = 0;
$prueba = function (string $n, bool $r, string $d = '') use (&$ok, &$total, $escenario) {
    $total++; $ok += $r ? 1 : 0;
    echo ($r ? 'APROBADA ' : 'FALLIDA  ')."[{$escenario}] {$n}".($d !== '' ? " — {$d}" : '')."\n";
};
$esperadas8088 = ['Contaduría Pública', 'Administración', 'Informática Administrativa', 'Negocios Internacionales',
    'Otras Disciplinas Vinculadas al Ámbito Económico Administrativo'];
if ($escenario === '8088') {
    foreach ($esperadas8088 as $d) {
        DB::table('area_certificacion')->insert(['area_descripcion' => $d]);
    }
} elseif ($escenario === 'laboratorio') {
    foreach ((array) config('anfeca_8088.areas_certificacion') as $d) {
        DB::table('area_certificacion')->insert(['area_descripcion' => $d]);
    }
}
$antes = DB::table('area_certificacion')->orderBy('area_id_area_certificacion')->pluck('area_descripcion', 'area_id_area_certificacion')->all();
Artisan::call('anfeca:catalogos', ['--convocatoria-anio' => date('Y')]);
Catalogo::olvidar();
$despues = DB::table('area_certificacion')->orderBy('area_id_area_certificacion')->pluck('area_descripcion', 'area_id_area_certificacion')->all();
$prueba('anfeca:catalogos no duplica ni modifica áreas existentes', $escenario === 'vacio' ? count($despues) === 5 : $despues === $antes,
    count($antes).' → '.count($despues));
$prueba('Cinco áreas, sin duplicados normalizados', count($despues) === 5 && count(array_unique(array_map([Catalogo::class, 'clave'], $despues))) === 5);
$ids = [];
foreach ((array) config('anfeca_8088.areas_certificacion') as $interna) {
    $ids[$interna] = Catalogo::area($interna);
}
$prueba('Cada nombre interno se resuelve al ID real existente', !in_array(null, $ids, true) && count(array_unique($ids)) === 5,
    implode(', ', array_map(fn ($k, $v) => "{$v}", array_keys($ids), $ids)));
$prueba('Las descripciones de la base se traducen al nombre interno', collect($despues)->every(fn ($d) => in_array(Catalogo::areaInterna($d), (array) config('anfeca_8088.areas_certificacion'), true)));
$area5 = end($despues);
$prueba('Regla de posgrado obligatorio aplica al área 5 con la descripción real', RequisitosFormacion::exigePosgrado(Catalogo::areaInterna($area5)), $area5);
$prueba('Equivalencias de formación disponibles para el área 5', config('anfeca_equivalencias.areas.'.Catalogo::areaInterna($area5)) !== null);
$prueba('Histórico con otra grafía se reconoce como la misma área (refrendo)', Catalogo::mismaArea('OTRAS DISCIPLINAS VINCULADAS AL AMBITO ECONOMICO ADMINISTRATIVO', 'Disciplinas vinculadas al ámbito económico-administrativo')
    && Catalogo::mismaArea('CONTADURIA PUBLICA', 'Contaduría Pública') && !Catalogo::mismaArea('Administración', 'Contaduría Pública'));
$prueba('La descripción almacenada no se modifica', DB::table('area_certificacion')->orderBy('area_id_area_certificacion')->pluck('area_descripcion', 'area_id_area_certificacion')->all() === $despues);
echo "Resultado: {$ok}/{$total} verificaciones aprobadas.\n";
exit($ok === $total ? 0 : 1);
