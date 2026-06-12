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
        $this->assertNotContains('profesional-agenda.editar-flow', $ids);
        $this->assertNotContains('data-access.info', $ids);
        $this->assertNotContains('data-access.listar', $ids);
        $this->assertNotContains('data-access.editar', $ids);
    }

    public function testDataAccessActionsNotInYamlCatalog(): void
    {
        $this->assertFalse(YamlIntentCatalogService::intentExists('data-access.info'));
        $this->assertFalse(YamlIntentCatalogService::intentExists('data-access.listar'));
        $this->assertFalse(YamlIntentCatalogService::intentExists('data-access.editar'));
    }

    public function testDeprecatedYamlStillExistsForSubIntentEngine(): void
    {
        $this->assertTrue(YamlIntentCatalogService::intentExists('profesional-agenda.editar-flow'));
    }
}
