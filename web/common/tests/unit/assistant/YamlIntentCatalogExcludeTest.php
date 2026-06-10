<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Assistant\Catalog\YamlIntentCatalogService;

class YamlIntentCatalogExcludeTest extends Unit
{
    public function testDeprecatedAgendaEditFlowExcludedFromCatalog(): void
    {
        $all = YamlIntentCatalogService::discoverAll(false);
        $ids = array_map(
            static fn (array $item): string => trim((string) ($item['action_id'] ?? '')),
            $all
        );
        $this->assertNotContains('agenda.editar-agenda-flow', $ids);
        $this->assertContains('data-access.editar', $ids);
    }

    public function testDeprecatedYamlStillExistsForSubIntentEngine(): void
    {
        $this->assertTrue(YamlIntentCatalogService::intentExists('agenda.editar-agenda-flow'));
    }
}
