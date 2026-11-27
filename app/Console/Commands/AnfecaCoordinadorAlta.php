<?php

namespace App\Console\Commands;

use App\Models\Cuenta;
use App\Services\Anfeca\Catalogo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Alta idempotente de una cuenta de coordinación en PERSONA + USUARIO + ROL (36 tablas oficiales).
 *  - Nacional: ROL coordinador_nacional (privilegio alcance_nacional).
 *  - Regional: ROL coordinador_zona_N (privilegio alcance_zona_N), N = 1…7.
 * La contraseña se genera aleatoriamente y SOLO se anexa a un archivo fuera del proyecto con permisos 0600.
 * Nunca convierte cuentas de solicitantes ni personas con solicitudes.
 */
#[Signature('anfeca:coordinador-alta
    {correo : Correo de la cuenta}
    {rol : coordinador_nacional | coordinador_zona}
    {--zona= : Número de zona 1 a 7 (solo coordinador_zona)}
    {--nombre= : Nombre(s)}
    {--apellido= : Apellido paterno}
    {--curp= : CURP de la persona coordinadora (PERSONA.PERS_CURP es obligatoria)}
    {--rfc= : RFC (PERSONA.PERS_RFC es obligatorio)}
    {--credenciales= : Archivo local fuera del proyecto donde se anexa la contraseña (0600)}
    {--restablecer : Genera una nueva contraseña para una cuenta de coordinación existente}')]
#[Description('Da de alta (o actualiza el rol de) una cuenta de coordinación ANFECA en el modelo oficial.')]
class AnfecaCoordinadorAlta extends Command
{
    public function handle(): int
    {
        $correo = mb_strtolower(trim((string) $this->argument('correo')));
        $tipo = (string) $this->argument('rol');
        $zona = $this->option('zona');
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 150) {
            return $this->fallo('El correo no es válido.');
        }
        if ($tipo === Cuenta::ROL_COORDINADOR_NACIONAL) {
            if ($zona !== null) {
                return $this->fallo('La coordinación nacional abarca todas las zonas: no admite --zona.');
            }
            $rol = Cuenta::ROL_COORDINADOR_NACIONAL;
        } elseif ($tipo === 'coordinador_zona') {
            if (!preg_match('/^[1-7]$/', (string) $zona)) {
                return $this->fallo('Indique --zona con un número de 1 a 7.');
            }
            $rol = Cuenta::PREFIJO_COORDINADOR_ZONA.$zona;
        } else {
            return $this->fallo('Rol no permitido. Use coordinador_nacional o coordinador_zona.');
        }

        $existente = Cuenta::query()->whereRaw('lower(usua_nombre) = ?', [$correo])->first();
        if ($existente && !$existente->esCoordinador()) {
            return $this->fallo('El correo pertenece a una cuenta que no es de coordinación: no se modifica.');
        }
        if ($existente && DB::table('solicitud')->where('soli_id_persona', $existente->usua_id_persona)->exists()) {
            return $this->fallo('La persona tiene solicitudes de certificación: no se modifica.');
        }
        $generar = !$existente || $this->option('restablecer');
        $archivo = (string) $this->option('credenciales');
        if ($generar) {
            $real = $archivo === '' ? false : realpath(dirname($archivo));
            if ($real === false || str_starts_with($real.'/', rtrim(base_path(), '/').'/')) {
                return $this->fallo('Indique --credenciales en un directorio existente FUERA del proyecto.');
            }
        }
        if (!$existente) {
            $curp = mb_strtoupper(trim((string) $this->option('curp')));
            $rfc = mb_strtoupper(trim((string) $this->option('rfc')));
            if (!preg_match('/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[A-Z0-9][0-9]$/', $curp) || mb_strlen($rfc) < 12 || mb_strlen($rfc) > 13
                || blank($this->option('nombre'))) {
                return $this->fallo('Para una cuenta nueva indique --nombre, --curp (18) y --rfc (12-13).');
            }
        }

        $contrasena = $generar ? Str::password(20) : null;
        DB::transaction(function () use ($existente, $correo, $rol, $contrasena) {
            $rolId = Catalogo::rol($rol);
            if ($existente) {
                $cambios = ['usua_id_rol' => $rolId];
                if ($contrasena) {
                    $cambios['usua_contrasenia'] = Hash::make($contrasena);
                }
                DB::table('usuario')->where('usua_id_usuario', $existente->usua_id_usuario)->update($cambios);

                return;
            }
            $persona = DB::table('persona')->insertGetId([
                'pers_nombre' => trim((string) $this->option('nombre')),
                'pers_apellido_paterno' => $this->option('apellido') ?: null,
                'pers_curp' => mb_strtoupper(trim((string) $this->option('curp'))),
                'pers_rfc' => mb_strtoupper(trim((string) $this->option('rfc'))),
                'pers_activo' => true,
            ], 'pers_id_persona');
            DB::table('usuario')->insert([
                'usua_id_persona' => $persona,
                'usua_id_rol' => $rolId,
                'usua_nombre' => $correo,
                'usua_contrasenia' => Hash::make($contrasena),
                'usua_estatus' => config('anfeca_8088.estatus_usuario.ACTIVO'),
            ]);
            DB::table('comunicacion')->insert([
                'comu_id_persona' => $persona,
                'comu_id_tipo_comunicacion' => Catalogo::tipoComunicacion('CORREO'),
                'comu_valor' => $correo,
                'comu_visible' => true,
            ]);
        });
        if ($contrasena) {
            file_put_contents($archivo, "{$correo}\t{$contrasena}\n", FILE_APPEND | LOCK_EX);
            chmod($archivo, 0600);
        }
        $this->info(($existente ? 'Cuenta actualizada' : 'Cuenta creada').": {$correo} ({$rol}).".($contrasena ? " Contraseña anexada a {$archivo}." : ''));

        return self::SUCCESS;
    }

    private function fallo(string $mensaje): int
    {
        $this->error($mensaje);

        return self::FAILURE;
    }
}
