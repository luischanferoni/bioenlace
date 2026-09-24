<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;
use common\components\Platform\Assistant\Preprocess\PreprocessExtractionCategoryCatalog;
use common\components\Platform\Assistant\Service\HintCandidateProviderRegistry;

class PreprocessExtractionCategoryCatalogTest extends Unit
{
    protected function _before(): void
    {
        ChatPreprocessService::resetCacheForTests();
        PreprocessExtractionCategoryCatalog::resetCacheForTests();
        HintCandidateProviderRegistry::resetForTests();
    }

    public function testCatalogIsDeclaredHintEntitiesOnly(): void
    {
        $all = PreprocessExtractionCategoryCatalog::all();

        $this->assertSame(
            HintCandidateProviderRegistry::allDeclaredEntities(),
            $all
        );
        $this->assertContains('servicio', $all);
        $this->assertContains('efector', $all);
        $this->assertContains('profesional', $all);
        $this->assertContains('persona', $all);
        $this->assertNotContains('acto', $all);
        $this->assertNotContains('sintoma', $all);
        $this->assertNotContains('medicamento', $all);
        $this->assertNotContains('turno', $all);
        $this->assertNotContains('tiempo', $all);
    }

    public function testNormalizeKeepsSpansWithoutCategory(): void
    {
        $out = ChatPreprocessService::normalizeFromAi([
            'routing_hint' => 'pedido_claro',
            'normalized_text' => 'turno con cardiólogo',
            'tags' => ['turno'],
            'extractions' => [
                ['span' => 'cardiólogo', 'synonyms' => ['cardio']],
                ['span' => '', 'synonyms' => []],
            ],
        ], 'turno con cardiólogo');

        $this->assertSame([
            ['span' => 'cardiólogo', 'synonyms' => ['cardio']],
        ], $out['extractions']);
        $this->assertSame(['turno'], $out['tags']);
    }

    public function testStablePromptAsksRoutingHintNotClosedCategories(): void
    {
        $prefix = ChatPreprocessService::stablePromptPrefix();

        $this->assertStringContainsString('- pedido_claro —', $prefix);
        $this->assertStringNotContainsString('- servicio —', $prefix);
        $this->assertStringNotContainsString('- efector —', $prefix);
        $this->assertStringNotContainsString('categories_json', $prefix);
        $this->assertStringNotContainsString('categories_human', $prefix);
    }
}
