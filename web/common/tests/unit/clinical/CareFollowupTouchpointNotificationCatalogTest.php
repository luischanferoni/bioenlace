<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\CareCohort\Application\Catalog\CareFollowupTouchpointNotificationCatalog;

final class CareFollowupTouchpointNotificationCatalogTest extends Unit
{
    protected function _after(): void
    {
        CareFollowupTouchpointNotificationCatalog::resetCacheForTests();
    }

    public function testReturnsFallbackTitleAndAudienceSpecificBodies(): void
    {
        $catalog = new CareFollowupTouchpointNotificationCatalog();

        $this->assertSame('Seguimiento de tu atención', $catalog->fallbackTitle());
        $this->assertSame(
            'Contanos cómo te sentís y revisá las recomendaciones de tu consulta.',
            $catalog->bodyForRecipient(true, 'Ana')
        );
        $this->assertSame(
            'Seguimiento de la atención de Ana: completá el formulario de evolución.',
            $catalog->bodyForRecipient(false, 'Ana')
        );
    }
}
