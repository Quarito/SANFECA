<?php

namespace Tests\Feature;

use App\Services\Pdm\BasePdm;
use Tests\TestCase;

class BasePdmTest extends TestCase
{
    public function test_el_nombre_oficial_y_las_bases_historicas_son_protegidas(): void
    {
        $this->assertSame('certificacion_anfeca', BasePdm::OFICIAL);
        config(['anfeca_modelo.bd_permitida' => 'base_personalizada']);
        foreach (['certificacion_anfeca', 'bd_anfeca_ensayo_pruebas_pg', 'bd_laravelanfeca_pruebas', 'base_personalizada'] as $base) {
            $this->assertTrue(BasePdm::protegida($base));
            $this->assertFalse(BasePdm::descartable($base, '127.0.0.1'));
        }
    }

    public function test_el_sufijo_no_autoriza_bases_remotas_o_ajenas(): void
    {
        $this->assertFalse(BasePdm::descartable('bd_anfeca_pruebas_pg', '192.0.2.17'));
        $this->assertFalse(BasePdm::descartable('otra_base', '127.0.0.1'));
        $this->assertFalse(BasePdm::descartable('ambiente_8008_pruebas_pg', '127.0.0.1'));
        $this->assertTrue(BasePdm::descartable('bd_anfeca_pruebas_pg', '127.0.0.1'));
        $this->assertTrue(BasePdm::descartable('bd_anfeca_copia_20261127000000_aBC123de_pruebas_pg', null));
    }
}
