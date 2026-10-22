<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Agenda\Application\Catalog\TurnoResolutionNotificationCatalog;

final class TurnoResolutionNotificationCatalogTest extends Unit
{
    protected function _after(): void
    {
        TurnoResolutionNotificationCatalog::resetCacheForTests();
    }

    public function testRendersRelocationNotificationCopy(): void
    {
        $catalog = new TurnoResolutionNotificationCatalog();

        $this->assertSame('Tu turno requiere una nueva cita', $catalog->requiresRelocationTitle());
        $this->assertSame(
            'El profesional registró una licencia. Tocá esta notificación para cambiar el horario del turno 22/10/2026 a las 09:30.',
            $catalog->requiresRelocationBody([
                'reason' => 'El profesional registró una licencia.',
                'date' => '22/10/2026',
                'time' => '09:30',
            ])
        );
    }
}
