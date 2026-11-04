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

        return [
            'estado' => $estado,
            'total' => $total,
            'verificados' => $verificados,
            'posibles_coincidencias' => $posiblesCoincidencias,
        ];
    }
}
