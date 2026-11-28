<?php

namespace App\Services\Anfeca;

use App\Models\Cuenta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Fluent;
use Illuminate\Validation\ValidationException;

/**
 * Datos personales, domicilio y contacto sobre PERSONA, USUARIO, DOMICILIO, ENTIDAD_FEDERATIVA,
 * COMUNICACION y TIPO_COMUNICACION (esta última pareja solo para correo y teléfonos).
 * DOMICILIO guarda únicamente sus atributos oficiales: calle, número exterior, número interior y entidad federativa.
 * Código postal, colonia y alcaldía o municipio se validan al capturar (SEPOMEX) pero no se guardan: el modelo
 * vigente no relaciona DOMICILIO con COLONIA ni con ALCALDIA_MUNICIPIO, y no se usa ningún mecanismo alternativo. Expone los mismos nombres de campo que ya usan las pantallas
 * (curp, nombres, calle, colonia, telefono_celular…), de modo que Blade y Vue no cambian.
 */
final class ExpedienteSolicitante
{
    public const GENEROS = ['Femenino' => 'F', 'Masculino' => 'M'];

    /** Datos del solicitante con los nombres de campo de las pantallas, o null si la cuenta no tiene persona. */
    public function datos(Cuenta|int $cuenta): ?Fluent
    {
        $usuario = $cuenta instanceof Cuenta ? $cuenta : Cuenta::find($cuenta);
        if (!$usuario) {
            return null;
        }
        $persona = DB::table('persona')->where('pers_id_persona', $usuario->usua_id_persona)->first();
        if (!$persona) {
            return null;
        }
        $domicilio = DB::table('domicilio as d')
            ->join('entidad_federativa as e', 'e.enfe_id_entidad_federativa', '=', 'd.domi_id_entidad_federativa')
            ->where('d.domi_id_persona', $persona->pers_id_persona)
            ->orderByDesc('d.domi_id_domicilio')->first();
        $contacto = $this->comunicaciones((int) $persona->pers_id_persona);

        return new Fluent([
            'id' => (int) $persona->pers_id_persona,
            'user_id' => (int) $usuario->usua_id_usuario,
            'persona_id' => (int) $persona->pers_id_persona,
            'curp' => $persona->pers_curp,
            'rfc' => $persona->pers_rfc,
            'cedula_profesional' => null,
            'nombres' => $persona->pers_nombre,
            'apellido_paterno' => $persona->pers_apellido_paterno,
            'apellido_materno' => $persona->pers_apellido_materno,
            'genero' => array_flip(self::GENEROS)[$persona->pers_genero] ?? null,
            'calle' => $domicilio?->domi_calle,
            'numero_exterior' => $domicilio?->domi_numero_ext,
            'numero_interior' => $domicilio?->domi_numero_int,
            'entidad_federativa' => $domicilio?->enfe_nombre,
            // No recuperables tras guardar: DOMICILIO no tiene relación con COLONIA ni ALCALDIA_MUNICIPIO.
            'codigo_postal' => null,
            'colonia' => null,
            'municipio' => null,
            'correo_electronico' => $contacto['CORREO'] ?? $usuario->usua_nombre,
            'telefono_particular' => $contacto['TEL_PARTICULAR'] ?? null,
            'telefono_celular' => $contacto['TEL_CELULAR'] ?? null,
            'datos_autorizados' => $usuario->datosAutorizados(),
            'institucion_id' => $persona->pers_id_institucion !== null ? (int) $persona->pers_id_institucion : null,
        ]);
    }

    /** @return array<string, string> clave interna => valor (el registro más reciente de cada tipo) */
    public function comunicaciones(int $personaId): array
    {
        $tipos = Catalogo::tiposComunicacionPorId();
        $valores = [];
        foreach (DB::table('comunicacion')->where('comu_id_persona', $personaId)->orderBy('comu_id_comunicacion')->get() as $c) {
            if (isset($tipos[(int) $c->comu_id_tipo_comunicacion])) {
                $valores[$tipos[(int) $c->comu_id_tipo_comunicacion]] = $c->comu_valor;
            }
        }

        return $valores;
    }

