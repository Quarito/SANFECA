<?php

namespace Tests\Unit;

use App\Services\Pdm\NormalizadorCatalogos;
use Tests\TestCase;

/**
 * Reglas de normalización con los valores REALES observados en el servidor
 * (consulta D de PLAN_INTEGRACION_FUNCIONAL_PDM.md, 20/11/2026). Sin base de datos.
 */
class NormalizadorCatalogosTest extends TestCase
{
    private NormalizadorCatalogos $n;

    protected function setUp(): void
    {
        parent::setUp();
        $this->n = new NormalizadorCatalogos();
    }

    public function test_entidades_observadas_en_el_servidor(): void
    {
        $casos = [
            // valor original => [entidad del catálogo o null, regla, revisión]
            'cdmx' => ['Ciudad de México', 'N-2', false],
            'CDMX' => ['Ciudad de México', 'N-2', false],
            'tlaxcala' => ['Tlaxcala', 'N-1', false],
            'quintana roo' => ['Quintana Roo', 'N-1', false],
            'Monterrey' => [null, 'N-3', true],
            'monterrey' => [null, 'N-3', true],
            'Entidad de Prueba' => [null, 'N-3', true],
        ];

        foreach ($casos as $original => [$esperado, $regla, $revision]) {
            $r = $this->n->entidadFederativa($original);

            $this->assertSame($esperado, $r->valor, $original);
            $this->assertSame($regla, $r->regla, $original);
            $this->assertSame($revision, $r->revision, $original);
            $this->assertSame($original, $r->original, 'el valor histórico se conserva');
        }

        $this->assertSame(9, $this->n->entidadFederativa('CDMX')->id);
        $this->assertStringContainsString('Nuevo León', $this->n->entidadFederativa('Monterrey')->motivo);
    }

    public function test_acentos_mayusculas_y_espacios_no_cambian_la_entidad(): void
    {
        $this->assertSame('Nuevo León', $this->n->entidadFederativa('  NUEVO   LEON ')->valor);
        $this->assertSame('Michoacán de Ocampo', $this->n->entidadFederativa('Michoacán')->valor);
        $this->assertTrue($this->n->entidadFederativa('')->revision);
    }

    public function test_genero(): void
    {
        $this->assertSame('F', $this->n->genero('Femenino')->valor);
        $this->assertSame('M', $this->n->genero('Masculino')->valor);

        $vacio = $this->n->genero('');
        $this->assertNull($vacio->valor, 'no se supone un género');
        $this->assertFalse($vacio->revision, 'PERS_GENERO admite nulo');

        $this->assertTrue($this->n->genero('X')->revision);
    }

    public function test_areas_y_tipos_de_personal_observados(): void
    {
        foreach (['Administración', 'Informática Administrativa', 'Disciplinas vinculadas al ámbito económico-administrativo'] as $area) {
            $this->assertSame($area, $this->n->catalogoExacto('areas_certificacion', $area)->valor);
        }

        $this->assertSame(
            'Profesoras y profesores de Tiempo Completo',
            $this->n->catalogoExacto('tipos_personal_academico', 'Profesoras y profesores de Tiempo Completo')->valor
        );

        $vacia = $this->n->catalogoExacto('areas_certificacion', '');
        $this->assertTrue($vacia->revision);
        $this->assertStringContainsString('N-6', $vacia->motivo);
    }

    public function test_numero_de_certificado_y_anio(): void
    {
        $this->assertSame('1000022', $this->n->numeroCertificado(' 1000022 ')->valor);
        $this->assertTrue($this->n->numeroCertificado('12345678')->revision, 'más de 7 dígitos');
        $this->assertTrue($this->n->numeroCertificado('ANF-123')->revision, 'formato no numérico');
        $this->assertTrue($this->n->numeroCertificado(null)->revision);

        $this->assertSame('2023', $this->n->anio('2023')->valor);
        $this->assertNull($this->n->anio(null)->valor, 'sin refrendo: nulo, sin revisión');
        $this->assertTrue($this->n->anio('23')->revision);
    }
}
