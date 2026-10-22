<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Clinical\HistoryExchange\Application\Catalog\IntegrationRetryNotificationCatalog;
use PHPUnit\Framework\TestCase;

final class IntegrationRetryNotificationCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        IntegrationRetryNotificationCatalog::resetCacheForTests();
    }

    public function testLoadsOperationsAlertCopyAndPlaceholders(): void
    {
        self::assertSame([
            'title' => 'Integración fallida',
            'body_template' => '{{connector}} job #{{job_id}}: {{message}}',
        ], (new IntegrationRetryNotificationCatalog())->opsAlert());
    }
}
