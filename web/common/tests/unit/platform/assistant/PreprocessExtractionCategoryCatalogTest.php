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

    public function testInvalidCategoryIsDroppedOnNormalize(): void
    {
        $out = ChatPreprocessService::normalizeFromAi([
            'routing_hint' => 'pedido_claro',
            'normalized_text' => 'turno con cardiólogo',
            'extractions' => [
                ['span' => 'cardiólogo', 'category' => 'profesional', 'synonyms' => []],
                ['span' => 'invalida', 'category' => 'categoria_inventada', 'synonyms' => []],
                ['span' => 'dolor', 'category' => 'sintoma', 'synonyms' => []],
            ],
        ], 'turno con cardiólogo');

        $this->assertCount(1, $out['extractions']);
        $this->assertSame('profesional', $out['extractions'][0]['category']);
    }

    public function testStablePromptIncludesResolvableCategoryList(): void
    {
        $prefix = ChatPreprocessService::stablePromptPrefix();

        $this->assertStringContainsString('- servicio —', $prefix);
        $this->assertStringContainsString('- efector —', $prefix);
        $this->assertStringNotContainsString('- acto —', $prefix);
        $this->assertStringNotContainsString('- sintoma —', $prefix);
        $this->assertStringContainsString('- incompletas —', $prefix);
        $this->assertStringContainsString('llegar_tarde', $prefix);
        $this->assertStringNotContainsString('categories_json', $prefix);
        $this->assertStringNotContainsString('categories_human', $prefix);
    }
}
