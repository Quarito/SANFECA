<?php

namespace App\Services\Pdm\Espejo;

use App\Services\Pdm\Migracion\Incidencia;
use App\Services\Pdm\Migracion\PlanMigracionPdm;
use App\Services\Pdm\ModoPdm;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

/**
 * F3 · Escritura en espejo hacia anfeca_modelo.
 *
 * Los controladores lo llaman DENTRO de la transacción en la que escriben en public.
 * - Modo apagado: no hace nada (ni consulta la base).
 * - Modo espejo: recalcula, con las mismas reglas de F2, las filas del PDM de la persona
 *   y/o solicitud afectadas y las aplica. Si algo no puede registrarse en el PDM, lanza
 *   ValidationException: la transacción se revierte completa (public incluido) y el usuario
 *   ve un mensaje controlado en el formulario.
 */
class EspejoPdm
{
    public const MENSAJE_GENERAL = 'No fue posible registrar la información en el modelo institucional. No se guardó ningún cambio; intente de nuevo o informe al administrador.';

    /** Persona, usuario técnico, domicilio y contacto del usuario. */
    public function persona(int $userId): void
    {
        if (ModoPdm::espejo()) {
            $this->aplicar(['usuarios' => [$userId]]);
        }
    }

    /** Solicitud completa (y la persona de su titular, que puede cambiar de institución). */
    public function solicitud(int $solicitudId): void
    {
        if (!ModoPdm::espejo()) {
            return;
        }

        $userId = DB::table('public.solicitudes_certificacion')->where('id', $solicitudId)->value('user_id');
        if ($userId === null) {
            throw ValidationException::withMessages(['pdm' => self::MENSAJE_GENERAL]);
        }

        $this->aplicar(['usuarios' => [(int) $userId], 'solicitudes' => [$solicitudId]]);
    }

    /** Cambios de autenticación (contraseña) del usuario técnico. */
    public function usuario(int $userId): void
    {
        $this->persona($userId);
    }

    private function aplicar(array $alcance): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('EspejoPdm debe ejecutarse dentro de la transacción de la operación.');
        }

        try {
            $plan = PlanMigracionPdm::construir(null, $alcance);
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['pdm' => self::MENSAJE_GENERAL]);
        }

        $bloqueantes = $plan->incidenciasDe(true);
        if ($bloqueantes !== []) {
            throw ValidationException::withMessages($this->mensajes($bloqueantes));
        }

        try {
            (new AplicadorPdm())->aplicar($plan, $alcance);
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['pdm' => self::MENSAJE_GENERAL]);
        }
    }

    /** @param list<Incidencia> $incidencias */
    private function mensajes(array $incidencias): array
    {
        $mensajes = [];

        foreach ($incidencias as $i) {
            if ($i->codigo === 'ENTIDAD_EN_REVISION') {
                $mensajes['entidad_federativa'] = 'Escriba el nombre de una entidad federativa de México (por ejemplo, «Nuevo León» o «Ciudad de México»).';
            } elseif ($i->codigo === 'LONGITUD' && $i->campo !== null) {
                preg_match('/admite (\d+)/', $i->mensaje, $m);
                $mensajes[$i->campo] = 'Este dato admite como máximo '.($m[1] ?? '').' caracteres.';
            } else {
                $mensajes['pdm'] = self::MENSAJE_GENERAL.' (código '.$i->codigo.')';
            }
        }

        return $mensajes;
    }
}
