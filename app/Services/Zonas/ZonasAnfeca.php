<?php

namespace App\Services\Zonas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Resolución CENTRALIZADA de la zona ANFECA de una institución afiliada.
 *
 * Prioridad:
 *  1. zona_id con registro válido en `zonas` (relación normalizada).
 *  2. instituciones_afiliadas.zona_anfeca (dato institucional ya cargado), interpretado contra las
 *     7 zonas oficiales (config/anfeca_zonas.php). Solo número y nombre coherentes se aceptan.
 *  3. «Sin clasificar».
 * Contradicciones (zona_id ≠ zona_anfeca, número ≠ nombre) se marcan y se reportan.
 * Además se advierte si la entidad de la institución pertenece a otra zona oficial.
 *
 * La autorización regional depende del ROL de la cuenta (privilegio alcance_zona_N, ver AlcanceZona);
 * este servicio solo ubica cada institución en su zona.
 */
class ZonasAnfeca
{
    /** @var array<int, ResolucionZona>|null caché por petición: institucion_id => resolución */
    private ?array $mapa = null;

    /** @return array<int, array{romano:string,nombre:string,entidades:list<string>}> */
    public function oficiales(): array
    {
        return config('anfeca_zonas.zonas', []);
    }

    public function etiquetaOficial(int $numero): string
    {
        return "Zona {$numero} - ".$this->oficiales()[$numero]['nombre'];
    }

