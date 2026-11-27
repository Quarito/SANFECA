<?php

namespace App\Services\Formacion;

use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * FUENTE ÚNICA de los requisitos de la Formación Académica (etapa 1), según los dos escenarios autorizados:
 *  - Escenario 1 «Solo licenciatura» (cuenta_con_posgrado = FALSE): se exigen únicamente la formación de origen,
 *    la institución y los documentos de licenciatura. El último grado ES la licenciatura (derivado, no se duplica).
 *  - Escenario 2 «Licenciatura con posgrado» (cuenta_con_posgrado = TRUE) y registros sin declaración (NULL,
 *    comportamiento vigente): se exigen además nivel y área del último grado y sus dos documentos.
 * Lo usan el guardado, la autorización, la finalización, el seguimiento y la coordinación (sin reglas repetidas).
 */
class RequisitosFormacion
{
    public const CAMPOS_ORIGEN = ['nivel_origen', 'titulo_origen', 'institucion_afiliada_id'];
    public const CAMPOS_ULTIMO = ['nivel_ultimo_grado', 'titulo_ultimo_grado'];
    public const DOCUMENTOS_ORIGEN = ['titulo_origen_archivo', 'cedula_origen_archivo'];
    public const DOCUMENTOS_ULTIMO = ['titulo_ultimo_grado_archivo', 'cedula_ultimo_grado_archivo'];
    public const NIVELES_POSGRADO = ['Especialidad', 'Maestría', 'Doctorado'];

    private static ?bool $persistencia = null;

    /** ¿Existe la columna cuenta_con_posgrado? (la aplicación funciona sin ella). */
    public static function persistenciaDisponible(): bool
    {
        return self::$persistencia ??= Schema::hasColumn('formaciones_academicas_solicitud', 'cuenta_con_posgrado');
    }

    public static function olvidarPersistencia(): void
    {
        self::$persistencia = null;
    }

    /**
     * ¿La casilla de posgrado rige esta formación? Con la bandera activa (y la columna aplicada) o, aunque la bandera
     * se desactive, cuando la formación YA tiene una declaración guardada: así nunca se obliga a duplicar la
     * licenciatura ni se pierde la declaración al volver al formulario anterior.
     */
    public static function declaracionActiva(mixed $formacion): bool
    {
        return self::persistenciaDisponible()
            && (config('anfeca_catalogos.declaracion_posgrado.activo') === true || self::declaracion($formacion) !== null);
    }

    /** ¿El área de certificación exige posgrado? (normativa citada en config/anfeca_catalogos.php). */
    public static function exigePosgrado(?string $areaCertificacion): bool
    {
        return in_array($areaCertificacion, (array) config('anfeca_catalogos.declaracion_posgrado.areas_que_exigen_posgrado', []), true);
    }

    /**
     * Escenario 1 guardado en un área que EXIGE posgrado. Ocurre si el solicitante cambia el área de certificación
     * después de guardar «solo licenciatura»: la autorización y el envío deben detenerse (DOC-02).
     */
    public static function faltaPosgradoObligatorio(mixed $formacion, ?string $areaCertificacion): bool
    {
        return self::soloLicenciatura($formacion) && self::exigePosgrado($areaCertificacion);
    }

    public static function mensajePosgradoObligatorio(?string $areaCertificacion): string
    {
        return 'Para la certificación en '.$areaCertificacion.' es requisito contar con estudios de posgrado '
            .'(Reglamento para la Certificación Académica, arts. 1 y 13; Instrumento de evaluación, factor 1.1). '
            .'Marque «Cuento con estudios de posgrado concluidos» y registre su último grado.';
    }

