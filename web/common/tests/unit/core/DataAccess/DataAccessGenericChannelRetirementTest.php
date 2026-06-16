<?php

namespace common\tests\unit\core\DataAccess;

use Codeception\Test\Unit;
use common\components\Platform\Core\DataAccess\DataAccessGenericChannelRetirement;
use common\components\Platform\Core\Permission\IntentEditSurfaceIndex;
use common\components\Platform\Core\Permission\IntentManifestIndex;
use common\components\Platform\Core\Permission\IntentMetricIndex;

class DataAccessGenericChannelRetirementTest extends Unit
{
    protected function _before(): void
    {
        IntentManifestIndex::resetCache();
        IntentMetricIndex::resetCache();
        IntentEditSurfaceIndex::resetCache();
    }

    public function testGenericChannelsRetiredAfterPhase3Domains(): void
    {
        $this->assertTrue(DataAccessGenericChannelRetirement::areGenericChannelsRetired());
    }
}
