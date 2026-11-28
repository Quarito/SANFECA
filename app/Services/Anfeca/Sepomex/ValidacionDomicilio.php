<?php

namespace App\Services\Anfeca\Sepomex;

use App\Services\Anfeca\Catalogo;
use Illuminate\Validation\ValidationException;

/**
 * Validación de CAPTURA del domicilio contra el catálogo SEPOMEX (COLONIA → ALCALDIA_MUNICIPIO → ENTIDAD_FEDERATIVA).
 * Comprueba que el código postal exista y que la colonia, la alcaldía o municipio y la entidad capturados le
 * correspondan. No persiste nada: devuelve la ENTIDAD_FEDERATIVA que se guardará en DOMICILIO.
 * Código postal, colonia y municipio no se guardan: DOMICILIO no tiene esos atributos ni relación con COLONIA.
 */
final class ValidacionDomicilio
{
    public function __construct(private readonly ConsultaCodigoPostal $consulta)
    {
    }

    /** @return array{entidad_id:int, entidad:string, municipio:string, colonia:string} */
    public function validar(array $datos): array
    {
        $cp = (string) ($datos['codigo_postal'] ?? '');
        $resultado = $this->consulta->consultar($cp);
        if ($resultado === null) {
            throw ValidationException::withMessages(['codigo_postal' => 'El código postal no se encuentra en el catálogo nacional de Correos de México.']);
        }
        $municipio = collect($resultado['municipios'])->first(fn ($m) => Catalogo::clave($m['nombre']) === Catalogo::clave($datos['municipio'] ?? ''));
        if ($municipio === null) {
            throw ValidationException::withMessages(['municipio' => 'La alcaldía o municipio no corresponde al código postal '.$cp.' ('
                .collect($resultado['municipios'])->pluck('nombre')->implode(', ').').']);
        }
        if (Catalogo::clave($municipio['entidad_federativa']['nombre']) !== Catalogo::clave($datos['entidad_federativa'] ?? '')) {
            throw ValidationException::withMessages(['entidad_federativa' => 'La entidad federativa no corresponde al código postal '.$cp.' ('
                .$municipio['entidad_federativa']['nombre'].').']);
        }
        $colonia = collect($municipio['colonias'])->first(fn ($c) => Catalogo::clave($c['nombre']) === Catalogo::clave($datos['colonia'] ?? ''));
        if ($colonia === null) {
            throw ValidationException::withMessages(['colonia' => 'La colonia no corresponde al código postal '.$cp.'. Seleccione una de las colonias registradas para ese código.']);
        }

        return ['entidad_id' => $municipio['entidad_federativa']['id'], 'entidad' => $municipio['entidad_federativa']['nombre'],
            'municipio' => $municipio['nombre'], 'colonia' => $colonia['nombre']];
    }
}
