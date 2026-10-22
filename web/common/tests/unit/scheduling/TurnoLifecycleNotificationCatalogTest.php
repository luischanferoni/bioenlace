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

        $this->assertSame('Consulta', $catalog->reminderServiceFallback());
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
        $this->assertSame(
            [
                'title' => 'Demora posible',
                'body' => 'Tu turno podría demorar ~30 min por un sobreturno.',
            ],
            $catalog->scheduledOverbookDelay(30)
        );
        $this->assertSame(
            [
                'title' => 'Recordatorio de turno',
                'body' => "Cardiología — 2026-10-22 09:30\nCalle 1",
                'transport_hint' => 'Consultá en la app "Cómo llegar" o colectivos según tu ubicación (datos de transporte público pendiente de integración).',
            ],
            $catalog->reminder('Cardiología', '2026-10-22', '09:30', 'Calle 1')
        );
    }
}
