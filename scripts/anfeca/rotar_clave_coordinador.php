<?php
/*
 * =============================================================================
 * ANFECA · Rotación de la contraseña de UNA cuenta de coordinación (sin mostrarla nunca).
 *
 *   cd /var/www/ANFECA_ENSAYO
 *   ANFECA_AUTORIZO_ROTACION=<correo> php scripts/anfeca/rotar_clave_coordinador.php <correo> <archivo_tsv>
 *
 * - Solo cuentas con rol de coordinación. No toca ningún otro usuario.
 * - Genera la contraseña localmente (random_bytes), la guarda con Hash::make() y la verifica.
 * - Cambia remember_token: invalida las cookies «recordarme».
 * - Invalida las sesiones activas de esa cuenta según SESSION_DRIVER (file: borra solo sus
 *   archivos de sesión; database: borra solo sus filas).
 * - Actualiza el archivo TSV local (permisos 600) con copia previa (600), sin imprimir la contraseña.
 * - Registra la operación (sin secretos) en ~/ANFECA_RESPALDOS/rotacion_credenciales.log.
 * =============================================================================
 */
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

function detener(string $m): never
{
    fwrite(STDERR, "DETENIDO: {$m}\n");
    exit(1);
}

[$correo, $tsv] = [$argv[1] ?? '', $argv[2] ?? ''];
if ($correo === '' || $tsv === '') {
    detener('uso: php scripts/anfeca/rotar_clave_coordinador.php <correo> <archivo_tsv>');
}
if (getenv('ANFECA_AUTORIZO_ROTACION') !== $correo) {
    detener('falta la autorización explícita ANFECA_AUTORIZO_ROTACION=<correo>.');
}
if (!is_file($tsv) || (fileperms($tsv) & 0o077) !== 0) {
    detener('el archivo de credenciales no existe o no tiene permisos 600.');
}
$lineas = file($tsv, FILE_IGNORE_NEW_LINES);
$indice = null;
foreach ($lineas as $i => $l) {
    if (explode("\t", $l)[0] === $correo) {
        $indice = $i;
    }
}
if ($indice === null) {
    detener('la cuenta no figura en el archivo de credenciales.');
}

$usuario = User::where('email', $correo)->first();
if ($usuario === null || !$usuario->esCoordinador()) {
    detener('la cuenta no existe o no es de coordinación.');
}

// Contraseña robusta: 24 caracteres de un alfabeto sin ambigüedades, con todas las clases.
$alfabeto = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnpqrstuvwxyz', '23456789', '-_.:;!?#%+='];
do {
    $clave = '';
    for ($i = 0; $i < 24; $i++) {
        $grupo = $alfabeto[$i % 4];
        $clave .= $grupo[random_int(0, strlen($grupo) - 1)];
    }
    $clave = str_shuffle($clave);
} while (preg_match('/^[A-Za-z0-9]/', $clave) !== 1);

$otros = DB::table('users')->where('id', '<>', $usuario->id)->orderBy('id')->get(['id', 'password', 'remember_token'])->toJson();

DB::transaction(function () use ($usuario, $clave) {
    $u = User::whereKey($usuario->id)->lockForUpdate()->firstOrFail();
    $u->forceFill(['password' => Hash::make($clave), 'remember_token' => Str::random(60)])->save();
});
$usuario->refresh();
if (!Hash::check($clave, $usuario->password)) {
    detener('la verificación del nuevo hash falló (la base quedó con el hash nuevo; repita la rotación).');
}
if (DB::table('users')->where('id', '<>', $usuario->id)->orderBy('id')->get(['id', 'password', 'remember_token'])->toJson() !== $otros) {
    detener('ALERTA: cambió otro usuario. Revise de inmediato.');
}

// Sesiones activas de la cuenta.
$clave_sesion = Auth::guard('web')->getName();
$sesiones = 0;
$driver = config('session.driver');
if ($driver === 'file') {
    foreach (glob(config('session.files').'/*') ?: [] as $archivo) {
        $contenido = @file_get_contents($archivo);
        if ($contenido === false || $contenido === '') {
            continue;
        }
        if (config('session.encrypt')) {
            try {
                $contenido = Crypt::decryptString($contenido);
            } catch (Throwable) {
                continue;
            }
        }
        // Laravel 13 guarda la sesión en JSON (session.serialization = json); se admite también serialize().
        $datos = json_decode($contenido, true);
        if (!is_array($datos)) {
            $datos = @unserialize($contenido, ['allowed_classes' => false]);
        }
        if (is_array($datos) && isset($datos[$clave_sesion]) && (int) $datos[$clave_sesion] === (int) $usuario->id) {
            $sesiones += @unlink($archivo) ? 1 : 0;
        }
    }
} elseif ($driver === 'database') {
    $sesiones = DB::table(config('session.table', 'sessions'))->where('user_id', $usuario->id)->delete();
} else {
    fwrite(STDERR, "Aviso: SESSION_DRIVER={$driver}; las sesiones se invalidan al vencer. remember_token sí se cambió.\n");
}

// Archivo local de credenciales: copia previa y reemplazo atómico, ambos con permisos 600.
$copia = $tsv.'.antes_rotacion_'.date('Ymd_His');
copy($tsv, $copia);
chmod($copia, 0600);
$campos = explode("\t", $lineas[$indice]);
$campos[2] = $clave;
$lineas[$indice] = implode("\t", $campos);
$temporal = $tsv.'.tmp';
umask(0077);
file_put_contents($temporal, implode("\n", $lineas)."\n");
chmod($temporal, 0600);
rename($temporal, $tsv);

$registro = getenv('HOME').'/ANFECA_RESPALDOS/rotacion_credenciales.log';
@file_put_contents($registro, sprintf("%s\tcuenta=%s\tid=%d\tsesiones_invalidadas=%d\tdriver=%s\tbase=%s\thash_verificado=si\tcopia_tsv=%s\n",
    date('c'), $correo, $usuario->id, $sesiones, $driver, DB::connection()->getDatabaseName(), basename($copia)), FILE_APPEND);
@chmod($registro, 0600);

echo "Rotación completada para {$correo}: hash verificado, remember_token renovado, {$sesiones} sesión(es) invalidada(s).\n";
echo "La nueva contraseña está en {$tsv} (permisos 600). Consúltela personalmente; no se muestra aquí.\n";
