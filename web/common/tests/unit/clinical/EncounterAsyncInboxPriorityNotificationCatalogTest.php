<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Scheduling\Agenda\Application\Catalog\EncounterAsyncInboxPriorityNotificationCatalog;
use PHPUnit\Framework\TestCase;

final class EncounterAsyncInboxPriorityNotificationCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        EncounterAsyncInboxPriorityNotificationCatalog::resetCacheForTests();
    }

    public function testLoadsStaffSlaPushCopyAndPlaceholders(): void
    {
        self::assertSame([
            'title' => 'Plazo vencido — consulta clínica por mensaje',
            'body' => '{{paciente}} · {{servicio}}. Responder cuanto antes.',
        ], (new EncounterAsyncInboxPriorityNotificationCatalog())->slaEscalationPush());
    }
}
