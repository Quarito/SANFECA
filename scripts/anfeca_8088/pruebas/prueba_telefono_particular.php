<?php
/**
 * ANFECA · Regresión de los medios de contacto (TIPO_COMUNICACION / COMUNICACION) con datos FICTICIOS.
 * Comprueba la compatibilidad de TEL_PARTICULAR con «Teléfono de casa» (vigente en 8088) y con «Teléfono particular»
 * (bases anteriores): captura, recuperación, modificación y borrado, sin duplicar tipos ni perder datos, y que
 * COMU_VISIBLE = true solo se aplique a correo y teléfonos.
 *
 * Uso: php prueba_telefono_particular.php <directorio de la copia de prueba de ANFECA> <escenario>
 *   escenario: casa | particular | ambos | vacio
 *   (la base de la copia debe ser de PRUEBA, con el modelo oficial, una entidad y el rol solicitante)
 */
[$_, $dir, $escenario] = $argv + [null, null, null];
require $dir.'/vendor/autoload.php';
$app = require $dir.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Anfeca\Catalogo;
use App\Services\Anfeca\ExpedienteSolicitante;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$base = DB::selectOne('select current_database() as b')->b;
if ($base === 'certificacion_anfeca' || str_starts_with($base, 'bd_')) {
    fwrite(STDERR, "DETENIDO: use una base de PRUEBA, no {$base}.\n");
    exit(1);
}
$ok = $total = 0;
$prueba = function (string $nombre, bool $resultado, string $detalle = '') use (&$ok, &$total) {
    $total++;
    $ok += $resultado ? 1 : 0;
    echo ($resultado ? 'APROBADA ' : 'FALLIDA  ')."[{$GLOBALS['escenario']}] {$nombre}".($detalle !== '' ? " — {$detalle}" : '')."\n";
};
$tipos = fn () => DB::table('tipo_comunicacion')->orderBy('tico_id_tipo_comunicacion')->pluck('tico_tipo_comunicacion', 'tico_id_tipo_comunicacion')->all();

// Preparación del escenario (solo en la base de prueba).
$legado = null;
if (in_array($escenario, ['casa', 'ambos'], true)) {
    DB::table('tipo_comunicacion')->insert(['tico_tipo_comunicacion' => 'Teléfono de casa', 'tico_estado' => 'ACT']);
}
if (in_array($escenario, ['particular', 'ambos'], true)) {
    DB::table('tipo_comunicacion')->insert(['tico_tipo_comunicacion' => 'Teléfono particular', 'tico_estado' => 'ACT']);
}
Artisan::call('anfeca:catalogos', ['--convocatoria-anio' => date('Y')]);
Catalogo::olvidar();
$t0 = $tipos();
$telefonos = array_filter($t0, fn ($d) => in_array($d, ['Teléfono de casa', 'Teléfono particular'], true));
$esperados = ['casa' => ['Teléfono de casa'], 'particular' => ['Teléfono particular'], 'ambos' => ['Teléfono de casa', 'Teléfono particular'], 'vacio' => ['Teléfono de casa']][$escenario];
$prueba('anfeca:catalogos no crea un segundo tipo de teléfono particular', array_values($telefonos) === $esperados, implode(' + ', $telefonos));
$prueba('Sin tipos geográficos en TIPO_COMUNICACION', !array_filter($t0, fn ($d) => preg_match('/postal|colonia|municipio/i', $d)), implode(', ', $t0));
Artisan::call('anfeca:catalogos', ['--convocatoria-anio' => date('Y')]);
$prueba('anfeca:catalogos repetido no agrega tipos', $tipos() === $t0);

if ($escenario === 'ambos') {
    // Persona previa cuyo teléfono se guardó con el tipo anterior «Teléfono particular».
    $pid = DB::table('persona')->insertGetId(['pers_nombre' => 'Legado Ficticio', 'pers_apellido_paterno' => 'Prueba', 'pers_curp' => 'LEGA800101HDFCRS09',
        'pers_rfc' => 'LEGA800101AB9', 'pers_activo' => true], 'pers_id_persona');
    DB::table('usuario')->insert(['usua_id_persona' => $pid, 'usua_id_rol' => Catalogo::rol('solicitante'), 'usua_nombre' => 'legado.ficticio@example.test',
        'usua_contrasenia' => 'hash-ficticio', 'usua_estatus' => 'ACTIVO']);
    DB::table('domicilio')->insert(['domi_id_persona' => $pid, 'domi_id_entidad_federativa' => DB::table('entidad_federativa')->min('enfe_id_entidad_federativa'),
        'domi_calle' => 'Calle Legado', 'domi_numero_ext' => '1']);
    DB::table('comunicacion')->insert(['comu_id_persona' => $pid, 'comu_id_tipo_comunicacion' => array_search('Teléfono particular', $t0, true),
        'comu_valor' => '5500000001', 'comu_visible' => true]);
    $legado = $pid;
}

