<?php

namespace App\Services\Pdm;

/**
 * Reglas de normalización de valores de Laravel hacia los catálogos del PDM.
 *
 * Cada método devuelve un ResultadoNormalizacion. Nunca inventa equivalencias:
 * si un valor no coincide con una regla verificable, se marca "revision" y el
 * valor original se conserva intacto para que una persona lo resuelva.
 *
 * Reglas (ver docs/anfeca/DECISIONES_DATOS_F1.md):
 *  N-1 Comparación sin distinguir mayúsculas, acentos ni espacios repetidos.
 *  N-2 Alias explícitos y verificables (config anfeca_pdm.catalogos.alias_entidades).
 *  N-3 Valores conocidos que NO son del catálogo → revisión con motivo.
 *  N-4 Vacío → nulo si la columna PDM lo admite; si es obligatoria → revisión.
 *  N-5 Formatos: número de certificado ^[0-9]{1,7}$, año ^[0-9]{4}$.
 */
class NormalizadorCatalogos
{
    /** N-1: minúsculas, sin acentos, sin espacios sobrantes. */
    public static function clave(?string $valor): string
    {
        $valor = trim(preg_replace('/\s+/u', ' ', (string) $valor));
        $valor = mb_strtolower($valor, 'UTF-8');

        return strtr($valor, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }

    public function entidadFederativa(?string $valor): ResultadoNormalizacion
    {
        $clave = self::clave($valor);

        if ($clave === '') {
            return ResultadoNormalizacion::revision($valor, 'N-4', 'Entidad vacía; DOMI_ID_ENTIDAD_FEDERATIVA es obligatoria.');
        }

        $catalogo = (array) config('anfeca_pdm.catalogos.entidades_federativas');

        foreach ($catalogo as $id => $nombre) {
            if (self::clave($nombre) === $clave) {
                return ResultadoNormalizacion::valido($valor, $nombre, 'N-1', $id);
            }
        }

        $alias = (array) config('anfeca_pdm.catalogos.alias_entidades');

        if (isset($alias[$clave])) {
            $nombre = $alias[$clave];

            return ResultadoNormalizacion::valido($valor, $nombre, 'N-2', array_search($nombre, $catalogo, true));
        }

        $noEntidades = (array) config('anfeca_pdm.catalogos.no_entidades');

        if (isset($noEntidades[$clave])) {
            return ResultadoNormalizacion::revision($valor, 'N-3', $noEntidades[$clave]);
        }

        return ResultadoNormalizacion::revision($valor, 'N-3', 'No coincide con ninguna entidad del catálogo INEGI.');
    }

    public function genero(?string $valor): ResultadoNormalizacion
    {
        if (trim((string) $valor) === '') {
            // PERS_GENERO admite nulo: se conserva vacío, no se supone un género.
            return ResultadoNormalizacion::valido($valor, null, 'N-4');
        }

        foreach ((array) config('anfeca_pdm.catalogos.generos') as $laravel => $pdm) {
            if (self::clave($laravel) === self::clave($valor)) {
                return ResultadoNormalizacion::valido($valor, $pdm, 'N-1');
            }
        }

        return ResultadoNormalizacion::revision($valor, 'N-3', 'Género fuera de los valores admitidos por el formulario.');
    }

    /**
     * Área de certificación o tipo de personal: coincidencia exacta (N-1) con el
     * catálogo que usa la interfaz. Vacío → revisión (columna obligatoria en SOLICITUD).
     */
    public function catalogoExacto(string $catalogo, ?string $valor): ResultadoNormalizacion
    {
        if (trim((string) $valor) === '') {
            return ResultadoNormalizacion::revision(
                $valor,
                'N-4',
                'Valor vacío en columna obligatoria; ver regla N-6 (selección de certificación del mismo usuario y certificación).'
            );
        }

        foreach ((array) config("anfeca_pdm.catalogos.{$catalogo}") as $nombre) {
            if (self::clave($nombre) === self::clave($valor)) {
                return ResultadoNormalizacion::valido($valor, $nombre, 'N-1');
            }
        }

        return ResultadoNormalizacion::revision($valor, 'N-3', "No pertenece al catálogo {$catalogo}.");
    }

    public function numeroCertificado(?string $valor): ResultadoNormalizacion
    {
        $limpio = trim((string) $valor);

        if (preg_match('/^[0-9]{1,7}$/', $limpio)) {
            return ResultadoNormalizacion::valido($valor, $limpio, 'N-5');
        }

        return ResultadoNormalizacion::revision(
            $valor,
            'N-5',
            $limpio === '' ? 'Sin número de certificado.' : 'El número no cumple ^[0-9]{1,7}$ (HICE_FOLIO_CERTIFICADO, 7).'
        );
    }

    public function anio(?string $valor): ResultadoNormalizacion
    {
        $limpio = trim((string) $valor);

        if ($limpio === '') {
            return ResultadoNormalizacion::valido($valor, null, 'N-4');
        }

        if (preg_match('/^[0-9]{4}$/', $limpio)) {
            return ResultadoNormalizacion::valido($valor, $limpio, 'N-5');
        }

        return ResultadoNormalizacion::revision($valor, 'N-5', 'El año no tiene cuatro dígitos.');
    }
}
