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
// Revisión documental funcional (6.3.1): revisiones_documentales ya se creó con «migrate» en esta base desechable.
$disco = Illuminate\Support\Facades\Storage::disk('local');

/** PDF FICTICIO válido y mínimo: una página por elemento [ancho, alto, texto] (puntos PDF). */
function pdfFicticio(array $paginas): string
{
    $obj = [];
    $kids = [];
    $n = 3;
    foreach ($paginas as [$w, $h, $texto]) {
        $flujo = "BT /F1 22 Tf 60 ".($h - 90)." Td (".addcslashes($texto, '()\\').") Tj ET\nBT /F1 12 Tf 60 ".($h - 120)." Td (DOCUMENTO FICTICIO DE DEMOSTRACION - sin validez) Tj ET";
        $obj[$n] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$w} {$h}] /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> /Contents ".($n + 1)." 0 R >>";
        $obj[$n + 1] = "<< /Length ".strlen($flujo)." >>\nstream\n{$flujo}\nendstream";
        $kids[] = "{$n} 0 R";
        $n += 2;
    }
    $obj[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $obj[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
    ksort($obj);
    $pdf = "%PDF-1.4\n";
    $pos = [];
    foreach ($obj as $i => $o) {
        $pos[$i] = strlen($pdf);
        $pdf .= "{$i} 0 obj\n{$o}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($obj) + 1)."\n0000000000 65535 f \n";
    foreach ($pos as $p) {
        $pdf .= sprintf("%010d 00000 n \n", $p);
    }

    return $pdf."trailer\n<< /Size ".(count($obj) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
}

$zona = DB::table('zonas')->insertGetId(['siglas' => 'Z-DEMO', 'descripcion' => 'Zona FICTICIA de demostración']);
$inst = DB::table('instituciones_afiliadas')->insertGetId(['numero_afiliacion' => 'DEMO-1', 'nombre' => 'Universidad FICTICIA de Demostración', 'escuela_facultad' => 'Facultad ficticia', 'zona_id' => $zona]);

for ($k = 1; $k <= 3; $k++) {
    $u = DB::table('users')->insertGetId(['name' => "Persona ficticia {$k}", 'email' => "demo.solicitante{$k}@example.test", 'password' => Hash::make($clave), 'rol' => 'solicitante']);
    DB::table('datos_solicitantes')->insert(['user_id' => $u, 'curp' => sprintf('DEMO9001%02dHDFRRN0%d', $k, $k), 'rfc' => sprintf('DEMO9001%02dA%d', $k, $k), 'nombres' => "Persona{$k}", 'apellido_paterno' => 'Ficticia', 'genero' => 'Femenino', 'calle' => 'Ficticia', 'numero_exterior' => '1', 'codigo_postal' => '01000', 'entidad_federativa' => 'Ciudad de México', 'colonia' => 'Ficticia', 'municipio' => 'Ficticio', 'correo_electronico' => "demo.solicitante{$k}@example.test", 'telefono_celular' => '5500000000', 'datos_autorizados' => true]);
    $s = DB::table('solicitudes_certificacion')->insertGetId(['user_id' => $u, 'tipo_tramite' => $k === 3 ? 'refrendo' : 'inicial', 'certificacion' => 'ANFECA', 'estado' => 'enviado', 'tipo_personal' => 'Profesoras y profesores de Tiempo Completo', 'area_certificacion' => 'Administración', 'fecha_envio' => now()->subDays($k), 'compromiso_aceptado' => true, 'created_at' => now(), 'updated_at' => now()]);
    // Documentos FICTICIOS (PDF mínimos generados aquí); las cédulas quedan sin cargar.
    // Persona 1: PDF válidos (uno de 3 páginas y otro con una página horizontal).
    // Persona 2: comprobante como IMAGEN PNG. Persona 3: constancia como PDF DAÑADO (prueba del visor).
    $pdf = function (string $nombre) use ($disco, $s, $k) {
        $dañado = $k === 3 && $nombre === 'constancia_antiguedad';
        $imagen = $k === 2 && $nombre === 'comprobante_pago' && function_exists('imagecreatetruecolor');
        $ruta = "anfeca/documentos/solicitudes/{$s}/".bin2hex(random_bytes(8))."_{$nombre}.".($imagen ? 'png' : 'pdf');
        if ($imagen) {
            $im = imagecreatetruecolor(900, 600);
            imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
            imagestring($im, 5, 40, 40, "COMPROBANTE FICTICIO - Persona ficticia {$k}", imagecolorallocate($im, 189, 36, 52));
            ob_start();
            imagepng($im);
            $disco->put($ruta, ob_get_clean());
        } elseif ($dañado) {
            $disco->put($ruta, "%PDF-1.4\n% ARCHIVO DAÑADO INTENCIONALMENTE (demostración)\n");
        } else {
            $paginas = $nombre === 'titulo' && $k === 1
                ? [[612, 792, "Titulo ficticio - pagina 1"], [612, 792, "Titulo ficticio - pagina 2"], [612, 792, "Titulo ficticio - pagina 3"]]
                : ($nombre === 'constancia_antiguedad' ? [[792, 612, "Constancia ficticia (horizontal)"]] : [[612, 792, ucfirst(str_replace('_', ' ', $nombre))." ficticio - Persona ficticia {$k}"]]);
            $disco->put($ruta, pdfFicticio($paginas));
        }

        return $ruta;
    };
    DB::table('formaciones_academicas_solicitud')->insert(['solicitud_id' => $s, 'nivel_origen' => 'Licenciatura', 'titulo_origen' => 'Administración', 'institucion_afiliada_id' => $inst,
        'titulo_origen_archivo' => $pdf('titulo'), 'titulo_ultimo_grado_archivo' => $pdf('titulo_ultimo_grado')]);
    DB::table('documentos_probatorios_solicitud')->insert(['solicitud_id' => $s, 'comprobante_pago_archivo' => $pdf('comprobante_pago'), 'constancia_antiguedad_archivo' => $pdf('constancia_antiguedad')]);
}

foreach ([['demo.nacional@anfeca.test', 'coordinador_nacional', false], ['demo.zona@anfeca.test', 'coordinador_zona', true]] as [$correo, $rol, $conZona]) {
    $u = DB::table('users')->insertGetId(['name' => 'Coordinación FICTICIA ('.$rol.')', 'email' => $correo, 'password' => Hash::make($clave), 'rol' => $rol]);
    if ($conZona) {
        DB::table('coordinador_zona')->insert(['user_id' => $u, 'zona_id' => $zona]);
    }
}
echo "Demostración sembrada: 3 solicitudes con documentos ficticios y 2 cuentas de coordinación ficticias.\n";
