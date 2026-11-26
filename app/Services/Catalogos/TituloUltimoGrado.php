<?php

namespace App\Services\Catalogos;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Título específico del último grado (catálogo de posgrados).
 *
 * Significado de los datos (alternativa A aprobada):
 *  - nivel_ultimo_grado            → nivel (sin cambios)
 *  - titulo_ultimo_grado           → ÁREA académica (sin cambios)
 *  - titulo_ultimo_grado_otro      → título libre del área «Otro» (sin cambios; con el catálogo se refleja
 *                                     ahí el título del área Otro para el formulario anterior y las consultas)
 *  - titulo_ultimo_grado_especifico, origen_titulo_ultimo_grado ('catalogo'|'manual'),
 *    clave_posgrado_catalogo, version_catalogo_posgrados → columnas ADITIVAS
 * La clave y la versión solo existen para selecciones reales del catálogo (nunca claves ficticias).
 */
class TituloUltimoGrado
{
    public function __construct(private readonly CatalogoPosgrados $catalogo)
    {
    }

    private function usaCatalogo(Request $request): bool
    {
        return $this->catalogo->activo() && $request->input('nivel_ultimo_grado') !== 'Licenciatura';
    }

    /** Reglas adicionales o sustitutas cuando el catálogo está activo (Licenciatura conserva las actuales). */
    public function reglas(Request $request): array
    {
        if (!$this->usaCatalogo($request)) {
            return [];
        }
        $max = (int) config('anfeca_catalogos.posgrados.titulo_manual_maximo', 200);
        $min = (int) config('anfeca_catalogos.posgrados.titulo_manual_minimo', 5);

        return [
            // El título libre ya no se recibe del navegador: el servidor lo deriva (área Otro).
            'titulo_ultimo_grado_otro' => ['prohibited'],
            'modo_titulo_ultimo_grado' => ['required', Rule::in(['catalogo', 'manual'])],
            'clave_posgrado_catalogo' => ['required_if:modo_titulo_ultimo_grado,catalogo', 'prohibited_unless:modo_titulo_ultimo_grado,catalogo',
                'nullable', 'string', 'regex:/^(ESP|MAE|DOC)-[0-9A-F]{12}$/'],
            'titulo_ultimo_grado_manual' => ['required_if:modo_titulo_ultimo_grado,manual', 'prohibited_unless:modo_titulo_ultimo_grado,manual',
                'nullable', 'string', "min:{$min}", "max:{$max}", 'not_regex:/[<>]/'],
        ];
    }

    public function mensajes(): array
    {
        return [
            'clave_posgrado_catalogo.required_if' => 'Seleccione el nombre del título o elija «No encuentro mi título».',
            'clave_posgrado_catalogo.regex' => 'El título seleccionado no es válido.',
            'titulo_ultimo_grado_manual.required_if' => 'Escriba el nombre de su título.',
            'titulo_ultimo_grado_otro.prohibited' => 'Dato no permitido.',
        ];
    }

    /**
     * Calcula las columnas del último grado a partir de lo VALIDADO y del catálogo del servidor
     * (el nombre enviado por el navegador nunca se usa). Devuelve los datos a guardar.
     *
     * @throws ValidationException
     */
    public function aplicar(Request $request, array $datos, ?object $formacion): array
    {
        $persistencia = CatalogoPosgrados::persistenciaDisponible();

        if (!$this->usaCatalogo($request)) {
            unset($datos['modo_titulo_ultimo_grado'], $datos['clave_posgrado_catalogo'], $datos['titulo_ultimo_grado_manual']);
            if ($this->catalogo->activo() && $persistencia) {
                // Licenciatura con el catálogo activo: no hay título específico de posgrado.
                return $datos + array_fill_keys(CatalogoPosgrados::COLUMNAS, null);
            }

            return $persistencia ? $this->protegerSinCatalogo($datos, $formacion) : $datos;
        }

        $modo = $datos['modo_titulo_ultimo_grado'];
        if ($modo === 'catalogo' && $this->sinCambios($datos, $formacion)) {
            // Selección guardada SIN cambios (misma clave, nivel y área): se conserva tal como se registró, con su
            // título y su versión de origen, aunque la versión vigente la haya retirado, renombrado o reclasificado.
            $columnas = ['titulo_ultimo_grado_especifico' => $formacion->titulo_ultimo_grado_especifico, 'origen_titulo_ultimo_grado' => 'catalogo',
                'clave_posgrado_catalogo' => $formacion->clave_posgrado_catalogo, 'version_catalogo_posgrados' => $formacion->version_catalogo_posgrados];
        } elseif ($modo === 'catalogo') {
            $t = $this->catalogo->validar($datos['clave_posgrado_catalogo'] ?? null, $datos['nivel_ultimo_grado'], $datos['titulo_ultimo_grado']);
            if ($t === null) {
                throw ValidationException::withMessages(['clave_posgrado_catalogo' => 'El título no corresponde al nivel y al área seleccionados.']);
            }
            $columnas = ['titulo_ultimo_grado_especifico' => $t['titulo'], 'origen_titulo_ultimo_grado' => 'catalogo',
                'clave_posgrado_catalogo' => $t['clave'], 'version_catalogo_posgrados' => $this->catalogo->version()];
        } else {
            $columnas = ['titulo_ultimo_grado_especifico' => trim($datos['titulo_ultimo_grado_manual']), 'origen_titulo_ultimo_grado' => 'manual',
                'clave_posgrado_catalogo' => null, 'version_catalogo_posgrados' => null];
        }
        unset($datos['modo_titulo_ultimo_grado'], $datos['clave_posgrado_catalogo'], $datos['titulo_ultimo_grado_manual']);
        // Área «Otro»: se refleja el título en el campo existente (≤ 200) para el formulario anterior y las consultas.
        $datos['titulo_ultimo_grado_otro'] = $datos['titulo_ultimo_grado'] === 'Otro'
            ? mb_substr($columnas['titulo_ultimo_grado_especifico'], 0, 200) : null;

        return $datos + $columnas;
    }

