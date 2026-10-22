<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Encounter\Application\Catalog\EncounterPatientSummaryNotificationCatalog;

final class EncounterPatientSummaryNotificationCatalogTest extends Unit
{
    protected function _after(): void
    {
        EncounterPatientSummaryNotificationCatalog::resetCacheForTests();
    }

    public function testReturnsPatientAndRepresentativeSummaryCopy(): void
    {
        $catalog = new EncounterPatientSummaryNotificationCatalog();

        $this->assertSame('Resumen de atención disponible', $catalog->title());
        $this->assertSame(
            'Consultá qué indicó el profesional y tus próximos pasos.',
            $catalog->bodyForPatient()
        );
        $this->assertSame(
            'El resumen de atención de Ana está listo.',
            $catalog->bodyForRepresentative('Ana')
        );
    }
}
