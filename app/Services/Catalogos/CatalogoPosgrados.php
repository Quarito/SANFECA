<?php

namespace App\Services\Catalogos;

use App\Exceptions\Catalogos\CatalogoNoDisponible;
use Illuminate\Support\Facades\Log;
use App\Services\Pdm\Compat\FormacionCatalogoPdm;
use Illuminate\Support\Str;

/**
 * Catálogo de posgrados (ANUIES 2025) clasificado administrativamente en 5 áreas.
 * FUENTE ÚNICA para Vue (vía endpoint) y para las validaciones del servidor.
 * La clasificación es administrativa (facilita la captura): no es equivalencia académica ni elegibilidad.
 */
class CatalogoPosgrados
{
    public const NIVELES = ['Especialidad', 'Maestría', 'Doctorado'];
    public const AREAS = ['Administración', 'Contaduría', 'Informática', 'Negocios Internacionales', 'Otro'];
    /** Columnas aditivas (migración propuesta 2026_11_27_000000). */
    public const COLUMNAS = ['titulo_ultimo_grado_especifico', 'origen_titulo_ultimo_grado', 'clave_posgrado_catalogo', 'version_catalogo_posgrados'];

    private ?array $datos = null;
    private ?array $porClave = null;
    private static ?bool $persistencia = null;

    /** Datos validados del catálogo. @throws CatalogoNoDisponible */
    public function datos(): array
    {
        if ($this->datos !== null) {
            return $this->datos;
        }
        $ruta = (string) config('anfeca_catalogos.posgrados.ruta');
        if ($ruta === '' || !is_file($ruta) || !is_readable($ruta)) {
            throw new CatalogoNoDisponible('El catálogo de posgrados no existe o no es legible en la ruta configurada.');
        }
        $contenido = (string) file_get_contents($ruta);
        $esperada = config('anfeca_catalogos.posgrados.sha256');
        if (filled($esperada) && !hash_equals(strtolower((string) $esperada), hash('sha256', $contenido))) {
            throw new CatalogoNoDisponible('La huella SHA-256 del catálogo no coincide con la configurada.');
        }
        $json = json_decode($contenido, true);
        if (!is_array($json) || !is_array($json['titulos'] ?? null) || $json['titulos'] === []) {
            throw new CatalogoNoDisponible('El catálogo de posgrados está dañado o vacío.');
        }
        if (($json['version'] ?? null) !== config('anfeca_catalogos.posgrados.version')) {
            throw new CatalogoNoDisponible('La versión del catálogo no coincide con la configurada.');
        }
        $claves = [];
        foreach ($json['titulos'] as $t) {
            if (!is_string($t['clave'] ?? null) || !preg_match('/^(ESP|MAE|DOC)-[0-9A-F]{12}$/', $t['clave'])
                || !in_array($t['nivel'] ?? null, self::NIVELES, true) || !in_array($t['area'] ?? null, self::AREAS, true)
                || !is_string($t['titulo'] ?? null) || trim($t['titulo']) === '' || mb_strlen($t['titulo']) > 300
                || isset($claves[$t['clave']])) {
                throw new CatalogoNoDisponible('El catálogo contiene un registro inválido o una clave repetida.');
            }
            $claves[$t['clave']] = $t;
        }
        $this->porClave = $claves;

        return $this->datos = $json;
    }

    public function version(): string
    {
        return (string) $this->datos()['version'];
    }

    public function disponible(): bool
    {
        try {
            $this->datos();

            return true;
        } catch (CatalogoNoDisponible $e) {
            return false;
        }
    }

    /** ¿Existen las columnas aditivas? (la aplicación funciona sin ellas con la bandera desactivada). */
    public static function persistenciaDisponible(): bool
    {
        return self::$persistencia ??= FormacionCatalogoPdm::persistenciaDisponible(self::COLUMNAS);
    }

    public static function olvidarPersistencia(): void
    {
        self::$persistencia = null;
    }

    /**
     * Estado efectivo: 'desactivado' | 'activo' | 'error'. Con la bandera activa y algún requisito ausente
     * se registra el motivo y se usa el formulario anterior con aviso (nunca un catálogo vacío silencioso).
     */
    public function estado(): array
    {
        if (config('anfeca_catalogos.posgrados.activo') !== true) {
            return ['estado' => 'desactivado', 'motivo' => null];
        }
        if (!self::persistenciaDisponible()) {
            Log::warning('Catálogo de posgrados activado sin la migración aditiva aplicada; se usa el formulario anterior.');

            return ['estado' => 'error', 'motivo' => 'persistencia'];
        }
        try {
            $this->datos();
        } catch (CatalogoNoDisponible $e) {
            Log::error('Catálogo de posgrados no disponible: '.$e->getMessage());

            return ['estado' => 'error', 'motivo' => 'catalogo'];
        }

        return ['estado' => 'activo', 'motivo' => null, 'version' => $this->version()];
    }

    public function activo(): bool
    {
        return $this->estado()['estado'] === 'activo';
    }

    /** Título por clave, o null. */
    public function porClave(string $clave): ?array
    {
        $this->datos();

        return $this->porClave[$clave] ?? null;
    }

    /** Valida que la clave exista y corresponda al nivel y al área. Devuelve el registro o null. */
    public function validar(?string $clave, ?string $nivel, ?string $area): ?array
    {
        $t = is_string($clave) ? $this->porClave($clave) : null;

        return $t !== null && $t['nivel'] === $nivel && $t['area'] === $area ? $t : null;
    }

    /** Títulos por nivel y área, en orden alfabético; búsqueda opcional sin acentos ni mayúsculas, por palabras. */
    public function titulos(string $nivel, string $area, ?string $busqueda = null, ?int $limite = null): array
    {
        $palabras = array_filter(explode(' ', self::normalizar((string) $busqueda)));
        $resultado = collect($this->datos()['titulos'])
            ->filter(fn ($t) => $t['nivel'] === $nivel && $t['area'] === $area)
            ->filter(function ($t) use ($palabras) {
                $n = self::normalizar($t['titulo']);

                return collect($palabras)->every(fn ($p) => str_contains($n, $p));
            })
            ->sortBy(fn ($t) => self::normalizar($t['titulo']))
            ->values()
            ->map(fn ($t) => ['clave' => $t['clave'], 'titulo' => $t['titulo']]);

        return $resultado->take($limite ?? (int) config('anfeca_catalogos.posgrados.maximo_resultados', 400))->all();
    }

    public static function normalizar(string $texto): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($texto)))));
    }
}