    /** ¿La selección enviada es exactamente la guardada (clave, nivel y área) y proviene del catálogo? */
    private function sinCambios(array $datos, ?object $formacion): bool
    {
        return $formacion !== null
            && ($formacion->origen_titulo_ultimo_grado ?? null) === 'catalogo'
            && filled($formacion->clave_posgrado_catalogo ?? null)
            && ($datos['clave_posgrado_catalogo'] ?? null) === $formacion->clave_posgrado_catalogo
            && ($datos['nivel_ultimo_grado'] ?? null) === $formacion->nivel_ultimo_grado
            && ($datos['titulo_ultimo_grado'] ?? null) === $formacion->titulo_ultimo_grado;
    }

    /**
     * Formulario ANTERIOR (bandera desactivada) sobre un registro con título específico: no se permite
     * que quede asociado a un nivel o área incompatibles ni se pierde en silencio.
     *  - Cambiar nivel o área → se rechaza con un mensaje explícito (nada cambia: reversible).
     *  - Título de catálogo del área Otro → su texto no se modifica desde el formulario anterior.
     *  - Título manual del área Otro → se mantiene sincronizado con titulo_ultimo_grado_otro.
     *
     * @throws ValidationException
     */
    public function protegerSinCatalogo(array $datos, ?object $formacion): array
    {
        if ($formacion === null || blank($formacion->origen_titulo_ultimo_grado ?? null)) {
            return $datos;
        }
        $mensaje = 'Su último grado tiene registrado el título «'.$formacion->titulo_ultimo_grado_especifico.'». '
            .'Para cambiar el nivel o el área solicite apoyo a la coordinación; puede guardar el resto de la información.';
        if (($datos['nivel_ultimo_grado'] ?? null) !== $formacion->nivel_ultimo_grado
            || ($datos['titulo_ultimo_grado'] ?? null) !== $formacion->titulo_ultimo_grado) {
            throw ValidationException::withMessages(['nivel_ultimo_grado' => $mensaje]);
        }
        if ($formacion->titulo_ultimo_grado === 'Otro') {
            $nuevo = trim((string) ($datos['titulo_ultimo_grado_otro'] ?? ''));
            if ($formacion->origen_titulo_ultimo_grado === 'catalogo' && $nuevo !== (string) $formacion->titulo_ultimo_grado_otro) {
                throw ValidationException::withMessages(['titulo_ultimo_grado_otro' => 'El título proviene del catálogo de posgrados y no puede modificarse aquí.']);
            }
            if ($formacion->origen_titulo_ultimo_grado === 'manual' && $nuevo !== '') {
                $datos['titulo_ultimo_grado_especifico'] = $nuevo;
            }
        }

        return $datos;
    }

    /** Texto a mostrar: título exacto si existe; si no, el valor histórico original (Otro → título libre). */
    public static function presentar(mixed $f): string
    {
        $v = fn ($k) => is_array($f) ? ($f[$k] ?? null) : ($f?->{$k} ?? null);
        if (filled($v('titulo_ultimo_grado_especifico'))) {
            return (string) $v('titulo_ultimo_grado_especifico');
        }
        if ($v('titulo_ultimo_grado') === 'Otro' && filled($v('titulo_ultimo_grado_otro'))) {
            return (string) $v('titulo_ultimo_grado_otro');
        }

        return (string) ($v('titulo_ultimo_grado') ?: '—');
    }
}
