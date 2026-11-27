<?php

namespace App\Services\Pdm\Solicitantes;

use App\Models\Pdm\Colonia;
use App\Models\Pdm\Usuario;

final class ConsultaPersonalPdm
{
    public function consultar(Usuario $usuario): object
    {
        $persona = $usuario->persona;
        $domicilios = $persona->domicilios()->with('entidadFederativa')->limit(2)->get();
        $domicilio = $domicilios->count() === 1 ? $domicilios->first() : null;
        $colonia = $domicilio?->domi_id_colonia
            ? Colonia::with('alcaldiaMunicipio')->find($domicilio->domi_id_colonia)
            : null;
        if ($colonia && $colonia->alcaldiaMunicipio->almu_id_entidad_federativa !== $domicilio->domi_id_entidad_federativa) {
            $colonia = null;
        }
        $contactos = $persona->comunicaciones()->with('tipoComunicacion')->get()
            ->filter(fn ($fila) => $fila->tipoComunicacion->tico_estado === 'ACT')
            ->groupBy(fn ($fila) => $fila->tipoComunicacion->tico_tipo_comunicacion);

        $contacto = function (string $clave) use ($contactos): ?string {
            $tipo = config('anfeca_pdm.catalogos.tipos_comunicacion.'.$clave);
            $valores = $contactos->get($tipo, collect())->pluck('comu_valor')->unique();

            return $valores->count() === 1 ? $valores->first() : null;
        };

        return (object) [
            'curp' => $persona->pers_curp,
            'rfc' => $persona->pers_rfc,
            'nombres' => $persona->pers_nombre,
            'apellido_paterno' => $persona->pers_apellido_paterno,
            'apellido_materno' => $persona->pers_apellido_materno,
            'genero' => match ($persona->pers_genero) {
                'F' => 'Femenino',
                'M' => 'Masculino',
                default => null,
            },
            'calle' => $domicilio?->domi_calle,
            'numero_exterior' => $domicilio?->domi_numero_ext,
            'numero_interior' => $domicilio?->domi_numero_int,
            'entidad_federativa' => $domicilio?->entidadFederativa->enfe_nombre,
            'codigo_postal' => $colonia?->colo_codigo_postal,
            'colonia' => $colonia?->colo_nombre,
            'municipio' => $colonia?->alcaldiaMunicipio->almu_nombre,
            'correo_electronico' => $contacto('CORREO'),
            'telefono_particular' => $contacto('TEL_PARTICULAR'),
            'telefono_celular' => $contacto('TEL_CELULAR'),
            'datos_autorizados' => null,
        ];
    }
}
