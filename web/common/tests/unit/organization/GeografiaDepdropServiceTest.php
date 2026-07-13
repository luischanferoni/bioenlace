<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Service\GeografiaDepdropService;

class GeografiaDepdropServiceTest extends Unit
{
    public function testDepartamentosResponseSinParents(): void
    {
        $result = GeografiaDepdropService::departamentosResponse([]);
        $this->assertSame('', $result['selected']);
        $this->assertSame('', $result['output']);
    }

    public function testLocalidadesPorProvinciaResponseSinParents(): void
    {
        $result = GeografiaDepdropService::localidadesPorProvinciaResponse([]);
        $this->assertSame('', $result['selected']);
        $this->assertSame('', $result['output']);
    }

    public function testLocalidadesPorProvinciaResponseParentVacio(): void
    {
        $result = GeografiaDepdropService::localidadesPorProvinciaResponse([
            'depdrop_parents' => [''],
        ]);
        $this->assertSame('', $result['selected']);
        $this->assertSame('', $result['output']);
    }

    public function testBarriosResponseSinParents(): void
    {
        $result = GeografiaDepdropService::barriosResponse([]);
        $this->assertSame('', $result['selected']);
        $this->assertSame([], $result['output']);
    }
}
