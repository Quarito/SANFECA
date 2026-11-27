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
        $this->assertStringContainsString('/var/www|/var/www/*)', $script);
        $this->assertStringNotContainsString('dropdb', $script);
        $this->assertStringContainsString('Ejecute este script desde un worktree aislado', $script);
    }

    public function test_restauracion_falla_cerrada_y_sin_cambios_parciales(): void
    {
        $script = $this->script();
        $this->assertStringContainsString('pg_restore --exit-on-error --single-transaction', $script);
        $this->assertStringContainsString('Falló la restauración: la copia no es válida', $script);
        $this->assertStringNotContainsString('pg_restore reportó advertencias', $script);
    }

    public function test_no_ignora_fallos_al_generar_el_plan_pdm(): void
    {
        $script = $this->script();
        $this->assertStringContainsString('Falló la generación del plan PDM.', $script);
        $this->assertStringNotContainsString('anfeca:pdm-migrar --informe="$SALIDA/plan.json" || true', $script);
    }

    public function test_comparacion_bloquea_laravel_si_fallan_huellas_o_son_distintas(): void
    {
        $script = $this->script();
        $this->assertStringContainsString('ANFECA_ENSAYO_HUELLAS', $script);
        $this->assertStringContainsString('huellas_bd_instantanea psql -d "$COPIA"', $script);
        $this->assertStringContainsString('La copia difiere de las huellas de la base de ensayo.', $script);
        $this->assertLessThan(strpos($script, 'php artisan'), strpos($script, 'cmp -s "$HUELLAS_ORIGEN"'));
    }
}
