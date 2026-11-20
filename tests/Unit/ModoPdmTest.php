<?php

namespace Tests\Unit;

use App\Services\Pdm\ModoPdm;
use RuntimeException;
use Tests\TestCase;

/**
 * Interruptor del modo PDM: sin activación registrada (dos llaves) siempre opera en apagado.
 */
class ModoPdmTest extends TestCase
{
    public function test_por_omision_esta_apagado(): void
    {
        $this->assertSame('apagado', ModoPdm::actual());
        $this->assertTrue(ModoPdm::apagado());
    }

    public function test_principal_y_exclusivo_estan_implementados_en_configuracion(): void
    {
        foreach (['principal', 'exclusivo'] as $modo) {
            config(['anfeca_pdm.modo' => $modo]);
            $this->assertSame($modo, ModoPdm::actual());
        }
    }

    public function test_espejo_se_admite_en_configuracion_pero_sin_activacion_opera_apagado(): void
    {
        config(['anfeca_pdm.modo' => 'espejo']);

        $this->assertSame('espejo', ModoPdm::actual());
        // SQLite: no existe la tabla de activación → la aplicación sigue en apagado.
        $this->assertSame('apagado', ModoPdm::efectivo());
    }

    public function test_principal_y_exclusivo_operan_como_apagado_sin_romper_la_aplicacion(): void
    {
        foreach (['principal', 'exclusivo', 'desconocido'] as $modo) {
            config(['anfeca_pdm.modo' => $modo]);
            $this->assertSame('apagado', ModoPdm::efectivo(), $modo);
        }
    }

    public function test_un_modo_desconocido_se_rechaza(): void
    {
        config(['anfeca_pdm.modo' => 'total']);

        $this->expectException(RuntimeException::class);
        ModoPdm::actual();
    }
}
