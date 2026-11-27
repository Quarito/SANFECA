<?php

namespace App\Services\Catalogos;

use App\Exceptions\Catalogos\CatalogoNoDisponible;
use Illuminate\Support\Facades\Log;
use App\Services\Pdm\Compat\FormacionCatalogoPdm;

/**
 * Catálogo PRELIMINAR de denominaciones de licenciatura (Anuario Estadístico ANUIES), clasificado de forma
 * administrativa en las 5 áreas. Fuente única para el endpoint de búsqueda y las validaciones del servidor.
 * La clasificación NO es equivalencia normativa ni criterio de elegibilidad.
 * El navegador nunca recibe el catálogo completo: solo resultados de búsqueda acotados.
 */
class CatalogoLicenciaturas
{
    public const AREAS = CatalogoPosgrados::AREAS;
    public const PATRON_CLAVE = '/^LIC-[0-9A-F]{12}$/';
    public const COLUMNAS = ['titulo_origen_especifico', 'origen_titulo_origen', 'clave_licenciatura_catalogo', 'version_catalogo_licenciaturas'];

    private ?array $datos = null;
    private ?array $porClave = null;
    /** [[normalizado, clave, titulo], …] ordenado alfabéticamente. */
    private ?array $indice = null;
    private static ?bool $persistencia = null;

    /** @throws CatalogoNoDisponible */
    public function datos(): array
    {
        if ($this->datos !== null) {
            return $this->datos;
        }
        $ruta = (string) config('anfeca_catalogos.licenciaturas.ruta');
        if ($ruta === '' || !is_file($ruta) || !is_readable($ruta)) {
            throw new CatalogoNoDisponible('El catálogo de licenciaturas no existe o no es legible en la ruta configurada.');
        }
        $contenido = (string) file_get_contents($ruta);
        $esperada = config('anfeca_catalogos.licenciaturas.sha256');
        if (filled($esperada) && !hash_equals(strtolower((string) $esperada), hash('sha256', $contenido))) {
            throw new CatalogoNoDisponible('La huella SHA-256 del catálogo de licenciaturas no coincide con la configurada.');
        }
        $json = json_decode($contenido, true);
        if (!is_array($json) || !is_array($json['titulos'] ?? null) || $json['titulos'] === []) {
            throw new CatalogoNoDisponible('El catálogo de licenciaturas está dañado o vacío.');
        }
        if (($json['version'] ?? null) !== config('anfeca_catalogos.licenciaturas.version')) {
            throw new CatalogoNoDisponible('La versión del catálogo de licenciaturas no coincide con la configurada.');
        }
        $claves = [];
        $indice = [];
        foreach ($json['titulos'] as $t) {
            if (!is_string($t['clave'] ?? null) || !preg_match(self::PATRON_CLAVE, $t['clave'])
                || !in_array($t['area'] ?? null, self::AREAS, true)
                || !is_string($t['titulo'] ?? null) || trim($t['titulo']) === '' || mb_strlen($t['titulo']) > 300
                || isset($claves[$t['clave']])) {
                throw new CatalogoNoDisponible('El catálogo de licenciaturas contiene un registro inválido o una clave repetida.');
            }
            $claves[$t['clave']] = $t;
            $indice[] = [CatalogoPosgrados::normalizar($t['titulo']), $t['clave'], $t['titulo']];
        }
        usort($indice, fn ($a, $b) => strcmp($a[0], $b[0]));
        $this->porClave = $claves;
        $this->indice = $indice;

        return $this->datos = $json;
    }

    public function version(): string
    {
        return (string) $this->datos()['version'];
    }

    public static function persistenciaDisponible(): bool
    {
        return self::$persistencia ??= FormacionCatalogoPdm::persistenciaDisponible(self::COLUMNAS);
    }

    public static function olvidarPersistencia(): void
    {
        self::$persistencia = null;
    }

    /** 'desactivado' | 'activo' | 'error' (nunca una lista vacía silenciosa). */
    public function estado(): array
    {
        if (config('anfeca_catalogos.licenciaturas.activo') !== true) {
            return ['estado' => 'desactivado', 'motivo' => null];
        }
        if (!self::persistenciaDisponible()) {
            Log::warning('Catálogo de licenciaturas activado sin la migración aditiva aplicada; se usa el formulario anterior.');

            return ['estado' => 'error', 'motivo' => 'persistencia'];
        }
        try {
            $this->datos();
        } catch (CatalogoNoDisponible $e) {
            Log::error('Catálogo de licenciaturas no disponible: '.$e->getMessage());

            return ['estado' => 'error', 'motivo' => 'catalogo'];
        }

        return ['estado' => 'activo', 'motivo' => null, 'version' => $this->version()];
    }

    public function activo(): bool
    {
        return $this->estado()['estado'] === 'activo';
    }

    public function porClave(?string $clave): ?array
    {
        if (!is_string($clave) || !preg_match(self::PATRON_CLAVE, $clave)) {
            return null;
        }
        $this->datos();

        return $this->porClave[$clave] ?? null;
    }

    /**
     * Búsqueda en todas las áreas o en un área específica, parcial, por palabras, sin acentos ni mayúsculas, en
     * orden alfabético. Devuelve el total de coincidencias para que la interfaz pida afinar la búsqueda cuando se
     * supera el límite: ningún título queda inaccesible (basta escribir más palabras).
     */
    public function buscar(string $busqueda, ?int $limite = null, ?string $area = null): array
    {
        $this->datos();
        $palabras = array_values(array_filter(explode(' ', CatalogoPosgrados::normalizar($busqueda))));
        $limite ??= (int) config('anfeca_catalogos.licenciaturas.maximo_resultados', 60);
        $total = 0;
        $titulos = [];
        foreach ($this->indice as [$n, $clave, $titulo]) {
            if ($area !== null && ($this->porClave[$clave]['area'] ?? null) !== $area) {
                continue;
            }
            foreach ($palabras as $p) {
                if (!str_contains($n, $p)) {
                    continue 2;
                }
            }
            $total++;
            if (count($titulos) < $limite) {
                $titulos[] = ['clave' => $clave, 'titulo' => $titulo];
            }
        }

        return ['total' => $total, 'titulos' => $titulos];
    }
}
