<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\DiscoveryIndex;
use common\components\Platform\Assistant\Catalog\IntentSchemaPaths;
use common\components\Platform\Assistant\Catalog\IntentSemanticsPromptFormatter;
use common\components\Platform\Assistant\Catalog\StateTagIndex;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideChannelConfig;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuidePromptAssembler;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;

class DiscoveryIndexTest extends Unit
{
    protected function _before(): void
    {
        StateTagIndex::resetCacheForTests();
        IntentSchemaPaths::resetIndexCache();
        GuideChannelConfig::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
    }

    protected function _after(): void
    {
        StateTagIndex::resetCacheForTests();
        IntentSchemaPaths::resetIndexCache();
        GuideChannelConfig::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
    }

    public function testMatchFindsIntentByStateTags(): void
    {
        $result = DiscoveryIndex::match([
            'normalized_text' => 'me duele el pecho',
            'tags' => ['sintomas', 'dolor'],
            'routing_hint' => 'guide',
        ]);

        $this->assertFalse($result->isEmpty());
        $this->assertContains('atencion.necesito-atencion', $result->intentIds());
        $semantics = IntentSemanticsPromptFormatter::formatStateHits($result->intentHits);
        $this->assertNotSame('', $semantics);
        $this->assertStringContainsString('Solicitar Atención', $semantics);
    }

    public function testIncompletePromptUsesDiscoveryIntentSemantics(): void
    {
        $prompt = GuidePromptAssembler::buildForIncomplete(
            [
                'necesidad_usuario' => 'Quiere una ecografía.',
                'normalized_text' => 'necesito una ecografia',
                'tags' => ['estudio', 'solicitud'],
                'context_areas' => ['clinical'],
            ],
            'necesito una ecografia',
            0,
            '',
            ''
        );

        $this->assertStringContainsString('Solicitar Atención', $prompt);
        $this->assertStringNotContainsString('{intent_semantics}', $prompt);
    }
}
