<?php

namespace App\Services\Anfeca;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;

/**
 * Antecedentes en HISTORICO_CERTIFICADO. Cada fila es un EVENTO de un certificado:
 *   HICE_FOLIO_CERTIFICADO     número de certificado (el que captura la persona, 1 a 7 dígitos)
 *   HICE_TIPO                  certificación (ANFECA, CACSLA o CACECA)
 *   HICE_NUMERO_CERTIFICACION  0 = certificación por primera vez; 1…7 = número de refrendo
 *   HICE_ANIO_CERTIFICACION    año del evento
 *   HICE_CURP                  CURP de la persona certificada (vínculo con PERSONA.PERS_CURP)
 *   HICE_AREA_CERTIFICACION    área de certificación
 * Un mismo folio tiene varias filas (PK compuesta id + folio); dos folios distintos son dos certificados.
 */
final class HistoricoCertificados
{
    /** Certificado reconstruido a partir de sus eventos, o null si el folio no existe para esa certificación. */
    public function certificado(string $folio, ?string $certificacion = null): ?Fluent
    {
        $folio = trim($folio);
        $eventos = DB::table('historico_certificado')
            ->where('hice_folio_certificado', $folio)
            ->when($certificacion, fn ($q) => $q->where('hice_tipo', $certificacion))
            ->orderBy('hice_id_historico_certificado')->get();

        return $eventos->isEmpty() ? null : $this->armar($folio, $eventos);
    }

    /** Certificados de una CURP (vínculo verificable por CURP) para una certificación. */
    public function dePersona(string $curp, string $certificacion): array
    {
        return DB::table('historico_certificado')
            ->whereRaw('upper(trim(hice_curp)) = ?', [mb_strtoupper(trim($curp))])
            ->where('hice_tipo', $certificacion)
            ->orderBy('hice_id_historico_certificado')->get()
            ->groupBy('hice_folio_certificado')
            ->map(fn ($eventos, $folio) => $this->armar((string) $folio, $eventos))
            ->values()->all();
    }

    private function armar(string $folio, $eventos): Fluent
    {
        $primero = $eventos->first();
        $datos = [
            'id' => (int) ltrim($folio, '0') ?: 0,
            'numero_certificado' => $folio,
            'certificacion' => $primero->hice_tipo,
            'curp_historica' => $eventos->pluck('hice_curp')->filter()->first(),
            'area_certificacion' => $eventos->pluck('hice_area_certificacion')->filter()->last(),
            'primera_vez' => null,
            'curps' => $eventos->pluck('hice_curp')->filter()->map(fn ($c) => mb_strtoupper(trim($c)))->unique()->values()->all(),
        ];
        $max = (int) config('anfeca_8088.refrendos_maximos', 7);
        for ($i = 1; $i <= $max; $i++) {
            $datos['refrendo_'.$i] = null;
        }
        foreach ($eventos as $e) {
            $numero = trim((string) $e->hice_numero_certificacion);
            if ($numero === '0' || strcasecmp($numero, 'PV') === 0) {
                $datos['primera_vez'] = trim((string) $e->hice_anio_certificacion);
            } elseif (ctype_digit($numero) && (int) $numero >= 1 && (int) $numero <= $max) {
                $datos['refrendo_'.(int) $numero] = trim((string) $e->hice_anio_certificacion);
            }
        }

        return new Fluent($datos);
    }
}
