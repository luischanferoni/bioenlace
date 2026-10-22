<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Scheduling\Agenda\Application\Agents\TurnoResolucionShortlistAgent;
use common\components\Domain\Scheduling\Agenda\Application\Catalog\TurnoResolucionShortlistNotificationCatalog;
use PHPUnit\Framework\TestCase;

final class TurnoResolucionShortlistNotificationCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        TurnoResolucionShortlistNotificationCatalog::resetCacheForTests();
    }

    public function testFormatsSuggestedOptionsUsingConfiguredSeparatorAndPrefix(): void
    {
        $agent = new TurnoResolucionShortlistAgent();

        self::assertSame(
            ' Opciones sugeridas: martes 10:00; miércoles 11:30',
            $agent->formatPushBodySuffix([
                ['label' => 'martes 10:00'],
                ['label' => 'miércoles 11:30'],
            ])
        );
    }

    public function testDoesNotLoadCopyWhenThereAreNoOptions(): void
    {
        self::assertSame('', (new TurnoResolucionShortlistAgent())->formatPushBodySuffix([]));
    }
}
