<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Datos FICTICIOS para el laboratorio del 8088 (instituciones y certificados históricos de prueba).
 * Solo opera con APP_ENV=local o testing. No crea estructuras; inserta filas faltantes.
 */
class SembrarLaboratorioAnfeca extends Command
{
    protected $signature = 'anfeca:laboratorio';

    protected $description = 'Inserta instituciones y certificados históricos ficticios para pruebas del 8088.';

    public const INSTITUCIONES = [
        ['LAB0001', 'Universidad Ficticia del Centro Sur', 'Facultad Ficticia de Contaduría y Administración', 'Zona 5 - Centro Sur', 'Sede Ficticia Puebla'],
        ['LAB0002', 'Instituto Ficticio de la Ciudad de México', 'Escuela Ficticia de Negocios', 'Zona 7 - Ciudad de México', 'Sede Ficticia Coyoacán'],
        ['LAB0003', 'Universidad Ficticia del Sur', 'Facultad Ficticia de Ciencias Administrativas', 'Zona 6 - Sur', 'Sede Ficticia Mérida'],
    ];

    /** [folio, certificación, curp, área, [número => año]] (0 = primera vez). */
    public const HISTORICOS = [
        ['1001', 'ANFECA', 'FILR800101HDFCTC09', 'Administración', [0 => '2017', 1 => '2020']],
        ['1002', 'ANFECA', 'FILR800101HDFCTC09', 'Contaduría Pública', [0 => '2024']],
        ['1003', 'ANFECA', 'FIXX700101MDFCTC01', 'Administración', [0 => '2015']],
    ];

    public function handle(): int
    {
        if (!app()->environment('local', 'testing')) {
            $this->error('Solo disponible con APP_ENV=local o testing.');

            return self::FAILURE;
        }
        DB::transaction(function () {
            foreach (self::INSTITUCIONES as [$numero, $nombre, $facultad, $zona, $sede]) {
                $id = DB::table('institucion')->where('inst_numero_afiliacion', $numero)->value('inst_id_institucion')
                    ?? DB::table('institucion')->insertGetId([
                        'inst_numero_afiliacion' => $numero, 'inst_nombre' => $nombre, 'inst_facultad' => $facultad,
                        'inst_zona' => $zona, 'inst_coord_regional_cert_academica' => 'Coordinación regional ficticia',
                    ], 'inst_id_institucion');
                if (!DB::table('sede')->where('inst_id_institucion', $id)->exists()) {
                    DB::table('sede')->insert(['inst_id_institucion' => $id, 'sede_descripcion' => $sede]);
                }
            }
            foreach (self::HISTORICOS as [$folio, $certificacion, $curp, $area, $eventos]) {
                foreach ($eventos as $numero => $anio) {
                    $existe = DB::table('historico_certificado')->where('hice_folio_certificado', $folio)
                        ->where('hice_numero_certificacion', (string) $numero)->exists();
                    if (!$existe) {
                        DB::table('historico_certificado')->insert([
                            'hice_anio_certificacion' => $anio, 'hice_numero_certificacion' => (string) $numero,
                            'hice_tipo' => $certificacion, 'hice_folio_certificado' => $folio, 'hice_curp' => $curp,
                            'hice_area_certificacion' => $area,
                        ]);
                    }
                }
            }
        });
        $this->info('Datos ficticios del laboratorio verificados.');

        return self::SUCCESS;
    }
}
