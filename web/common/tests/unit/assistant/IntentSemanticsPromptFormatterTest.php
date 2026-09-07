<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\IntentSemanticsPromptFormatter;
use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;

class IntentSemanticsPromptFormatterTest extends Unit
{
    protected function _after(): void
    {
        SmartCatalogRegistry::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
    }

    public function testTurnosCrearIncludesObjectiveAndSteps(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('turnos.crear-como-paciente');

        $this->assertStringContainsString('objective:', $block);
        $this->assertStringContainsString('kind: multi-step flow', $block);
        $this->assertStringContainsString('select_servicio:', $block);
        $this->assertStringContainsString('select_slot:', $block);
    }

    public function testAtencionUsesOutlineForBranchingFlow(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('atencion.necesito-atencion');

        $this->assertStringContainsString('objective:', $block);
        $this->assertStringContainsString('outline:', $block);
        $this->assertStringContainsString('kind: multi-step flow', $block);
    }
}