    /**
     * Reglas de la casilla y de los datos de posgrado según el escenario (las usa el guardado de la Formación).
     *
     * @param  bool  $sinPosgrado  escenario 1 solicitado en este guardado
     * @param  ?bool  $declarada  declaración ya guardada (null = sin declarar: la casilla es obligatoria)
     */
    public static function reglasDeclaracion(bool $sinPosgrado, ?bool $declarada, ?string $areaCertificacion): array
    {
        $reglas = ['cuenta_con_posgrado' => [
            $declarada === null ? 'required' : 'nullable',
            Rule::in(self::exigePosgrado($areaCertificacion) ? ['1'] : ['0', '1']),
        ]];
        if ($sinPosgrado) {
            // Escenario 1: no se recibe ningún dato ni archivo de posgrado (los guardados antes se conservan).
            foreach ([...self::CAMPOS_ULTIMO, 'titulo_ultimo_grado_otro', 'modo_titulo_ultimo_grado', 'clave_posgrado_catalogo',
                      'titulo_ultimo_grado_manual', ...self::DOCUMENTOS_ULTIMO] as $campo) {
                $reglas[$campo] = ['prohibited'];
            }
        } else {
            // Escenario 2: el último grado SOLO puede ser un posgrado (nunca una segunda licenciatura).
            $reglas['nivel_ultimo_grado'] = ['required', Rule::in(self::NIVELES_POSGRADO)];
        }

        return $reglas;
    }

    public static function mensajesDeclaracion(?string $areaCertificacion): array
    {
        $sinDocumentos = 'No se reciben documentos de posgrado si indicó que solo cuenta con licenciatura.';

        return [
            'cuenta_con_posgrado.required' => 'Indique si cuenta con estudios de posgrado concluidos.',
            'cuenta_con_posgrado.in' => self::mensajePosgradoObligatorio($areaCertificacion),
            'nivel_ultimo_grado.in' => 'El último grado debe ser Especialidad, Maestría o Doctorado. Si solo cuenta con licenciatura, desmarque la casilla «Cuento con estudios de posgrado concluidos».',
            'nivel_ultimo_grado.prohibited' => 'No se reciben datos de posgrado si indicó que solo cuenta con licenciatura.',
            'titulo_ultimo_grado_archivo.prohibited' => $sinDocumentos,
            'cedula_ultimo_grado_archivo.prohibited' => $sinDocumentos,
        ];
    }

    /** Valor normalizado de la declaración: true, false o null (no declarada / histórico). */
    public static function declaracion(mixed $formacion): ?bool
    {
        $v = is_array($formacion) ? ($formacion['cuenta_con_posgrado'] ?? null) : ($formacion?->cuenta_con_posgrado ?? null);
        if ($v === null || $v === '') {
            return null;
        }
        if (is_bool($v)) {
            return $v;
        }

        return in_array(strtolower((string) $v), ['1', 't', 'true'], true);
    }

    /** Escenario 1: el solicitante declaró que solo cuenta con licenciatura. */
    public static function soloLicenciatura(mixed $formacion): bool
    {
        return self::declaracion($formacion) === false;
    }

    public static function campos(mixed $formacion): array
    {
        return self::soloLicenciatura($formacion) ? self::CAMPOS_ORIGEN : [...self::CAMPOS_ORIGEN, ...self::CAMPOS_ULTIMO];
    }

    public static function documentos(mixed $formacion): array
    {
        return self::soloLicenciatura($formacion) ? self::DOCUMENTOS_ORIGEN : [...self::DOCUMENTOS_ORIGEN, ...self::DOCUMENTOS_ULTIMO];
    }

    /** ¿El documento académico aplica a esta formación? (los del último grado no aplican en el escenario 1). */
    public static function documentoAplica(mixed $formacion, string $campo): bool
    {
        return !(in_array($campo, self::DOCUMENTOS_ULTIMO, true) && self::soloLicenciatura($formacion));
    }

    /** Máximo nivel académico vigente (derivado; en el escenario 1 es la licenciatura de origen). */
    public static function maximoNivel(mixed $formacion): ?string
    {
        $v = fn ($k) => is_array($formacion) ? ($formacion[$k] ?? null) : ($formacion?->{$k} ?? null);

        return self::soloLicenciatura($formacion) ? ($v('nivel_origen') ?: 'Licenciatura') : $v('nivel_ultimo_grado');
    }
}
