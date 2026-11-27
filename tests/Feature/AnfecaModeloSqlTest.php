<?php

namespace Tests\Feature;

use App\Services\AnfecaModeloInspector;
use Tests\TestCase;

/**
 * Pruebas del esquema aislado del PDM (anfeca_modelo).
 * No requieren PostgreSQL: revisan el script y las protecciones de los comandos.
 */
class AnfecaModeloSqlTest extends TestCase
{
    private function script(): string
    {
        return (string) file_get_contents(config('anfeca_modelo.script'));
    }

    public function test_el_script_declara_36_tablas_y_35_llaves_foraneas(): void
    {
        $conteo = AnfecaModeloInspector::contarEnScript($this->script());

        $this->assertSame(36, $conteo['tablas']);
        $this->assertSame(35, $conteo['llaves_foraneas']);
    }

    public function test_el_ddl_coincide_con_la_referencia_corregida_de_powerdesigner(): void
    {
        // Huella de la referencia adjunta al retirar comentarios, envoltura de esquema
        // y transacción, y normalizar espacios. Incluye tipos, PK, FK, índices y comentarios SQL.
        $ddl = preg_replace('~/\*.*?\*/|--[^\n]*~s', '', $this->script());
        $ddl = preg_replace('/^\s*create schema anfeca_modelo;\s*/im', '', $ddl);
        $ddl = str_replace('anfeca_modelo.', '', $ddl);
        $ddl = trim(preg_replace('/\s+/', ' ', $ddl));

        $this->assertSame('a894bfafe6750fbd47730a83f89a5ef83ea4133024f04b3bf71a063b43f2ebc5', hash('sha256', $ddl));
    }

    public function test_el_script_pasa_la_revision_estatica(): void
    {
        $this->assertSame(
            [],
            AnfecaModeloInspector::revisarScript($this->script(), 'anfeca_modelo')
        );
    }

    public function test_la_revision_detecta_drop_y_referencias_a_public(): void
    {
        $peligroso = "create schema anfeca_modelo;\ndrop table USERS;\ncreate table public.X (id int);";

        $problemas = AnfecaModeloInspector::revisarScript($peligroso, 'anfeca_modelo');

        $this->assertNotEmpty(array_filter($problemas, fn ($p) => str_contains($p, 'drop')));
        $this->assertNotEmpty(array_filter($problemas, fn ($p) => str_contains($p, 'public')));
    }

    public function test_la_revision_detecta_tablas_sin_calificar(): void
    {
        $problemas = AnfecaModeloInspector::revisarScript(
            "create schema anfeca_modelo;\ncreate table SOLICITUD (id int);",
            'anfeca_modelo'
        );

        $this->assertNotEmpty($problemas);
    }

    public function test_el_indice_invalido_de_evaluacion_no_se_crea(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/create\s+index\s+TIENE_ARTICULO_FK/i',
            $this->script()
        );
    }

    public function test_relationship_27_fk_no_es_unico(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/create\s+unique\s+index\s+RELATIONSHIP_27_FK/i',
            $this->script()
        );
    }

    public function test_el_esquema_configurado_no_es_public(): void
    {
        $this->assertNotSame('public', strtolower(config('anfeca_modelo.esquema')));
    }

    public function test_instalar_se_niega_si_la_conexion_no_es_postgresql(): void
    {
        // phpunit.xml usa sqlite en memoria.
        $this->artisan('anfeca:modelo-instalar', ['--ejecutar' => true, '--confirmar' => true])
            ->expectsOutputToContain('no es PostgreSQL')
            ->assertFailed();
    }

    public function test_verificar_se_niega_si_la_conexion_no_es_postgresql(): void
    {
        $this->artisan('anfeca:modelo-verificar')
            ->expectsOutputToContain('no es PostgreSQL')
            ->assertFailed();
    }

    public function test_instalar_se_niega_si_el_esquema_configurado_es_public(): void
    {
        config(['anfeca_modelo.esquema' => 'public']);

        $this->artisan('anfeca:modelo-instalar')
            ->expectsOutputToContain('ni public')
            ->assertFailed();
    }
}
