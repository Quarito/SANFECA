<?php
// Demostración DESECHABLE del prototipo de revisión documental. Datos 100 % ficticios.
// Solo opera sobre /app/storage/demo.sqlite dentro del contenedor; cualquier otra base se rechaza.
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\{Artisan, DB, Hash};

if (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== '/app/storage/demo.sqlite') {
    fwrite(STDERR, "SEGURIDAD: la demostración solo usa /app/storage/demo.sqlite\n");
    exit(1);
}
$clave = getenv('DEMO_CLAVE');
if (!is_string($clave) || strlen($clave) < 16) {
    fwrite(STDERR, "Falta DEMO_CLAVE (mínimo 16 caracteres)\n");
    exit(1);
}

Artisan::call('migrate', ['--force' => true]);

$zona = DB::table('zonas')->insertGetId(['siglas' => 'Z-DEMO', 'descripcion' => 'Zona FICTICIA de demostración']);
$inst = DB::table('instituciones_afiliadas')->insertGetId(['numero_afiliacion' => 'DEMO-1', 'nombre' => 'Universidad FICTICIA de Demostración', 'escuela_facultad' => 'Facultad ficticia', 'zona_id' => $zona]);

for ($k = 1; $k <= 3; $k++) {
    $u = DB::table('users')->insertGetId(['name' => "Persona ficticia {$k}", 'email' => "demo.solicitante{$k}@example.test", 'password' => Hash::make($clave), 'rol' => 'solicitante']);
    DB::table('datos_solicitantes')->insert(['user_id' => $u, 'curp' => sprintf('DEMO9001%02dHDFRRN0%d', $k, $k), 'rfc' => sprintf('DEMO9001%02dA%d', $k, $k), 'nombres' => "Persona{$k}", 'apellido_paterno' => 'Ficticia', 'genero' => 'Femenino', 'calle' => 'Ficticia', 'numero_exterior' => '1', 'codigo_postal' => '01000', 'entidad_federativa' => 'Ciudad de México', 'colonia' => 'Ficticia', 'municipio' => 'Ficticio', 'correo_electronico' => "demo.solicitante{$k}@example.test", 'telefono_celular' => '5500000000', 'datos_autorizados' => true]);
    $s = DB::table('solicitudes_certificacion')->insertGetId(['user_id' => $u, 'tipo_tramite' => $k === 3 ? 'refrendo' : 'inicial', 'certificacion' => 'ANFECA', 'estado' => 'enviado', 'tipo_personal' => 'Profesoras y profesores de Tiempo Completo', 'area_certificacion' => 'Administración', 'fecha_envio' => now()->subDays($k), 'compromiso_aceptado' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('formaciones_academicas_solicitud')->insert(['solicitud_id' => $s, 'nivel_origen' => 'Licenciatura', 'titulo_origen' => 'Administración', 'institucion_afiliada_id' => $inst]);
}

foreach ([['demo.nacional@anfeca.test', 'coordinador_nacional', false], ['demo.zona@anfeca.test', 'coordinador_zona', true]] as [$correo, $rol, $conZona]) {
    $u = DB::table('users')->insertGetId(['name' => 'Coordinación FICTICIA ('.$rol.')', 'email' => $correo, 'password' => Hash::make($clave), 'rol' => $rol]);
    if ($conZona) {
        DB::table('coordinador_zona')->insert(['user_id' => $u, 'zona_id' => $zona]);
    }
}
echo "Demostración sembrada: 3 solicitudes y 2 cuentas de coordinación ficticias.\n";
