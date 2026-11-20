<?php

/*
|--------------------------------------------------------------------------
| ANFECA · Recorrido funcional de LECTURA con los datos reales de una base de ENSAYO
|--------------------------------------------------------------------------
| Uso (desde el worktree, con DB_DATABASE apuntando a la base de ensayo):
|   php scripts/anfeca/recorrido_lectura.php <salida.json>
|
| Para cada usuario con datos personales solicita, como ese usuario, las páginas de
| consulta de ANFECA (panel, mis datos, certificación, refrendo, formación, documentos,
| compromiso, finalización y el acuse PDF de cada solicitud enviada) y guarda el código
| HTTP y una huella del contenido (sin tokens CSRF ni marcas de tiempo del PDF).
|
| Ejecutándolo en modo apagado y luego en principal/exclusivo, ambos resultados deben ser
| IGUALES: la aplicación muestra lo mismo leyendo de public o del PDM.
|
| Garantía: todo el recorrido ocurre dentro de una transacción que se REVIERTE al final;
| nunca corre en bd_anfeca_copia_pruebas_pg; en bd_laravelanfeca_pruebas solo con ANFECA_RECORRIDO_PRINCIPAL=si.
*/

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$salida = $argv[1] ?? null;
if ($salida === null) {
    fwrite(STDERR, "Uso: php scripts/anfeca/recorrido_lectura.php <salida.json>\n");
    exit(2);
}

$bd = DB::connection()->getDatabaseName();
// La copia migrada nunca. La principal solo con autorización explícita (verificación de la activación
// real): el recorrido es de lectura y TODO ocurre en una transacción que se revierte.
if ($bd === 'bd_anfeca_copia_pruebas_pg'
    || ($bd === 'bd_laravelanfeca_pruebas' && getenv('ANFECA_RECORRIDO_PRINCIPAL') !== 'si')) {
    fwrite(STDERR, "Se niega a ejecutarse en {$bd} (en la principal requiere ANFECA_RECORRIDO_PRINCIPAL=si).\n");
    exit(2);
}

$modo = \App\Services\Pdm\ModoPdm::efectivo();
$resultado = ['base' => $bd, 'modo' => $modo, 'paginas' => []];

$normalizar = function (string $tipo, string $cuerpo): string {
    if (str_contains($tipo, 'pdf')) {
        // El PDF incluye fecha de generación e ID aleatorio: se comparan solo las páginas y el texto.
        $cuerpo = preg_replace('#/(CreationDate|ModDate) \(D:[^)]*\)#', '', $cuerpo);
        $cuerpo = preg_replace('#/ID\s*\[<[0-9a-f]+><[0-9a-f]+>\]#i', '', $cuerpo);
        $cuerpo = preg_replace('#startxref\s+\d+#', '', $cuerpo);
        return 'pdf:'.md5($cuerpo);
    }
    $cuerpo = preg_replace('/name="_token" value="[^"]*"/', '', $cuerpo);
    $cuerpo = preg_replace('/<meta name="csrf-token" content="[^"]*">/', '', $cuerpo);
    $cuerpo = preg_replace('/"csrf_token":"[^"]*"/', '', $cuerpo);
    $cuerpo = preg_replace('/data-csrf="[^"]*"/', '', $cuerpo);
    // Normalización documentada (N-1/N-2): el PDM devuelve el nombre oficial INEGI de la entidad.
    // Se aplica a ambos modos para que la comparación solo marque diferencias reales.
    $cuerpo = preg_replace_callback('/(entidad_federativa&quot;:&quot;)(.*?)(&quot;)/u', function ($m) {
        $r = (new \App\Services\Pdm\NormalizadorCatalogos())->entidadFederativa(json_decode('"'.html_entity_decode($m[2]).'"') ?? $m[2]);
        return $m[1].($r->revision ? $m[2] : htmlspecialchars(trim(json_encode($r->valor), '"'))).$m[3];
    }, $cuerpo);

    return md5($cuerpo);
};

DB::beginTransaction();
try {
    $usuarios = DB::table('public.users as u')
        ->whereExists(fn ($q) => $q->selectRaw('1')->from('public.datos_solicitantes as d')->whereColumn('d.user_id', 'u.id'))
        ->orderBy('u.id')->pluck('u.id');

    foreach ($usuarios as $id) {
        $usuario = \App\Models\User::find($id);
        $solicitudes = DB::table('public.solicitudes_certificacion')->where('user_id', $id)->where('estado', 'enviado')->pluck('id');
        $rutas = ['/panel', '/mis-datos', '/registro/certificacion', '/registro/refrendo', '/registro/formacion-academica',
            '/registro/documentos-probatorios', '/registro/compromiso', '/registro/completado'];
        foreach ($solicitudes as $s) {
            $rutas[] = "/registro/acuse/{$s}";
        }

        foreach ($rutas as $ruta) {
            Auth::guard()->setUser($usuario);
            $peticion = Request::create($ruta, 'GET');
            $peticion->setLaravelSession($app['session']->driver('array'));
            $respuesta = $kernel->handle($peticion);
            $tipo = (string) $respuesta->headers->get('content-type');
            $cuerpo = method_exists($respuesta, 'getContent') ? (string) $respuesta->getContent() : '';
            if (($dir = getenv('ANFECA_RECORRIDO_CUERPOS')) && $respuesta->isSuccessful() && !str_contains($tipo, 'pdf')) {
                @mkdir($dir, 0700, true);
                file_put_contents($dir.'/'.$id.str_replace('/', '_', $ruta).'.html', $cuerpo); // diagnóstico local; contiene datos personales
            }
            $resultado['paginas']["{$id} {$ruta}"] = [
                'http' => $respuesta->getStatusCode(),
                'destino' => $respuesta->isRedirection() ? $respuesta->headers->get('location') : null,
                'huella' => $respuesta->isSuccessful() ? $normalizar($tipo, $cuerpo) : null,
            ];
            $kernel->terminate($peticion, $respuesta);
            Auth::forgetGuards();
        }
    }
} finally {
    DB::rollBack(); // nada de lo anterior se conserva
}

$codigos = array_count_values(array_map(fn ($p) => (string) $p['http'], $resultado['paginas']));
ksort($codigos);
$resultado['resumen'] = ['usuarios' => count($usuarios ?? []), 'paginas' => count($resultado['paginas']), 'codigos' => $codigos];
file_put_contents($salida, json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "Modo {$modo} en {$bd}: {$resultado['resumen']['usuarios']} usuarios, {$resultado['resumen']['paginas']} páginas, códigos ".json_encode($codigos)."\n";
exit(isset($codigos['500']) ? 1 : 0);
