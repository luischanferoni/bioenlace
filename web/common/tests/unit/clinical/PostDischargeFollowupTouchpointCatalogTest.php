<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Inpatient\Application\Catalog\PostDischargeFollowupTouchpointCatalog;

final class PostDischargeFollowupTouchpointCatalogTest extends Unit
{
    protected function _after(): void
    {
        PostDischargeFollowupTouchpointCatalog::resetCacheForTests();
    }

    public function testReturnsTitlesForDefaultAndSurgeryPrograms(): void
    {
        $catalog = new PostDischargeFollowupTouchpointCatalog();

        $this->assertSame('Primer día en casa', $catalog->title('default', 0));
        $this->assertSame('Primera semana en casa', $catalog->title('default', 1));
        $this->assertSame('Un mes del alta', $catalog->title('default', 2));
        $this->assertSame('Post cirugía — día 1', $catalog->title('cirugia', 0));
        $this->assertSame('Post cirugía — herida y fiebre', $catalog->title('cirugia', 1));
        $this->assertSame('Control post cirugía', $catalog->title('cirugia', 2));
    }

    public function testMissingTouchpointTitleFailsExplicitly(): void
    {
        $this->expectException(\RuntimeException::class);

        (new PostDischargeFollowupTouchpointCatalog())->title('unknown', 0);
    }
}
