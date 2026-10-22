<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Scheduling\Agenda\Application\Catalog\ReservaTriagePostCupoNotificationCatalog;
use PHPUnit\Framework\TestCase;

final class ReservaTriagePostCupoNotificationCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        ReservaTriagePostCupoNotificationCatalog::resetCacheForTests();
    }

    public function testLoadsExistingPushCopyAndMessagePlaceholder(): void
    {
        self::assertSame([
            'title' => 'Alternativas de atención',
            'body_template' => '{{mensaje}}',
        ], (new ReservaTriagePostCupoNotificationCatalog())->push());
    }
}
