<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Service\CarePlanMedicationListService;

class CarePlanMedicationListServiceTest extends Unit
{
    public function testParseIdsDesdeCsvYArray(): void
    {
        $this->assertSame([12, 34], CarePlanMedicationListService::parseIds('12,34'));
        $this->assertSame([12, 34], CarePlanMedicationListService::parseIds(['12', '34', '12']));
        $this->assertSame([], CarePlanMedicationListService::parseIds(''));
        $this->assertSame([], CarePlanMedicationListService::parseIds(null));
    }
}
