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

    public function test_los_modos_de_fases_posteriores_se_rechazan_en_f1(): void
    {
        foreach (['espejo', 'principal', 'exclusivo'] as $modo) {
            config(['anfeca_pdm.modo' => $modo]);

            try {
                ModoPdm::actual();
                $this->fail("{$modo} no debe activarse en F1");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('aún no está implementado', $e->getMessage());
            }
        }
    }

    public function test_un_modo_desconocido_se_rechaza(): void
    {
        config(['anfeca_pdm.modo' => 'total']);

        $this->expectException(RuntimeException::class);
        ModoPdm::actual();
    }
}
