<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\AssistantShortcutGroupRegistry;
use common\components\Platform\Assistant\Catalog\AssistantShortcutGroupLabels;

class AssistantShortcutGroupRegistryTest extends Unit
{
    protected function _after(): void
    {
        AssistantShortcutGroupRegistry::resetCacheForTests();
        AssistantShortcutGroupLabels::resetCacheForTests();
    }

    public function testYamlFallbackProvidesUrgenciasLabel(): void
    {
        $yaml = AssistantShortcutGroupRegistry::loadFromYamlFallback();
        $this->assertSame('Urgencias / guardia', $yaml['labels']['urgencias'] ?? null);
        $this->assertContains('urgencias', $yaml['order']);
    }

    public function testLabelsFromYamlFallbackWhenAvailable(): void
    {
        $fallback = AssistantShortcutGroupRegistry::loadFromYamlFallback();
        $this->assertNotEmpty($fallback['labels']);
        $this->assertContains('urgencias', $fallback['order']);
    }
}