$servicio = app(ExpedienteSolicitante::class);
$sufijo = substr((string) hrtime(true), -6);
$entidad = (int) DB::table('entidad_federativa')->min('enfe_id_entidad_federativa');
$datos = ['curp' => "TELF800101HDFCRS{$sufijo[0]}{$sufijo[1]}", 'rfc' => "TELF800101{$sufijo[2]}{$sufijo[3]}1", 'nombres' => 'Teléfono Ficticio',
    'apellido_paterno' => 'Prueba', 'apellido_materno' => null, 'genero' => 'Femenino', 'correo_electronico' => "tel.{$sufijo}@example.test",
    'telefono_particular' => '5511112222', 'telefono_celular' => '3311112222', 'calle' => 'Calle Uno', 'numero_exterior' => '10',
    'numero_interior' => null, 'entidad_federativa_id' => $entidad];
$cuenta = $servicio->registrar($datos, 'ClaveFicticia#2026');
$persona = (int) $cuenta->usua_id_persona;
$filas = fn ($p) => DB::table('comunicacion as c')->join('tipo_comunicacion as t', 't.tico_id_tipo_comunicacion', '=', 'c.comu_id_tipo_comunicacion')
    ->where('c.comu_id_persona', $p)->orderBy('c.comu_id_comunicacion')->get(['t.tico_tipo_comunicacion as tipo', 'c.comu_valor as valor', 'c.comu_visible as visible'])->all();
$r = $filas($persona);
$tipoTel = collect($r)->firstWhere('valor', '5511112222')?->tipo;
$vigenteExistente = in_array('Teléfono de casa', $t0, true) ? 'Teléfono de casa' : 'Teléfono particular';
$prueba('Captura: el teléfono particular se guarda con el tipo existente', $tipoTel === $vigenteExistente, (string) $tipoTel);
$prueba('Captura: correo, teléfono particular y celular (3 filas, visibles)', count($r) === 3 && collect($r)->every(fn ($x) => $x->visible === true),
    collect($r)->map(fn ($x) => "{$x->tipo}={$x->valor}")->implode(', '));
Catalogo::olvidar();
$d = $servicio->datos($cuenta);
$prueba('Recuperación (Mis datos): teléfonos y correo', $d->telefono_particular === '5511112222' && $d->telefono_celular === '3311112222'
    && $d->correo_electronico === $datos['correo_electronico']);
$servicio->actualizarDomicilioYTelefonos($cuenta, ['telefono_particular' => '5599998888', 'telefono_celular' => '3311112222'] + $datos);
$r2 = $filas($persona);
$prueba('Modificación: actualiza el valor sin crear filas ni cambiar el tipo', count($r2) === 3 && collect($r2)->firstWhere('valor', '5599998888')?->tipo === $tipoTel);
Catalogo::olvidar();
$prueba('Recuperación tras modificar', $servicio->datos($cuenta)->telefono_particular === '5599998888');
$servicio->actualizarDomicilioYTelefonos($cuenta, ['telefono_particular' => '', 'telefono_celular' => '3311112222'] + $datos);
$prueba('Borrado: un teléfono vacío elimina solo ese medio', count($filas($persona)) === 2 && $servicio->datos($cuenta)->telefono_particular === null);
$servicio->actualizarDomicilioYTelefonos($cuenta, ['telefono_particular' => '5544443333', 'telefono_celular' => '3311112222'] + $datos);
$prueba('Nueva captura tras borrar usa el tipo vigente existente', collect($filas($persona))->firstWhere('valor', '5544443333')?->tipo === $vigenteExistente);

if ($legado) {
    $cl = App\Models\Cuenta::where('usua_id_persona', $legado)->firstOrFail();
    Catalogo::olvidar();
    $prueba('Dato previo con «Teléfono particular» se recupera', $servicio->datos($cl)->telefono_particular === '5500000001');
    $servicio->actualizarDomicilioYTelefonos($cl, ['telefono_particular' => '5500000002', 'telefono_celular' => null, 'calle' => 'Calle Legado',
        'numero_exterior' => '1', 'numero_interior' => null, 'entidad_federativa_id' => $entidad]);
    $rl = $filas($legado);
    $prueba('Dato previo modificado en su propia fila (sin duplicar ni cambiar de tipo)', count($rl) === 1 && $rl[0]->tipo === 'Teléfono particular' && $rl[0]->valor === '5500000002');
}

try {
    (new ReflectionMethod($servicio, 'guardarComunicacion'))->invoke($servicio, $persona, 'CODIGO_POSTAL', '04510');
    $rechazo = false;
} catch (Throwable $e) {
    $rechazo = $e instanceof InvalidArgumentException;
}
$prueba('COMUNICACION rechaza datos geográficos (solo medios de contacto)', $rechazo);
$noContacto = DB::table('comunicacion as c')->join('tipo_comunicacion as t', 't.tico_id_tipo_comunicacion', '=', 'c.comu_id_tipo_comunicacion')
    ->where('c.comu_visible', true)->whereNotIn('t.tico_tipo_comunicacion', ['Correo electrónico', 'Teléfono de casa', 'Teléfono particular', 'Teléfono celular'])->count();
$prueba('COMU_VISIBLE = true solo en correo y teléfonos', $noContacto === 0, "otros visibles: {$noContacto}");
$prueba('TIPO_COMUNICACION sin cambios tras todas las operaciones', $tipos() === $t0);

echo "Resultado: {$ok}/{$total} verificaciones aprobadas.\n";
exit($ok === $total ? 0 : 1);
