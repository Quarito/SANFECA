<?php

namespace App\Services\Zonas;

/** Resultado de resolver la zona de una institución (solo presentación y clasificación). */
final class ResolucionZona
{
    public const NORMALIZADA = 'normalizada';            // zona_id válido y reconocible como zona oficial
    public const CATALOGO = 'catalogo';                  // zona_id válido de un catálogo no oficial
    public const DATO_INSTITUCIONAL = 'dato_institucional'; // zona_anfeca cargada, sin zona_id
    public const CONTRADICTORIA = 'contradictoria';      // zona_id y zona_anfeca (o su nombre) no coinciden
    public const SIN_CLASIFICAR = 'sin_clasificar';

    public function __construct(
        public readonly string $clave,      // 'z1'…'z7', 'c<id>' (catálogo no oficial) o 'sin_zona'
        public readonly string $etiqueta,   // «Zona 6 - Sur», «Sin clasificar», …
        public readonly string $origen,
        public readonly ?int $numero = null,
        public readonly ?int $zonaId = null,
        public readonly array $advertencias = [],
    ) {
    }

    public function clasificada(): bool
    {
        return $this->clave !== 'sin_zona';
    }

    public function toArray(): array
    {
        return ['clave' => $this->clave, 'etiqueta' => $this->etiqueta, 'origen' => $this->origen,
            'numero' => $this->numero, 'advertencias' => $this->advertencias];
    }
}
