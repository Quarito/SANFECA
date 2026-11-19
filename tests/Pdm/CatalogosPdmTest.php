<?php

namespace Tests\Pdm;

use Database\Seeders\Pdm\CatalogosPdmSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogos sembrados desde las fuentes reales del sistema (config/anfeca_pdm.php).
 */
class CatalogosPdmTest extends PdmTestCase
{
    private const CONTEOS = [
        'entidad_federativa' => 32,
        'tipo_comunicacion' => 3,
        'c_estado_solicitud' => 4,
        'documento' => 9,
        'estado_documento' => 2,
        'articulo_subindice' => 4,
        'nivel_profesional' => 4,
        'estudio_profesional' => 16,
        'area_certificacion' => 5,
        'tipo_personal_academico' => 3,
    ];

    public function test_conteos_de_catalogos(): void
    {
        foreach (self::CONTEOS as $tabla => $total) {
            $this->assertSame($total, DB::table("anfeca_modelo.{$tabla}")->count(), $tabla);
        }
    }

    public function test_entidades_usan_la_clave_inegi(): void
    {
        $this->assertSame(9, $this->idCatalogo('entidad_federativa', 'enfe_nombre', 'Ciudad de México'));
        $this->assertSame(19, $this->idCatalogo('entidad_federativa', 'enfe_nombre', 'Nuevo León'));
        $this->assertSame(23, $this->idCatalogo('entidad_federativa', 'enfe_nombre', 'Quintana Roo'));
        $this->assertSame(29, $this->idCatalogo('entidad_federativa', 'enfe_nombre', 'Tlaxcala'));

        // Secuencia alineada: una entidad nueva no choca con las claves INEGI.
        $nueva = DB::table('anfeca_modelo.entidad_federativa')->insertGetId(['enfe_nombre' => 'Prueba de secuencia'], 'enfe_id_entidad_federativa');
        $this->assertSame(33, $nueva);
    }

    public function test_el_sembrado_es_idempotente(): void
    {
        (new CatalogosPdmSeeder())->run();
        (new CatalogosPdmSeeder())->run();

        foreach (self::CONTEOS as $tabla => $total) {
            $this->assertSame($total, DB::table("anfeca_modelo.{$tabla}")->count(), "{$tabla} duplicado");
        }
    }

    public function test_catalogos_coinciden_con_la_interfaz(): void
    {
        $this->assertEqualsCanonicalizing(
            config('anfeca_pdm.catalogos.areas_certificacion'),
            DB::table('anfeca_modelo.area_certificacion')->pluck('area_descripcion')->all()
        );

        // Las mismas áreas que usa config/anfeca_equivalencias.php para las reglas de título.
        $this->assertEqualsCanonicalizing(
            array_keys(config('anfeca_equivalencias.areas')),
            DB::table('anfeca_modelo.area_certificacion')->pluck('area_descripcion')->all()
        );

        // Los títulos del catálogo no incluyen «Otro» (se registra en revisión al capturarse).
        $this->assertSame(0, DB::table('anfeca_modelo.estudio_profesional')->where('espr_nombre', 'Otro')->count());
        $this->assertSame(0, DB::table('anfeca_modelo.estudio_profesional')->where('espr_estado', 'REV')->count());
    }
}
