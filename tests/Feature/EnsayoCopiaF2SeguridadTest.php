<?php

namespace Tests\Feature;

use Tests\TestCase;

class EnsayoCopiaF2SeguridadTest extends TestCase
{
    private function script(): string
    {
        return (string) file_get_contents(base_path('scripts/anfeca/ensayo_copia_f2.sh'));
    }

    public function test_la_copia_utiliza_la_base_de_ensayo_y_no_la_base_antigua(): void
    {
        $script = $this->script();
        $this->assertStringContainsString('TRABAJO="bd_anfeca_ensayo_pruebas_pg"', $script);
        $this->assertStringNotContainsString('TRABAJO="bd_laravelanfeca_pruebas"', $script);
    }

    public function test_rechaza_directorios_de_despliegue_activos(): void
    {
        $script = $this->script();
        foreach (['/var/www/ANFECA_PRUEBAS', '/var/www/ANFECA_ENSAYO', '/var/www/anfeca'] as $ruta) {
            $this->assertStringContainsString($ruta, $script);
        }
        $this->assertStringContainsString('Ejecute este script desde un worktree aislado', $script);
    }

    public function test_restauracion_falla_cerrada_y_sin_cambios_parciales(): void
    {
        $script = $this->script();
        $this->assertStringContainsString('pg_restore --exit-on-error --single-transaction', $script);
        $this->assertStringContainsString('Falló la restauración: la copia no es válida', $script);
        $this->assertStringNotContainsString('pg_restore reportó advertencias', $script);
    }

    public function test_comparacion_aborta_si_fallan_consultas_o_difieren_conteos(): void
    {
        $script = $this->script();
        $this->assertStringContainsString('CONTEOS_ORIGEN=$(psql', $script);
        $this->assertStringContainsString('CONTEOS_COPIA=$(psql', $script);
        $this->assertStringContainsString('No se pudieron consultar los conteos del origen.', $script);
        $this->assertStringContainsString('No se pudieron consultar los conteos de la copia.', $script);
        $this->assertStringContainsString('La copia difiere de la base de ensayo.', $script);
    }
}
