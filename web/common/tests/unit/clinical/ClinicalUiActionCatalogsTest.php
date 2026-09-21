<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Home\Domain\Catalog\HomeUiActionCatalog;
use common\components\Platform\Assistant\Catalog\UiActionCatalogProviderRegistry;

class ClinicalUiActionCatalogsTest extends Unit
{
    public function testClientOpenForHomePanelIsNativeWeb(): void
    {
        $co = HomeUiActionCatalog::clientOpenForActionId('clinical.home.panel');
        $this->assertNotNull($co);
        $this->assertSame('native', $co['kind'] ?? null);
        $this->assertSame('/site/index', $co['web']['path'] ?? null);

        $fromRegistry = UiActionCatalogProviderRegistry::clientOpenForActionId('clinical.home.panel');
        $this->assertNotNull($fromRegistry);
        $this->assertSame('native', $fromRegistry['kind'] ?? null);
    }

    public function testSplitCatalogsRegisteredAndNonEmpty(): void
    {
        $classes = UiActionCatalogProviderRegistry::allProviderClasses();
        $this->assertContains(HomeUiActionCatalog::class, $classes);

        $ids = [];
        foreach (UiActionCatalogProviderRegistry::discoverAllFromProviders() as $def) {
            $id = trim((string) ($def['action_id'] ?? ''));
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        $this->assertArrayHasKey('clinical.home.panel', $ids);
        $this->assertArrayHasKey('clinical.encounter.analizar', $ids);
        $this->assertArrayHasKey('clinical.care-plan.active', $ids);
        $this->assertArrayHasKey('clinical.internacion.mapa-camas', $ids);
    }
}
