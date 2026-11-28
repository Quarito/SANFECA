<?php
/**
 * ANFECA · Búsqueda directa de programas ANUIES en PostgreSQL (ProgramasAnuiesPostgres), base de PRUEBA con títulos
 * FICTICIOS en estado ACT. Comprueba que el catálogo no se modifica. Uso: php prueba_programas_anuies.php <copia ANFECA>
 */
[$_, $dir] = $argv + [null, null];
require $dir.'/vendor/autoload.php';
$app = require $dir.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Catalogos\ProgramasAnuiesPostgres;
use Illuminate\Support\Facades\DB;

$base = DB::selectOne('select current_database() as b')->b;
if ($base === 'certificacion_anfeca' || str_ends_with($base, '_8090')) { fwrite(STDERR, "DETENIDO: base de prueba.\n"); exit(1); }
$ok = $total = 0;
$prueba = function (string $n, bool $r, string $d = '') use (&$ok, &$total) { $total++; $ok += $r ? 1 : 0; echo ($r ? 'APROBADA ' : 'FALLIDA  ')."{$n}".($d !== '' ? " — {$d}" : '')."\n"; };
$huella = fn () => DB::selectOne("select md5(string_agg(t::text, ',' order by t::text)) as h from estudio_profesional t")->h;
$h0 = $huella();
$s = new ProgramasAnuiesPostgres();
$r = $s->buscar(['Licenciatura'], 'contaduria');
$prueba('Búsqueda sin acentos ni mayúsculas encuentra «Contaduría»', $r['total'] === 1 && $r['programas'][0]['nombre'] === 'Licenciatura Ficticia en Contaduría', json_encode($r, JSON_UNESCAPED_UNICODE));
$esperadas = (int) DB::selectOne("select count(*) as n from estudio_profesional e join nivel_profesional n on n.nipr_id_nivel_profesional = e.espr_id_nivel_profesional
    where e.espr_estado = 'ACT' and n.nipr_nivel_profesional = 'Licenciatura' and lower(e.espr_nombre) like '%licenciatura%' and lower(e.espr_nombre) like '%ficticia%'")->n;
$r = $s->buscar(['Licenciatura'], 'licenciatura ficticia');
$prueba('Varias palabras: todas las licenciaturas ACT ficticias (excluye otros niveles y estados)', $esperadas > 0 && $r['total'] === $esperadas, "total {$r['total']} / esperado {$esperadas}");
$r = $s->buscar(['Especialidad', 'Maestría', 'Doctorado'], 'ficticia');
$prueba('Posgrados: especialidad y maestría ficticias (por nivel, sin filtro de área)', $r['total'] === 2, implode(', ', array_column($r['programas'], 'nombre')));
$prueba('Límite de resultados con total real', ($r2 = $s->buscar(['Licenciatura'], 'licenciatura ficticia', 2)) && count($r2['programas']) === 2 && $r2['total'] === $esperadas);
$prueba('Un comodín SQL escrito por el usuario no devuelve todo el catálogo', $s->buscar(['Licenciatura'], '%%')['total'] === 0);
$id = DB::table('estudio_profesional')->where('espr_nombre', 'Maestría Ficticia en Negocios')->value('espr_id_estudio_profesional');
$prueba('Validación del servidor: ID ACT del nivel permitido', $s->validar((int) $id, ['Maestría']) !== null && $s->validar((int) $id, ['Licenciatura']) === null);
$cal = DB::table('estudio_profesional')->where('espr_estado', '<>', 'ACT')->value('espr_id_estudio_profesional');
$prueba('Validación rechaza registros que no son del catálogo ANUIES (estado distinto de ACT)', $cal === null || $s->validar((int) $cal, ['Licenciatura']) === null);
$prueba('El catálogo ESTUDIO_PROFESIONAL no se modificó', $huella() === $h0);
echo "Resultado: {$ok}/{$total} verificaciones aprobadas.\n";
exit($ok === $total ? 0 : 1);
