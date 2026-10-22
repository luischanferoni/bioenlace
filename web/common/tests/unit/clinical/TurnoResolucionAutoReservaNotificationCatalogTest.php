<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Scheduling\Agenda\Application\Agents\TurnoResolucionAutoReservaAgent;
use common\components\Domain\Scheduling\Agenda\Application\Catalog\TurnoResolucionAutoReservaNotificationCatalog;
use PHPUnit\Framework\TestCase;

final class TurnoResolucionAutoReservaNotificationCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        TurnoResolucionAutoReservaNotificationCatalog::resetCacheForTests();
    }

    public function testAutoRebookedPushPreservesCopyAndInterpolatesDateAndTime(): void
    {
        $agent = new TurnoResolucionAutoReservaAgent();

        self::assertSame([
            'title' => 'Te reubicamos el turno',
            'body' => 'Reservamos tu turno para el 2026-10-24 a las 09:30. Si no te sirve, cambiá el horario desde la app.',
        ], $agent->buildAutoRebookedPush([
            'fecha' => '2026-10-24',
            'hora' => '09:30:00',
        ]));
    }
}
