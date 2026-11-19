<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('anfeca:reiniciar-demo
    {--usuarios=I,J,K,L : Usuarios de demostración}
    {--ejecutar : Ejecutar el reinicio después de confirmar}')]
#[Description('Reinicia los registros de demostración ANFECA sin eliminar usuarios ni certificados históricos')]
class ReiniciarDemo extends Command
{
    public function handle(): int
    {
        if (realpath(base_path()) !== '/var/www/ANFECA_PRUEBAS') {
            $this->error('Solo se permite en ANFECA_PRUEBAS.');
            return self::FAILURE;
        }

        if (DB::connection()->getDatabaseName() !== 'bd_laravelanfeca_pruebas') {
            $this->error('Base de datos incorrecta.');
            return self::FAILURE;
        }

        $mapa = [
            'I' => 11,
            'J' => 12,
            'K' => 13,
            'L' => 14,
        ];

        $usuarios = array_map(
            'trim',
            explode(',', strtoupper((string) $this->option('usuarios')))
        );

        if (
            in_array('', $usuarios, true) ||
            count($usuarios) !== count(array_unique($usuarios)) ||
            array_diff($usuarios, array_keys($mapa))
        ) {
            $this->error('Usuarios inválidos.');
            return self::FAILURE;
        }

        $ids = array_map(fn ($u) => $mapa[$u], $usuarios);

        $solicitudes = DB::table('solicitudes_certificacion')
            ->whereIn('user_id', $ids)
            ->pluck('id')
            ->all();

        $dependientes = [
            'datos_refrendo',
            'documentos_probatorios_solicitud',
            'formaciones_academicas_solicitud',
            'historial_documentos_academicos',
        ];

        $individuales = [
            'datos_solicitantes',
            'selecciones_certificacion',
            'formaciones_academicas',
            'documentos_probatorios',
        ];

        $filas = [];

        foreach ($dependientes as $tabla) {
            $filas[] = [
                $tabla,
                DB::table($tabla)
                    ->whereIn('solicitud_id', $solicitudes)
                    ->count(),
            ];
        }

        $filas[] = [
            'solicitudes_certificacion',
            count($solicitudes),
        ];

        foreach ($individuales as $tabla) {
            $filas[] = [
                $tabla,
                DB::table($tabla)
                    ->whereIn('user_id', $ids)
                    ->count(),
            ];
        }

        $this->info('ANFECA - REINICIO DE DEMOSTRACIÓN');
        $this->line('Usuarios: ' . implode(', ', $usuarios));
        $this->table(['Tabla', 'Registros'], $filas);

        $historicos = DB::table('certificados_historicos')
            ->whereIn('user_id', $ids)
            ->count();

        $this->info("Certificados históricos conservados: {$historicos}");

        if (!$this->option('ejecutar')) {
            $this->warn('VISTA PREVIA: no se eliminó ningún registro.');
            return self::SUCCESS;
        }

        if (!$this->confirm(
            '¿Confirmas que deseas reiniciar los registros de estos usuarios?',
            false
        )) {
            $this->warn('Operación cancelada.');
            return self::SUCCESS;
        }

        DB::transaction(function () use (
            $dependientes,
            $individuales,
            $solicitudes,
            $ids
        ) {
            foreach ($dependientes as $tabla) {
                DB::table($tabla)
                    ->whereIn('solicitud_id', $solicitudes)
                    ->delete();
            }

            DB::table('solicitudes_certificacion')
                ->whereIn('user_id', $ids)
                ->delete();

            foreach ($individuales as $tabla) {
                DB::table($tabla)
                    ->whereIn('user_id', $ids)
                    ->delete();
            }
        });

        $this->info('Registros de demostración reiniciados.');
        $this->warn(
            'Los archivos físicos no se eliminaron. '
            . 'Se requiere limpieza controlada posterior.'
        );

        return self::SUCCESS;
    }
}
