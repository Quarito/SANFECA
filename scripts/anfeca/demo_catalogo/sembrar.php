<?php
// Demostración DESECHABLE del catálogo de posgrados. Datos 100 % ficticios; solo /app/storage/demo.sqlite.
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Catalogos\CatalogoPosgrados;
use Illuminate\Support\Facades\{DB, Hash};

if (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== '/app/storage/demo.sqlite') {
    fwrite(STDERR, "SEGURIDAD: la demostración solo usa /app/storage/demo.sqlite\n");
    exit(1);
}
$clave = getenv('DEMO_CLAVE');
if (!is_string($clave) || strlen($clave) < 16) {
    fwrite(STDERR, "Falta DEMO_CLAVE (mínimo 16 caracteres)\n");
    exit(1);
}
$catalogo = app(CatalogoPosgrados::class);
if ($catalogo->estado()['estado'] !== 'activo') {
    fwrite(STDERR, 'El catálogo no quedó activo en la demostración: '.json_encode($catalogo->estado())."\n");
    exit(1);
}

$inst = DB::table('instituciones_afiliadas')->insertGetId(['numero_afiliacion' => 'DEMO01', 'nombre' => 'Universidad Ficticia de Demostración',
    'campus' => 'Campus Ficticio', 'zona_anfeca' => null, 'created_at' => now(), 'updated_at' => now()]);
$guardado = $catalogo->titulos('Maestría', 'Informática')[0];

$usuarios = [
    'demo.formacion' => null,
    'demo.guardado' => ['Maestría', 'Informática', null, ['titulo_ultimo_grado_especifico' => $guardado['titulo'], 'origen_titulo_ultimo_grado' => 'catalogo',
        'clave_posgrado_catalogo' => $guardado['clave'], 'version_catalogo_posgrados' => $catalogo->version()]],
    'demo.historico' => ['Maestría', 'Otro', 'Economía', []],
];
$n = 0;
foreach ($usuarios as $correo => $ultimo) {
    $n++;
    $u = DB::table('users')->insertGetId(['name' => "Solicitante ficticio {$n}", 'email' => "{$correo}@example.test", 'password' => Hash::make($clave),
        'rol' => 'solicitante', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('datos_solicitantes')->insert(['user_id' => $u, 'curp' => sprintf('DEMO9001%02dHDFRRN0%d', $n, $n), 'rfc' => sprintf('DEMO9001%02dA%d', $n, $n),
        'nombres' => "Persona demo {$n}", 'apellido_paterno' => 'Ficticia', 'genero' => 'Femenino', 'calle' => 'Calle Ficticia', 'numero_exterior' => '1',
        'codigo_postal' => '01000', 'entidad_federativa' => 'Ciudad de México', 'colonia' => 'Centro', 'municipio' => 'Cuauhtémoc',
        'correo_electronico' => "{$correo}@example.test", 'telefono_celular' => '5500000000', 'datos_autorizados' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('selecciones_certificacion')->insert(['user_id' => $u, 'certificacion' => 'ANFECA', 'tipo_personal' => 'Profesoras y profesores de Tiempo Completo',
        'area_certificacion' => 'Administración', 'created_at' => now(), 'updated_at' => now()]);
    $s = DB::table('solicitudes_certificacion')->insertGetId(['user_id' => $u, 'tipo_tramite' => 'inicial', 'certificacion' => 'ANFECA', 'estado' => 'borrador',
        'tipo_personal' => 'Profesoras y profesores de Tiempo Completo', 'area_certificacion' => 'Administración', 'created_at' => now(), 'updated_at' => now()]);
    if ($ultimo) {
        DB::table('formaciones_academicas_solicitud')->insert(array_merge(['solicitud_id' => $s, 'nivel_origen' => 'Licenciatura', 'titulo_origen' => 'Administración',
            'nivel_ultimo_grado' => $ultimo[0], 'titulo_ultimo_grado' => $ultimo[1], 'titulo_ultimo_grado_otro' => $ultimo[2],
            'institucion_afiliada_id' => $inst, 'created_at' => now(), 'updated_at' => now()], $ultimo[3]));
    }
}
echo 'Sembrado: '.count($usuarios)." solicitantes ficticios; catálogo {$catalogo->version()} activo.\n";