    /** Interpreta un texto («Zona 6 - Sur», «zona vi sur», «Z6»). Devuelve [número|null, contradicción]. */
    public function interpretar(?string $texto): array
    {
        $t = Str::of((string) $texto)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->value();
        if ($t === '') {
            return [null, false];
        }

        $numero = null;
        if (preg_match('/^(?:zona|z)\s*([1-7])(?!\d)/', $t, $m)) {
            $numero = (int) $m[1];
        } elseif (preg_match('/^zona\s+(vii|vi|v|iv|iii|ii|i)\b/', $t, $m)) {
            $numero = ['i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5, 'vi' => 6, 'vii' => 7][$m[1]];
        }

        // Nombre oficial presente en el texto (el más largo primero: «centro occidente» antes que «centro»).
        $nombres = collect($this->oficiales())
            ->map(fn ($z) => Str::of($z['nombre'])->ascii()->lower()->value())
            ->sortByDesc(fn ($n) => strlen($n));
        $porNombre = null;
        $resto = preg_replace('/^(?:zona|z)\s*(?:[1-7]|vii|vi|v|iv|iii|ii|i)\b/', '', $t);
        foreach ($nombres as $num => $nombre) {
            if (preg_match('/(^|\s)'.preg_quote($nombre, '/').'(\s|$)/', trim($resto))) {
                $porNombre = $num;
                break;
            }
        }

        if ($numero !== null && $porNombre !== null && $numero !== $porNombre) {
            return [null, true];                                   // número y nombre se contradicen
        }
        if ($numero !== null) {
            return [$numero, false];
        }
        // Sin número: solo se acepta el nombre oficial EXACTO («Sur», «Zona Centro Occidente»).
        $soloNombre = trim(preg_replace('/^zona\s+/', '', trim($resto)));
        $exacto = $nombres->search($soloNombre, true);

        return [$exacto === false ? null : (int) $exacto, false];
    }

    /** Resuelve una institución a partir de sus datos (sin consultar la base). */
    public function resolver(?int $zonaId, ?string $catalogoSiglas, ?string $catalogoDescripcion, ?string $zonaAnfeca, ?string $entidad = null): ResolucionZona
    {
        $advertencias = [];
        [$numTexto, $contradiceTexto] = $this->interpretar($zonaAnfeca);
        if ($contradiceTexto) {
            $advertencias[] = "zona_anfeca «{$zonaAnfeca}» contiene un número y un nombre de zonas distintas";
        }

        if ($zonaId !== null && ($catalogoSiglas !== null || $catalogoDescripcion !== null)) {
            [$numCat] = $this->interpretar($catalogoDescripcion);
            $numCat ??= $this->interpretar($catalogoSiglas)[0];
            if ($numCat !== null && $numTexto !== null && $numCat !== $numTexto) {
                $advertencias[] = "zona_id corresponde a la zona {$numCat} y zona_anfeca a la zona {$numTexto}";

                return $this->conEntidad(new ResolucionZona("z{$numCat}", $this->etiquetaOficial($numCat).' (contradicción)',
                    ResolucionZona::CONTRADICTORIA, $numCat, $zonaId, $advertencias), $entidad);
            }
            if ($numCat !== null) {
                return $this->conEntidad(new ResolucionZona("z{$numCat}", $this->etiquetaOficial($numCat),
                    ResolucionZona::NORMALIZADA, $numCat, $zonaId, $advertencias), $entidad);
            }

            return new ResolucionZona("c{$zonaId}", (string) ($catalogoSiglas ?: $catalogoDescripcion),
                ResolucionZona::CATALOGO, null, $zonaId, $advertencias);
        }

        if ($contradiceTexto) {
            return new ResolucionZona('sin_zona', 'Sin clasificar', ResolucionZona::CONTRADICTORIA, null, null, $advertencias);
        }
        if ($numTexto !== null) {
            return $this->conEntidad(new ResolucionZona("z{$numTexto}", $this->etiquetaOficial($numTexto),
                ResolucionZona::DATO_INSTITUCIONAL, $numTexto, null, $advertencias), $entidad);
        }
        if (filled($zonaAnfeca)) {
            $advertencias[] = "zona_anfeca «{$zonaAnfeca}» no corresponde a ninguna zona oficial";
        }

        return new ResolucionZona('sin_zona', 'Sin clasificar', ResolucionZona::SIN_CLASIFICAR, null, null, $advertencias);
    }

    /** Advierte (sin cambiar la zona) si la entidad federativa pertenece oficialmente a otra zona. */
    private function conEntidad(ResolucionZona $r, ?string $entidad): ResolucionZona
    {
        if ($r->numero === null || blank($entidad)) {
            return $r;
        }
        $norm = fn ($s) => Str::of($s)->ascii()->lower()->squish()->value();
        foreach ($this->oficiales() as $num => $z) {
            if (in_array($norm($entidad), array_map($norm, $z['entidades']), true) && $num !== $r->numero) {
                return new ResolucionZona($r->clave, $r->etiqueta, $r->origen, $r->numero, $r->zonaId,
                    [...$r->advertencias, "la entidad «{$entidad}» pertenece oficialmente a la zona {$num}"]);
            }
        }

        return $r;
    }

    /** Todas las instituciones resueltas (una consulta por petición). @return array<int, ResolucionZona> */
    public function instituciones(): array
    {
        // INSTITUCION.INST_ZONA (modelo oficial): «Zona 6 - Sur», «Z6», «zona vi sur»… interpretados contra las 7 zonas.
        return $this->mapa ??= DB::table('institucion')
            ->get(['inst_id_institucion as id', 'inst_zona as zona_anfeca'])
            ->mapWithKeys(fn ($i) => [(int) $i->id => $this->resolver(null, null, null, $i->zona_anfeca)])
            ->all();
    }

    /** Resolución para una institución (o «Sin clasificar» si no hay institución). */
    public function deInstitucion(?int $institucionId): ResolucionZona
    {
        return $institucionId !== null && isset($this->instituciones()[$institucionId])
            ? $this->instituciones()[$institucionId]
            : new ResolucionZona('sin_zona', 'Sin clasificar', ResolucionZona::SIN_CLASIFICAR, null, null, ['sin institución identificada']);
    }

    /** IDs de institución clasificadas con la clave dada (para filtros). @return list<int> */
    public function institucionesConClave(string $clave): array
    {
        return array_keys(array_filter($this->instituciones(), fn (ResolucionZona $r) => $r->clave === $clave));
    }

    /** Opciones de filtro: 7 oficiales + catálogos no oficiales existentes + «Sin clasificar». */
    public function opciones(?array $clavesPermitidas = null, bool $incluirSinClasificar = true): array
    {
        $opciones = collect($this->oficiales())->map(fn ($z, $n) => ['valor' => "z{$n}", 'etiqueta' => $this->etiquetaOficial($n)])->values();
        foreach ($this->instituciones() as $r) {
            if ($r->origen === ResolucionZona::CATALOGO && !$opciones->contains('valor', $r->clave)) {
                $opciones->push(['valor' => $r->clave, 'etiqueta' => $r->etiqueta]);
            }
        }
        if ($clavesPermitidas !== null) {
            $opciones = $opciones->whereIn('valor', $clavesPermitidas)->values();
        }
        if ($incluirSinClasificar) {
            $opciones->push(['valor' => 'sin_zona', 'etiqueta' => 'Sin clasificar']);
        }

        return $opciones->all();
    }

    /** Claves de zona de los catálogos autorizados (para validar filtros regionales). @param list<int> $zonaIds */
    public function clavesDeZonas(array $zonaIds): array
    {
        if ($zonaIds === []) {
            return [];
        }

        // Los números de zona (1 a 7) provienen del privilegio alcance_zona_N.
        return collect($zonaIds)->filter(fn ($n) => isset($this->oficiales()[(int) $n]))->map(fn ($n) => 'z'.(int) $n)->unique()->values()->all();
    }
}