    public function correoRegistrado(string $correo, ?int $exceptoUsuario = null): bool
    {
        $correo = mb_strtolower(trim($correo));
        $usuario = DB::table('usuario')->whereRaw('lower(usua_nombre) = ?', [$correo])
            ->when($exceptoUsuario, fn ($q) => $q->where('usua_id_usuario', '<>', $exceptoUsuario))->exists();
        $contacto = DB::table('comunicacion')->where('comu_id_tipo_comunicacion', Catalogo::tipoComunicacion('CORREO'))
            ->whereRaw('lower(comu_valor) = ?', [$correo])
            ->when($exceptoUsuario, fn ($q) => $q->whereNotIn('comu_id_persona',
                DB::table('usuario')->where('usua_id_usuario', $exceptoUsuario)->select('usua_id_persona')))->exists();

        return $usuario || $contacto;
    }

    public function curpRegistrada(string $curp): bool
    {
        return DB::table('persona')->whereRaw('upper(pers_curp) = ?', [mb_strtoupper($curp)])->exists();
    }

    public function rfcRegistrado(string $rfc): bool
    {
        return DB::table('persona')->whereRaw('upper(pers_rfc) = ?', [mb_strtoupper($rfc)])->exists();
    }

    /**
     * Alta completa (registro desde cero): PERSONA → USUARIO (rol solicitante) → DOMICILIO → COMUNICACION.
     * Todo en una transacción: si algo falla no queda ninguna cuenta parcial.
     */
    public function registrar(array $datos, string $password): Cuenta
    {
        return DB::transaction(function () use ($datos, $password) {
            // Serializa las altas para que dos registros simultáneos no dupliquen correo o CURP.
            DB::statement('lock table persona in share row exclusive mode');
            if ($this->curpRegistrada($datos['curp'])) {
                throw ValidationException::withMessages(['curp' => 'La CURP ya está registrada.']);
            }
            if ($this->rfcRegistrado($datos['rfc'])) {
                throw ValidationException::withMessages(['rfc' => 'El RFC ya está registrado.']);
            }
            if ($this->correoRegistrado($datos['correo_electronico'])) {
                throw ValidationException::withMessages(['correo_electronico' => 'El correo electrónico ya está registrado.']);
            }

            $personaId = DB::table('persona')->insertGetId([
                'pers_id_institucion' => null,
                'pers_nombre' => $datos['nombres'],
                'pers_apellido_paterno' => $datos['apellido_paterno'],
                'pers_apellido_materno' => $datos['apellido_materno'] ?? null,
                'pers_curp' => $datos['curp'],
                'pers_rfc' => $datos['rfc'],
                'pers_genero' => self::GENEROS[$datos['genero']] ?? null,
                'pers_num_afiliacion_institucion' => null,
                'pers_activo' => true,
            ], 'pers_id_persona');

            $usuarioId = DB::table('usuario')->insertGetId([
                'usua_id_persona' => $personaId,
                'usua_id_rol' => Catalogo::rol(Cuenta::ROL_SOLICITANTE),
                'usua_nombre' => mb_strtolower(trim($datos['correo_electronico'])),
                'usua_contrasenia' => Hash::make($password),
                'usua_estatus' => config('anfeca_8088.estatus_usuario.ACTIVO'),
            ], 'usua_id_usuario');

            $this->guardarDomicilio($personaId, $datos);
            $this->guardarComunicacion($personaId, 'CORREO', mb_strtolower(trim($datos['correo_electronico'])));
            $this->guardarComunicacion($personaId, 'TEL_PARTICULAR', $datos['telefono_particular'] ?? null);
            $this->guardarComunicacion($personaId, 'TEL_CELULAR', $datos['telefono_celular'] ?? null);

            return Cuenta::findOrFail($usuarioId);
        });
    }

