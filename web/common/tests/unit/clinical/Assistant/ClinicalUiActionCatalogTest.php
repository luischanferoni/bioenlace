<?php

namespace common\tests\unit\clinical\Assistant;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Assistant\ClinicalUiActionCatalog;
use common\components\Platform\Assistant\Catalog\UiActionCatalogProviderRegistry;

class ClinicalUiActionCatalogTest extends Unit
{
    public function testClientOpenForHomePanelIsNativeWeb(): void
    {
        $co = ClinicalUiActionCatalog::clientOpenForActionId('clinical.home.panel');
        $this->assertNotNull($co);
        $this->assertSame('native', $co['kind'] ?? null);
        $this->assertSame('/site/index', $co['web']['path'] ?? null);

        $fromRegistry = UiActionCatalogProviderRegistry::clientOpenForActionId('clinical.home.panel');
        $this->assertNotNull($fromRegistry);
        $this->assertSame('native', $fromRegistry['kind'] ?? null);
    }
}
