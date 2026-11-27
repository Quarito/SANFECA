<?php

namespace App\Services\Coordinacion;

use App\Models\Cuenta;

/**
 * Alcance de la coordinación según ROL → ROL_PRIVILEGIO → PRIVILEGIO del modelo oficial:
 *  - alcance_nacional: todas las zonas (incluidas las solicitudes sin zona determinable).
 *  - alcance_zona_N:   solo la zona N (1 a 7). La zona de cada solicitud es la de la institución de la persona.
 * El alcance lo concede únicamente el rol asignado a la cuenta; un dato capturado nunca lo amplía.
 */
class AlcanceZona
{
    /** @var array<int, list<int>> caché por petición */
    private array $zonas = [];

    public function esNacional(?Cuenta $usuario): bool
    {
        return $usuario?->esCoordinadorNacional() === true && $usuario->tienePrivilegio('alcance_nacional');
    }

    /** @return list<int>|null números de zona autorizados; null = todas */
    public function zonasAutorizadas(?Cuenta $usuario): ?array
    {
        if ($this->esNacional($usuario)) {
            return null;
        }
        if ($usuario === null || !$usuario->esCoordinadorZona()) {
            return [];
        }

        return $this->zonas[$usuario->id] ??= collect($usuario->privilegios())
            ->map(fn ($p) => preg_match('/^alcance_zona_([1-7])$/', $p, $m) ? (int) $m[1] : null)
            ->filter()->unique()->sort()->values()->all();
    }

    public function permiteZona(?Cuenta $usuario, ?int $zona): bool
    {
        $zonas = $this->zonasAutorizadas($usuario);
        if ($zonas === null) {
            return $this->esNacional($usuario);
        }

        return $zona !== null && in_array($zona, $zonas, true);
    }

    public function descripcion(?Cuenta $usuario): string
    {
        $zonas = $this->zonasAutorizadas($usuario);
        if ($zonas === null) {
            return 'Todas las zonas';
        }
        if ($zonas === []) {
            return 'Sin zona asignada';
        }
        $z = app(\App\Services\Zonas\ZonasAnfeca::class);

        return collect($zonas)->map(fn ($n) => $z->etiquetaOficial($n))->implode(', ');
    }
}
