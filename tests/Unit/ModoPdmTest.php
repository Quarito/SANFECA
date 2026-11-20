<?php

namespace Tests\Unit;

use App\Services\Pdm\ModoPdm;
use RuntimeException;
use Tests\TestCase;

/**
 * F1: el interruptor existe, pero solo "apagado" está permitido.
 */
class ModoPdmTest extends TestCase
{
    public function test_por_omision_esta_apagado(): void
    {
        $this->assertSame('apagado', ModoPdm::actual());
        $this->assertTrue(ModoPdm::apagado());
    }

    public function test_los_modos_de_fases_posteriores_se_rechazan(): void
    {
        foreach (['principal', 'exclusivo'] as $modo) {
            config(['anfeca_pdm.modo' => $modo]);

            try {
                ModoPdm::actual();
                $this->fail("{$modo} no debe activarse en F1");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('aún no está implementado', $e->getMessage());
            }
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
