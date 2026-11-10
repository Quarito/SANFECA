<?php

namespace App\Services;

use App\Models\CertificadoHistorico;
use App\Models\DatosSolicitante;
use App\Models\SeleccionCertificacion;

class ConsultaAntecedentesService
{
    public function consultar(int $userId): array
    {
        $seleccion = SeleccionCertificacion::where(
            'user_id',
            $userId
        )->first();

        $certificacion = $seleccion?->certificacion;

        $resultado = [
            'estado' => 'sin_seleccion',
            'total' => 0,
            'verificados' => 0,
            'posibles_coincidencias' => 0,
            'historial' => [],
        ];

        // No buscar antecedentes sin una certificación seleccionada.
        if (!$certificacion) {
            return $resultado;
        }

        // Consultar únicamente certificados de la certificación elegida.
        $antecedentes = CertificadoHistorico::where(
            'user_id',
            $userId
        )
        ->where('certificacion', $certificacion)
        ->get();

        $total = $antecedentes->count();

        $verificados = $antecedentes->where(
            'estado_vinculacion',
            'verificado'
        )->count();

        $datos = DatosSolicitante::where(
            'user_id',
            $userId
        )->first();

        $posiblesCoincidencias = 0;

        if ($datos) {
            $curp = mb_strtoupper(trim($datos->curp ?? ''));
            $correo = mb_strtolower(trim($datos->correo_electronico ?? ''));

            if ($curp !== '' || $correo !== '') {
                $posiblesCoincidencias = CertificadoHistorico::whereNull(
                    'user_id'
                )
                ->where('certificacion', $certificacion)
                ->where(function ($query) use ($curp, $correo) {
                    if ($curp !== '') {
                        $query->whereRaw(
                            'UPPER(TRIM(curp_historica)) = ?',
                            [$curp]
                        );
                    }

                    if ($correo !== '') {
                        if ($curp !== '') {
                            $query->orWhereRaw(
                                'LOWER(TRIM(correo_historico)) = ?',
                                [$correo]
                            );
                        } else {
                            $query->whereRaw(
                                'LOWER(TRIM(correo_historico)) = ?',
                                [$correo]
                            );
                        }
                    }
                })
                ->count();
            }
        }

        $estado = 'sin_vinculacion';

        if ($verificados > 0) {
            $estado = 'verificado';
        } elseif ($total > 0) {
            $estado = 'pendiente';
        } elseif ($posiblesCoincidencias > 0) {
            $estado = 'posible_coincidencia';
        }

        $historial = $antecedentes
            ->where('estado_vinculacion', 'verificado')
            ->map(function ($antecedente) {
                $refrendos = [];

                for ($numero = 1; $numero <= 7; $numero++) {
                    $campo = 'refrendo_' . $numero;
                    $valor = trim((string) ($antecedente->{$campo} ?? ''));

                    if ($valor !== '') {
                        $refrendos[] = [
                            'numero' => $numero,
                            'anio' => $valor,
                        ];
                    }
                }

                return [
                    'certificacion' => $antecedente->certificacion,
                    'primera_vez' => trim((string) ($antecedente->primera_vez ?? '')),
                    'refrendos' => $refrendos,
                ];
            })
            ->values()
            ->all();

        return [
            'estado' => $estado,
            'total' => $total,
            'verificados' => $verificados,
            'posibles_coincidencias' => $posiblesCoincidencias,
            'historial' => $historial,
        ];
    }

    public function determinarTipoTramite(int $userId): string
    {
        $resultado = $this->consultar($userId);

        if ($resultado['estado'] === 'sin_seleccion') {
            return 'sin_seleccion';
        }

        if (
            $resultado['posibles_coincidencias'] > 0 ||
            $resultado['total'] > $resultado['verificados']
        ) {
            return 'revision_pendiente';
        }

        if ($resultado['verificados'] > 0) {
            foreach ($resultado['historial'] as $antecedente) {
                if (trim($antecedente['primera_vez']) !== '') {
                    return 'refrendo';
                }
            }

            return 'revision_pendiente';
        }

        return 'inicial';
    }


    public function obtenerDatosRefrendo(int $userId): ?array
    {
        if ($this->determinarTipoTramite($userId) !== 'refrendo') {
            return null;
        }

        $resultado = $this->consultar($userId);

        $numerosRegistrados = [];

        foreach ($resultado['historial'] as $antecedente) {
            foreach ($antecedente['refrendos'] as $refrendo) {
                $numerosRegistrados[] = (int) $refrendo['numero'];
            }
        }

        $ultimoRefrendo = empty($numerosRegistrados)
            ? 0
            : max($numerosRegistrados);

        return [
            'refrendo_actual' => $ultimoRefrendo + 1,
            'numero_refrendo_asignado' => null,
        ];
    }

}