    /** Antes de autorizar: solo domicilio completo y teléfonos. Identidad y correo quedan bloqueados. */
    public function actualizarDomicilioYTelefonos(Cuenta $cuenta, array $datos): void
    {
        DB::transaction(function () use ($cuenta, $datos) {
            $usuario = DB::table('usuario')->where('usua_id_usuario', $cuenta->usua_id_usuario)->lockForUpdate()->first();
            if ($usuario->usua_estatus === config('anfeca_8088.estatus_usuario.AUTORIZADO')) {
                throw ValidationException::withMessages(['datos' => 'Los datos personales ya fueron autorizados y no pueden modificarse.']);
            }
            $this->guardarDomicilio((int) $usuario->usua_id_persona, $datos);
            $this->guardarComunicacion((int) $usuario->usua_id_persona, 'TEL_PARTICULAR', $datos['telefono_particular'] ?? null);
            $this->guardarComunicacion((int) $usuario->usua_id_persona, 'TEL_CELULAR', $datos['telefono_celular'] ?? null);
        });
    }

    public function autorizar(Cuenta $cuenta): void
    {
        DB::table('usuario')->where('usua_id_usuario', $cuenta->usua_id_usuario)
            ->update(['usua_estatus' => config('anfeca_8088.estatus_usuario.AUTORIZADO')]);
        $cuenta->usua_estatus = config('anfeca_8088.estatus_usuario.AUTORIZADO');
    }

    /** Institución de afiliación de la persona (PERSONA.PERS_ID_INSTITUCION), capturada en Formación académica. */
    public function asignarInstitucion(int $personaId, int $institucionId): void
    {
        $numero = DB::table('institucion')->where('inst_id_institucion', $institucionId)->value('inst_numero_afiliacion');
        DB::table('persona')->where('pers_id_persona', $personaId)->update([
            'pers_id_institucion' => $institucionId,
            'pers_num_afiliacion_institucion' => $numero,
        ]);
    }

    /** @param array $datos datos validados; entidad_federativa_id proviene de ValidacionDomicilio (código postal) */
    private function guardarDomicilio(int $personaId, array $datos): void
    {
        $fila = [
            'domi_id_entidad_federativa' => (int) $datos['entidad_federativa_id'],
            'domi_calle' => $datos['calle'],
            'domi_numero_ext' => $datos['numero_exterior'],
            'domi_numero_int' => filled($datos['numero_interior'] ?? null) ? $datos['numero_interior'] : null,
        ];
        $actual = DB::table('domicilio')->where('domi_id_persona', $personaId)->orderByDesc('domi_id_domicilio')->value('domi_id_domicilio');
        $actual
            ? DB::table('domicilio')->where('domi_id_domicilio', $actual)->update($fila)
            : DB::table('domicilio')->insert($fila + ['domi_id_persona' => $personaId]);
    }

    /** Un valor vigente por tipo: actualiza el existente, lo crea o lo elimina si quedó vacío. */
    private function guardarComunicacion(int $personaId, string $tipo, ?string $valor): void
    {
        $tico = Catalogo::tipoComunicacion($tipo);
        $base = DB::table('comunicacion')->where('comu_id_persona', $personaId)->where('comu_id_tipo_comunicacion', $tico);
        $valor = $valor === null ? null : trim($valor);
        if ($valor === null || $valor === '') {
            $base->delete();

            return;
        }
        $id = (clone $base)->orderByDesc('comu_id_comunicacion')->value('comu_id_comunicacion');
        if ($id) {
            (clone $base)->where('comu_id_comunicacion', $id)->update(['comu_valor' => $valor]);
            (clone $base)->where('comu_id_comunicacion', '<>', $id)->delete();

            return;
        }
        DB::table('comunicacion')->insert([
            'comu_id_persona' => $personaId,
            'comu_id_tipo_comunicacion' => $tico,
            'comu_valor' => $valor,
            'comu_visible' => true,
        ]);
    }
}
