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

    public function testBarriosResponseSinParents(): void
    {
        $result = GeografiaDepdropService::barriosResponse([]);
        $this->assertSame('', $result['selected']);
        $this->assertSame([], $result['output']);
    }
}
