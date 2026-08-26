<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Service\Seed\DepartamentosLocalidadesArgentinaSeedService;
use common\components\Domain\Person\Service\Seed\ProvinciasArgentinaSeedService;

/**
 * El dump Georef one-shot ya no vive en el repo; solo se valida alineación de códigos canónicos.
 */
class DepartamentosLocalidadesArgentinaSeedServiceTest extends Unit
{
    public function testSeedCompletoYaNoDisponibleEnRepo(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Seed Georef eliminado');
        $ref = new \ReflectionClass(DepartamentosLocalidadesArgentinaSeedService::class);
        $m = $ref->getMethod('loadDefinition');
        $m->setAccessible(true);
        $m->invoke(new DepartamentosLocalidadesArgentinaSeedService());
    }

    public function testProvinciasCanonicasAlineadasConIndec(): void
    {
        $byCod = [];
        foreach (ProvinciasArgentinaSeedService::canonicalRows() as $row) {
            $byCod[$row['cod_indec']] = $row['nombre'];
        }
        $this->assertSame('Santa Fe', $byCod['82']);
        $this->assertSame('Santiago del Estero', $byCod['86']);
    }
}
