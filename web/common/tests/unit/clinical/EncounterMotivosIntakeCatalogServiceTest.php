<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Service\EncounterJourney\EncounterMotivosIntakeCatalogService;

final class EncounterMotivosIntakeCatalogServiceTest extends Unit
{
    protected function _after(): void
    {
        EncounterMotivosIntakeCatalogService::resetCacheForTests();
    }

    public function testChatGuideEnabledWithDefaultQuestions(): void
    {
        $catalog = new EncounterMotivosIntakeCatalogService();
        $this->assertTrue($catalog->isEnabled());
        $this->assertTrue($catalog->isChatPresentation());

        $guide = $catalog->buildChatGuide(null);
        $this->assertIsArray($guide);
        $this->assertStringContainsString('principal motivo', (string) ($guide['message'] ?? ''));
        $this->assertStringContainsString('un solo mensaje', (string) ($guide['message'] ?? ''));
    }

    public function testChatGuideAppendsVariantForTriageCode(): void
    {
        $catalog = new EncounterMotivosIntakeCatalogService();
        $guide = $catalog->buildChatGuide('zona_pecho');
        $this->assertIsArray($guide);
        $message = (string) ($guide['message'] ?? '');
        $this->assertStringContainsString('pecho', $message);
    }
}
