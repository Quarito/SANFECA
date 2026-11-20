<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Pdm\GuardiaBaseAislada;

/**
 * La protección de las pruebas PostgreSQL rechaza cualquier base que no sea la aislada.
 */
class GuardiaBaseAisladaTest extends TestCase
{
    private function motivos(array $cambios = []): array
    {
        $p = array_merge([
            'driver' => 'pgsql', 'bdConfigurada' => 'bd_anfeca_pruebas_pg', 'bdReal' => 'bd_anfeca_pruebas_pg',
            'host' => '127.0.0.1', 'direccionServidor' => '127.0.0.1', 'bdPermitida' => 'bd_laravelanfeca_pruebas',
            'sufijo' => '_pruebas_pg', 'configuracionEnCache' => false, 'url' => '',
        ], $cambios);

        return GuardiaBaseAislada::motivosDeRechazo(...$p);
    }

    public function test_acepta_la_base_aislada_local(): void
    {
        $this->assertSame([], $this->motivos());
        $this->assertSame([], $this->motivos(['host' => '/var/run/postgresql', 'direccionServidor' => null]));
    }

    public function test_rechaza_la_base_de_trabajo(): void
    {
        $this->assertNotEmpty($this->motivos(['bdConfigurada' => 'bd_laravelanfeca_pruebas', 'bdReal' => 'bd_laravelanfeca_pruebas']));
    }

    public function test_rechaza_una_base_real_distinta_de_la_configurada(): void
    {
        $motivos = $this->motivos(['bdReal' => 'bd_laravelanfeca_pruebas']);

        $this->assertNotEmpty($motivos);
        $this->assertStringContainsString('distinta de la configurada', implode(' ', $motivos));
    }

    public function test_rechaza_nombre_sin_sufijo_host_remoto_y_otros_motores(): void
    {
        $this->assertNotEmpty($this->motivos(['bdConfigurada' => 'otra_base', 'bdReal' => 'otra_base']));
        $this->assertNotEmpty($this->motivos(['host' => '192.168.7.130']));
        $this->assertNotEmpty($this->motivos(['direccionServidor' => '192.168.7.130']));
        $this->assertNotEmpty($this->motivos(['driver' => 'sqlite', 'bdReal' => null]));
        $this->assertNotEmpty($this->motivos(['sufijo' => '']));
    }

    public function test_rechaza_configuracion_en_cache_y_db_url(): void
    {
        $this->assertNotEmpty($this->motivos(['configuracionEnCache' => true]));
        $this->assertNotEmpty($this->motivos(['url' => 'pgsql://u:p@127.0.0.1/bd_laravelanfeca_pruebas']));
    }
}
