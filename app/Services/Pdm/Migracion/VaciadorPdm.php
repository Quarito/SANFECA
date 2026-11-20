<?php

namespace App\Services\Pdm\Migracion;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reversión de F2: elimina de anfeca_modelo los datos de dominio migrados.
 *
 * - Nunca toca public ni los catálogos sembrados (solo los títulos REV creados por la migración).
 * - Se niega si hay datos de etapa 2 (evaluación, dictamen, acta, certificado, refrendo emitido)
 *   o de convocatorias: esos no provienen de la migración y no se borran.
 * - DELETE (no TRUNCATE) dentro de la transacción del llamador.
 */
class VaciadorPdm
{
    /** Orden inverso de dependencias. */
    private const ORDEN = [
        'estado_solicitud_documento', 'solicitud_documento', 'pago', 'persona_estudio_profesional',
        'estado_solicitud', 'solicitud', 'historico_certificado', 'historico_certificado_importacion',
        'coordinador_zona', 'comunicacion', 'domicilio', 'usuario', 'persona', 'sede', 'institucion', 'zona',
        'rol_privilegio', 'privilegio', 'rol',
    ];

    private const NO_MIGRADAS = ['evaluacion', 'certificado', 'refrendo_certificado', 'dictamen', 'acta', 'convocatoria'];

    /** @return array<string,int> filas eliminadas por tabla */
    public function vaciar(): array
    {
        foreach (self::NO_MIGRADAS as $tabla) {
            if (DB::table("anfeca_modelo.{$tabla}")->exists()) {
                throw new RuntimeException("anfeca_modelo.{$tabla} tiene datos que no provienen de la migración: no se vacía nada.");
            }
        }

        $eliminadas = [];
        foreach (self::ORDEN as $tabla) {
            $eliminadas[$tabla] = DB::table("anfeca_modelo.{$tabla}")->delete();
        }
        $eliminadas['estudio_profesional (REV)'] = DB::table('anfeca_modelo.estudio_profesional')->where('espr_estado', 'REV')->delete();

        return $eliminadas;
    }
}
