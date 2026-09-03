<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;
use common\components\Platform\Assistant\Preprocess\PreprocessExtractionCategoryCatalog;

class PreprocessExtractionCategoryCatalogTest extends Unit
{
    protected function _before(): void
    {
        ChatPreprocessService::resetCacheForTests();
    }

    public function testCatalogIncludesHintEntitiesAndClinicalCategories(): void
    {
        $all = PreprocessExtractionCategoryCatalog::all();

        $this->assertContains('servicio', $all);
        $this->assertContains('efector', $all);
        $this->assertContains('profesional', $all);
        $this->assertContains('persona', $all);
        $this->assertContains('acto', $all);
        $this->assertContains('sintoma', $all);
        $this->assertContains('medicamento', $all);
    }

    public function testInvalidCategoryIsDroppedOnNormalize(): void
    {
        $out = ChatPreprocessService::normalizeFromAi([
            'routing_hint' => 'clara',
            'normalized_text' => 'turno con cardiólogo',
            'extractions' => [
                ['span' => 'cardiólogo', 'category' => 'profesional', 'synonyms' => []],
                ['span' => 'invalida', 'category' => 'categoria_inventada', 'synonyms' => []],
            ],
        ], 'turno con cardiólogo');

        $this->assertCount(1, $out['extractions']);
        $this->assertSame('profesional', $out['extractions'][0]['category']);
    }

    public function testStablePromptIncludesSingleCategoryList(): void
    {
        $prefix = ChatPreprocessService::stablePromptPrefix();

        $this->assertStringContainsString('- servicio —', $prefix);
        $this->assertStringContainsString('- acto —', $prefix);
        $this->assertStringContainsString('- incompletas —', $prefix);
        $this->assertStringContainsString('llegar_tarde', $prefix);
        $this->assertStringNotContainsString('categories_json', $prefix);
        $this->assertStringNotContainsString('categories_human', $prefix);
    }
}
