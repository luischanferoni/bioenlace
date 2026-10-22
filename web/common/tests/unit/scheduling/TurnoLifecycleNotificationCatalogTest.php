<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Agenda\Application\Catalog\TurnoLifecycleNotificationCatalog;

final class TurnoLifecycleNotificationCatalogTest extends Unit
{
    protected function _after(): void
    {
        TurnoLifecycleNotificationCatalog::resetCacheForTests();
    }

    public function testRendersProviderCancellationAndOverbookDelayCopy(): void
    {
        $catalog = new TurnoLifecycleNotificationCatalog();

        $this->assertSame(
            [
                'title' => 'Turno cancelado por el consultorio',
                'body' => 'Tu turno del 2026-10-22 fue cancelado.',
            ],
            $catalog->cancelledByProvider('2026-10-22')
        );
        $this->assertSame(
            [
                'title' => 'Posible demora en tu turno',
                'body' => 'Se agregó un sobreturno urgente; tu horario podría retrasarse aprox. 20 min.',
            ],
            $catalog->overbookDelay(20)
        );
    }
}
