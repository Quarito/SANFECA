<?php

namespace App\Services\Catalogos;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Nombre exacto del título de la Formación Académica de Origen (licenciatura) con el catálogo ANUIES.
 *
 * Significado de los datos (sin cambiar los campos históricos):
 *  - nivel_origen          → «Licenciatura» (fijo)
 *  - titulo_origen         → ÁREA del título, validada contra el área de certificación (config/anfeca_equivalencias.php,
 *                            regla vigente SIN cambios)
 *  - titulo_origen_otro    → texto del título cuando el área es «Otro» (se refleja el nombre exacto, ≤ 200)
 *  - titulo_origen_especifico, origen_titulo_origen ('catalogo'|'manual'), clave_licenciatura_catalogo,
 *    version_catalogo_licenciaturas → columnas ADITIVAS internas (no visibles como campos nuevos)
 *
 * Reglas:
 *  - Catálogo: el área se DERIVA de la clasificación preliminar del título elegido (nunca la envía el navegador).
 *    Si esa área no es admitida por la certificación solicitada, NO se guarda un área distinta para «satisfacer» la
 *    validación: se detiene con una explicación (decisión D-LIC-1 pendiente; ver PROPUESTA_CATALOGO_LICENCIATURAS.md).
 *  - Captura manual: el título no tiene clasificación de catálogo; no se inventa ninguna. El área registrada es la
 *    ÚNICA que admite la certificación (mismo comportamiento que el selector vigente de una opción: declaración del
 *    solicitante) y el origen «manual» la deja sujeta a revisión de procedencia.
 *  - Refrendo con área histórica registrada: no se aplica (se conserva el antecedente; el formulario no cambia).
 */
class TituloOrigen
{
    public function __construct(private readonly CatalogoLicenciaturas $catalogo)
    {
    }

    /** ¿El formulario de origen usa el catálogo para esta solicitud? */
    public function usaCatalogo(object $solicitud, ?object $formacion): bool
    {
        $historicoRefrendo = $solicitud->tipo_tramite === 'refrendo' && filled($formacion?->titulo_origen);

        return !$historicoRefrendo && $this->catalogo->activo();
    }

    public function reglas(object $solicitud, ?object $formacion): array
    {
        if (!$this->usaCatalogo($solicitud, $formacion)) {
            return [];
        }
        $max = (int) config('anfeca_catalogos.licenciaturas.titulo_manual_maximo', 200);
        $min = (int) config('anfeca_catalogos.licenciaturas.titulo_manual_minimo', 5);

        return [
            // El área y el texto libre los deriva el servidor.
            'titulo_origen' => ['prohibited'],
            'titulo_origen_otro' => ['prohibited'],
            'modo_titulo_origen' => ['required', Rule::in(['catalogo', 'manual'])],
            'clave_licenciatura_catalogo' => ['required_if:modo_titulo_origen,catalogo', 'prohibited_unless:modo_titulo_origen,catalogo',
                'nullable', 'string', 'regex:'.CatalogoLicenciaturas::PATRON_CLAVE],
            'titulo_origen_manual' => ['required_if:modo_titulo_origen,manual', 'prohibited_unless:modo_titulo_origen,manual',
                'nullable', 'string', "min:{$min}", "max:{$max}", 'not_regex:/[<>]/'],
        ];
    }

    public function mensajes(): array
    {
        return [
            'modo_titulo_origen.required' => 'Seleccione el nombre de su título de licenciatura o elija «No encuentro mi título».',
            'clave_licenciatura_catalogo.required_if' => 'Seleccione el nombre de su título de licenciatura o elija «No encuentro mi título».',
            'clave_licenciatura_catalogo.regex' => 'El título de licenciatura seleccionado no es válido.',
            'titulo_origen_manual.required_if' => 'Escriba el nombre de su título de licenciatura.',
            'titulo_origen_manual.min' => 'Escriba el nombre completo de su título de licenciatura.',
            'titulo_origen.prohibited' => 'Dato no permitido.',
            'titulo_origen_otro.prohibited' => 'Dato no permitido.',
        ];
    }

