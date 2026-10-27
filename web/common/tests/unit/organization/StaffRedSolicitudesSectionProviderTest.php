<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Efector\Application\Presentation\StaffRedSolicitudesSectionProvider;

class StaffRedSolicitudesSectionProviderTest extends Unit
{
    public function testNonNetworkRoleGetsEmptySection(): void
    {
        $out = (new StaffRedSolicitudesSectionProvider())->build([]);

        $this->assertSame(['title' => '', 'items' => []], $out);
    }
}
