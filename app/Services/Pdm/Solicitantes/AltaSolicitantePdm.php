<?php

namespace App\Services\Pdm\Solicitantes;

use App\Models\Pdm\Colonia;
use App\Models\Pdm\Comunicacion;
use App\Models\Pdm\Persona;
use App\Models\Pdm\Rol;
use App\Models\Pdm\TipoComunicacion;
use App\Models\Pdm\Usuario;
use App\Models\User;
use App\Services\Pdm\BasePdm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AltaSolicitantePdm
{
    public function registrar(Request $request): Usuario
    {
        $destino = DB::selectOne('select current_database() as base, host(inet_server_addr()) as direccion');

        if ($destino->base !== DB::connection()->getDatabaseName() || ! BasePdm::descartable($destino->base, $destino->direccion)
            || ! Schema::hasColumn('anfeca_modelo.domicilio', 'domi_id_colonia')) {
            throw ValidationException::withMessages(['colonia' => 'El alta requiere la migración de domicilio autorizada en la base de laboratorio.']);
        }

        $datos = $request->validate([
            'curp' => ['required', 'string', 'size:18', 'regex:/^[a-z]{4}[0-9]{6}[hm][a-z]{5}[a-z0-9][0-9]$/i'],
            'rfc' => ['required', 'string', 'between:12,13'],
            'nombres' => ['required', 'string', 'max:150'],
            'apellido_paterno' => ['required', 'string', 'max:60'],
            'apellido_materno' => ['nullable', 'string', 'max:60'],
            'genero' => ['required', Rule::in(array_keys(config('anfeca_pdm.catalogos.generos')))],
            'cedula_profesional' => ['prohibited'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'calle' => ['required', 'string', 'max:170'],
            'numero_exterior' => ['required', 'string', 'max:6'],
            'numero_interior' => ['nullable', 'string', 'max:4'],
            'codigo_postal' => ['required', 'digits:5'],
            'entidad_federativa' => ['required', 'string', 'max:100'],
            'municipio' => ['required', 'string', 'max:150'],
            'colonia' => ['required', 'string', 'max:150'],
            'correo_electronico' => ['required', 'email', 'max:150'],
            'telefono_particular' => ['nullable', 'required_without:telefono_celular', 'digits:10'],
            'telefono_celular' => ['nullable', 'required_without:telefono_particular', 'digits:10'],
        ]);

        $correo = mb_strtolower($datos['correo_electronico']);
        $curp = strtoupper($datos['curp']);
        $rfc = strtoupper($datos['rfc']);
        $clave = Hash::make($datos['password']);

        return DB::transaction(function () use ($datos, $correo, $curp, $rfc, $clave): Usuario {
            // El DDL oficial no impone unicidad a CURP, RFC o nombre de usuario.
            DB::statement('lock table anfeca_modelo.persona, anfeca_modelo.usuario, anfeca_modelo.comunicacion in share row exclusive mode');
            $this->validarIdentidad($curp, $rfc, $correo);

            $colonias = Colonia::query()->with('alcaldiaMunicipio')
                ->whereRaw('lower(colo_nombre) = lower(?)', [$datos['colonia']])
                ->where('colo_codigo_postal', $datos['codigo_postal'])
                ->whereHas('alcaldiaMunicipio', fn ($q) => $q
                    ->whereRaw('lower(almu_nombre) = lower(?)', [$datos['municipio']])
                    ->whereHas('entidadFederativa', fn ($q) => $q->whereRaw('lower(enfe_nombre) = lower(?)', [$datos['entidad_federativa']])))
                ->limit(2)->get();
            if ($colonias->count() !== 1) {
                throw ValidationException::withMessages(['colonia' => 'La colonia, el código postal, el municipio y la entidad deben corresponder a una ubicación única del catálogo.']);
            }
            $colonia = $colonias->first();

            $roles = Rol::where('rol_nombre', User::ROL_SOLICITANTE)->limit(2)->get();
            abort_unless($roles->count() === 1, 503, 'El rol solicitante falta o es ambiguo en el catálogo.');

            $persona = Persona::create([
                'pers_nombre' => $datos['nombres'],
                'pers_apellido_paterno' => $datos['apellido_paterno'],
                'pers_apellido_materno' => $datos['apellido_materno'] ?? null,
                'pers_curp' => $curp, 'pers_rfc' => $rfc,
                'pers_genero' => config('anfeca_pdm.catalogos.generos')[$datos['genero']],
                'pers_activo' => true,
            ]);
            $domicilio = $persona->domicilios()->make([
                'domi_id_entidad_federativa' => $colonia->alcaldiaMunicipio->almu_id_entidad_federativa,
                'domi_calle' => $datos['calle'], 'domi_numero_ext' => $datos['numero_exterior'],
                'domi_numero_int' => $datos['numero_interior'] ?? null,
            ]);
            $domicilio->domi_id_colonia = $colonia->getKey();
            $domicilio->save();
            foreach (['CORREO' => $correo, 'TEL_PARTICULAR' => $datos['telefono_particular'] ?? null,
                'TEL_CELULAR' => $datos['telefono_celular'] ?? null] as $tipo => $valor) {
                if ($valor !== null) {
                    $tipos = TipoComunicacion::where('tico_tipo_comunicacion', config("anfeca_pdm.catalogos.tipos_comunicacion.{$tipo}"))
                        ->where('tico_estado', 'ACT')->limit(2)->get();
                    abort_unless($tipos->count() === 1, 503, 'Falta un tipo de contacto activo o el catálogo es ambiguo.');
                    $persona->comunicaciones()->create(['comu_id_tipo_comunicacion' => $tipos->first()->getKey(), 'comu_valor' => $valor]);
                }
            }

            return $persona->usuarios()->create([
                'usua_id_rol' => $roles->first()->getKey(), 'usua_nombre' => $correo,
                'usua_contrasenia' => $clave, 'usua_estatus' => Usuario::ESTATUS_ACTIVO,
            ]);
        });
    }

    private function validarIdentidad(string $curp, string $rfc, string $correo): void
    {
        foreach (['curp' => $curp, 'rfc' => $rfc] as $campo => $valor) {
            if (Persona::whereRaw("upper(pers_{$campo}) = ?", [$valor])->exists()) {
                throw ValidationException::withMessages([$campo => 'Estos datos ya están registrados.']);
            }
        }

        $contacto = Comunicacion::whereRaw('lower(comu_valor) = ?', [$correo])
            ->whereHas('tipoComunicacion', fn ($q) => $q->where('tico_tipo_comunicacion', config('anfeca_pdm.catalogos.tipos_comunicacion.CORREO')))
            ->exists();
        if ($contacto || Usuario::whereRaw('lower(usua_nombre) = ?', [$correo])->exists()) {
            throw ValidationException::withMessages(['correo_electronico' => 'Este correo ya está registrado.']);
        }
    }
}
