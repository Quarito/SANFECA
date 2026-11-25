<?php

namespace App\Services\Coordinacion;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Alcance territorial de una cuenta de coordinación, resuelto SOLO en el servidor:
 *
 *  - coordinador_nacional: todas las zonas, incluidas las solicitudes sin zona
 *    determinable («pendientes de clasificación»).
 *  - coordinador_zona: únicamente las zonas asignadas en coordinador_zona.
 *    Sin asignación → ningún registro. Nunca ve solicitudes sin zona.
 *  - cualquier otro perfil: ningún registro.
 *
 * La zona de una solicitud se deriva de la institución de su formación académica:
 * solicitud → formaciones_academicas_solicitud.institucion_afiliada_id
 *           → instituciones_afiliadas.zona_id → zonas.
 * Ningún parámetro de la petición puede ampliar este alcance.
 */
class AlcanceZona
{
    /** @var array<int, list<int>> caché por petición */
    private array $zonas = [];

    public function esNacional(?User $usuario): bool
    {
        return $usuario?->esCoordinadorNacional() === true;
    }

    /**
     * IDs de zona autorizados. null = todas (coordinación nacional).
     *
     * @return list<int>|null
     */
    public function zonasAutorizadas(?User $usuario): ?array
    {
        if ($this->esNacional($usuario)) {
            return null;
        }

        if ($usuario === null || !$usuario->esCoordinadorZona()) {
            return [];
        }

        return $this->zonas[$usuario->id] ??= DB::table('coordinador_zona')
            ->where('user_id', $usuario->id)
            ->orderBy('zona_id')
            ->pluck('zona_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** ¿Puede consultar la zona indicada? (null = solicitud sin zona determinable) */
    public function permiteZona(?User $usuario, ?int $zonaId): bool
    {
        $zonas = $this->zonasAutorizadas($usuario);

        if ($zonas === null) {
            return $this->esNacional($usuario);
        }

        return $zonaId !== null && in_array($zonaId, $zonas, true);
    }

    /** Texto para la barra de usuario. */
    public function descripcion(?User $usuario): string
    {
        $zonas = $this->zonasAutorizadas($usuario);

        if ($zonas === null) {
            return 'Todas las zonas';
        }

        if ($zonas === []) {
            return 'Sin zona asignada';
        }

        return DB::table('zonas')->whereIn('id', $zonas)->orderBy('siglas')->pluck('siglas')->implode(', ');
    }
}
