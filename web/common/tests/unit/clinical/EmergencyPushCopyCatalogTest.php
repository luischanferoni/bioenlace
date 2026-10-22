<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Emergency\Application\Catalog\EmergencyPushCopyCatalog;

final class EmergencyPushCopyCatalogTest extends Unit
{
    protected function _after(): void
    {
        EmergencyPushCopyCatalog::resetCacheForTests();
    }

    public function testRendersAssignedAndCriticalTriageCopy(): void
    {
        $catalog = new EmergencyPushCopyCatalog();

        $this->assertSame(
            [
                'title' => 'Guardia: paciente asignado',
                'body' => 'Te asignaron la atención de Ana Pérez en guardia.',
            ],
            $catalog->assigned('Ana Pérez')
        );
        $this->assertSame(
            [
                'title' => 'Guardia: prioridad inmediata',
                'body' => 'Ana Pérez — Rojo',
            ],
            $catalog->criticalTriage(1, 'Ana Pérez', 'Rojo')
        );
        $this->assertSame(
            [
                'title' => 'Guardia: muy urgente',
                'body' => 'Ana Pérez — Naranja',
            ],
            $catalog->criticalTriage(2, 'Ana Pérez', 'Naranja')
        );
    }
}