    /**
     * Calcula las columnas de origen a partir de lo VALIDADO y del catálogo del servidor.
     *
     * @throws ValidationException
     */
    public function aplicar(array $datos, object $solicitud, ?object $formacion): array
    {
        $persistencia = CatalogoLicenciaturas::persistenciaDisponible();
        if (!$this->usaCatalogo($solicitud, $formacion)) {
            unset($datos['modo_titulo_origen'], $datos['clave_licenciatura_catalogo'], $datos['titulo_origen_manual']);

            return $persistencia ? $this->protegerSinCatalogo($datos, $formacion) : $datos;
        }
        $admitidas = (array) config('anfeca_equivalencias.areas.'.$solicitud->area_certificacion, []);

        if ($datos['modo_titulo_origen'] === 'catalogo') {
            $clave = $datos['clave_licenciatura_catalogo'] ?? null;
            if ($formacion !== null && ($formacion->origen_titulo_origen ?? null) === 'catalogo' && $clave === $formacion->clave_licenciatura_catalogo
                && in_array($formacion->titulo_origen, $admitidas, true)) {
                // Selección guardada sin cambios: se conserva como se registró (título, área y versión de origen).
                $area = $formacion->titulo_origen;
                $columnas = ['titulo_origen_especifico' => $formacion->titulo_origen_especifico, 'origen_titulo_origen' => 'catalogo',
                    'clave_licenciatura_catalogo' => $clave, 'version_catalogo_licenciaturas' => $formacion->version_catalogo_licenciaturas];
            } else {
                $t = $this->catalogo->porClave($clave);
                if ($t === null) {
                    throw ValidationException::withMessages(['clave_licenciatura_catalogo' => 'El título de licenciatura seleccionado no existe en el catálogo vigente.']);
                }
                if (!in_array($t['area'], $admitidas, true)) {
                    throw ValidationException::withMessages(['clave_licenciatura_catalogo' => sprintf(
                        'En el catálogo, «%s» está clasificado de forma preliminar en el área %s; la certificación en %s admite títulos del área %s. '
                        .'Revise el área de certificación elegida o, si considera que la clasificación no corresponde, elija «No encuentro mi título» '
                        .'y escriba el nombre: la coordinación revisará su procedencia.',
                        $t['titulo'], $t['area'], $solicitud->area_certificacion, implode(', ', $admitidas))]);
                }
                $area = $t['area'];
                $columnas = ['titulo_origen_especifico' => $t['titulo'], 'origen_titulo_origen' => 'catalogo',
                    'clave_licenciatura_catalogo' => $t['clave'], 'version_catalogo_licenciaturas' => $this->catalogo->version()];
            }
        } else {
            if (count($admitidas) !== 1) {
                throw ValidationException::withMessages(['titulo_origen_manual' => 'La configuración del área de certificación no permite registrar la captura manual. Solicite apoyo a la coordinación.']);
            }
            $area = $admitidas[0];
            $columnas = ['titulo_origen_especifico' => trim($datos['titulo_origen_manual']), 'origen_titulo_origen' => 'manual',
                'clave_licenciatura_catalogo' => null, 'version_catalogo_licenciaturas' => null];
        }
        unset($datos['modo_titulo_origen'], $datos['clave_licenciatura_catalogo'], $datos['titulo_origen_manual']);
        $datos['titulo_origen'] = $area;
        $datos['titulo_origen_otro'] = $area === 'Otro' ? mb_substr($columnas['titulo_origen_especifico'], 0, 200) : null;

        return $datos + $columnas;
    }

    /**
     * Formulario ANTERIOR (bandera de licenciaturas desactivada) sobre un registro con título específico: no se
     * permite cambiar el área ni perder el título en silencio; un título manual del área «Otro» se mantiene sincronizado.
     *
     * @throws ValidationException
     */
    public function protegerSinCatalogo(array $datos, ?object $formacion): array
    {
        if ($formacion === null || blank($formacion->origen_titulo_origen ?? null) || !array_key_exists('titulo_origen', $datos)) {
            return $datos;
        }
        if (($datos['titulo_origen'] ?? null) !== $formacion->titulo_origen) {
            throw ValidationException::withMessages(['titulo_origen' => 'Su formación de origen tiene registrado el título «'
                .$formacion->titulo_origen_especifico.'». Para cambiarlo solicite apoyo a la coordinación; puede guardar el resto de la información.']);
        }
        if ($formacion->titulo_origen === 'Otro') {
            $nuevo = trim((string) ($datos['titulo_origen_otro'] ?? ''));
            if ($formacion->origen_titulo_origen === 'catalogo' && $nuevo !== (string) $formacion->titulo_origen_otro) {
                throw ValidationException::withMessages(['titulo_origen_otro' => 'El título proviene del catálogo de licenciaturas y no puede modificarse aquí.']);
            }
            if ($formacion->origen_titulo_origen === 'manual' && $nuevo !== '') {
                $datos['titulo_origen_especifico'] = $nuevo;
            }
        }

        return $datos;
    }

    /** Texto a mostrar: nombre exacto si existe; si no, el valor histórico (Otro → texto libre; si no, el área). */
    public static function presentar(mixed $f): string
    {
        $v = fn ($k) => is_array($f) ? ($f[$k] ?? null) : ($f?->{$k} ?? null);
        if (filled($v('titulo_origen_especifico'))) {
            return (string) $v('titulo_origen_especifico');
        }
        if ($v('titulo_origen') === 'Otro' && filled($v('titulo_origen_otro'))) {
            return (string) $v('titulo_origen_otro');
        }

        return (string) ($v('titulo_origen') ?: '—');
    }
}
