<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Alta idempotente de una cuenta de coordinación.
 *
 * Seguridad:
 *  - Nunca convierte cuentas de solicitantes (ni cuentas con datos o solicitudes).
 *  - La contraseña se genera aleatoriamente (20 caracteres), se guarda con Hash::make
 *    y SOLO se escribe en un archivo local indicado con --credenciales, fuera del
 *    proyecto, con permisos 0600. Nunca se imprime en pantalla.
 *  - Las zonas se indican por SIGLAS del catálogo (tabla zonas), nunca por número.
 *  - Todo ocurre en una transacción.
 */
#[Signature('anfeca:coordinador-alta
    {correo : Correo de la cuenta}
    {rol : coordinador_nacional | coordinador_zona}
    {--nombre= : Nombre visible}
    {--zona=* : Siglas de zona del catálogo (solo coordinador_zona; repetible)}
    {--credenciales= : Archivo local fuera del proyecto donde se anexa la contraseña (0600)}
    {--restablecer : Genera una nueva contraseña para una cuenta de coordinación existente}')]
#[Description('Da de alta (o actualiza las zonas de) una cuenta de coordinación ANFECA.')]
class AnfecaCoordinadorAlta extends Command
{
    public function handle(): int
    {
        $correo = mb_strtolower(trim((string) $this->argument('correo')));
        $rol = (string) $this->argument('rol');
        $siglas = array_values(array_unique(array_filter(array_map('trim', (array) $this->option('zona')))));

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return $this->fallo('El correo no es válido.');
        }
        if (!in_array($rol, User::ROLES_COORDINACION, true)) {
            return $this->fallo('Rol no permitido. Use coordinador_nacional o coordinador_zona.');
        }
        if ($rol === User::ROL_COORDINADOR_NACIONAL && $siglas !== []) {
            return $this->fallo('La coordinación nacional abarca todas las zonas: no admite --zona.');
        }

        $zonaIds = [];
        foreach ($siglas as $s) {
            $id = DB::table('zonas')->where('siglas', $s)->value('id');
            if ($id === null) {
                return $this->fallo("La zona «{$s}» no existe en el catálogo. Registre primero el catálogo oficial.");
            }
            $zonaIds[] = (int) $id;
        }

        $existente = User::where('email', $correo)->first();
        $nueva = $existente === null;
        $generar = $nueva || $this->option('restablecer');

        if ($existente !== null && !$existente->esCoordinador()) {
            return $this->fallo('El correo pertenece a una cuenta que no es de coordinación: no se modifica.');
        }
        if ($existente !== null && (
            DB::table('datos_solicitantes')->where('user_id', $existente->id)->exists()
            || DB::table('solicitudes_certificacion')->where('user_id', $existente->id)->exists()
        )) {
            return $this->fallo('La cuenta tiene datos o solicitudes de solicitante: no se modifica.');
        }

        $archivo = (string) $this->option('credenciales');
        if ($generar) {
            if ($archivo === '') {
                return $this->fallo('Indique --credenciales=/ruta/segura/fuera/del/proyecto para entregar la contraseña.');
            }
            $real = realpath(dirname($archivo));
            if ($real === false || str_starts_with($real.'/', rtrim(base_path(), '/').'/')) {
                return $this->fallo('El archivo de credenciales debe estar en un directorio existente FUERA del proyecto.');
            }
        }

        $contrasena = $generar ? Str::password(20) : null;

        DB::transaction(function () use ($existente, $correo, $rol, $zonaIds, $contrasena, &$usuario) {
            $usuario = $existente ?? new User();
            $usuario->forceFill([
                'name' => (string) ($this->option('nombre') ?: $usuario->name ?: $correo),
                'email' => $correo,
                'rol' => $rol,
            ]);
            if ($contrasena !== null) {
                $usuario->password = Hash::make($contrasena);
            }
            $usuario->save();

            // Las asignaciones quedan exactamente como se indican (coordinación nacional: ninguna).
            DB::table('coordinador_zona')->where('user_id', $usuario->id)->whereNotIn('zona_id', $zonaIds)->delete();
            foreach ($zonaIds as $zonaId) {
                DB::table('coordinador_zona')->insertOrIgnore([
                    'user_id' => $usuario->id, 'zona_id' => $zonaId, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        if ($contrasena !== null) {
            $previo = umask(0077);
            file_put_contents($archivo, $correo."\t".$rol."\t".$contrasena.PHP_EOL, FILE_APPEND | LOCK_EX);
            umask($previo);
            chmod($archivo, 0600);
        }

        $this->info(sprintf(
            '%s %s (%s). Zonas: %s.%s',
            $nueva ? 'Cuenta creada:' : 'Cuenta actualizada:',
            $correo,
            $rol,
            $rol === User::ROL_COORDINADOR_NACIONAL ? 'todas' : ($siglas === [] ? 'sin asignación' : implode(', ', $siglas)),
            $contrasena !== null ? ' Contraseña escrita en el archivo de credenciales indicado.' : ''
        ));

        return self::SUCCESS;
    }

    private function fallo(string $mensaje): int
    {
        $this->error($mensaje);

        return self::FAILURE;
    }
}
