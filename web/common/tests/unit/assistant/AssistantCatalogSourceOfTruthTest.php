<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use common\components\Platform\Assistant\Context\AssistantContextHISAreaAspect;
use common\components\Platform\Assistant\Preprocess\PreprocessExtractionCategoryCatalog;
use common\components\Platform\Assistant\Preprocess\PreprocessRoutingHintCatalog;
use common\components\Platform\Assistant\Preprocess\PreprocessTagVocabularyCatalog;
use common\components\Platform\Assistant\Service\HintResolutionMetadata;
use ReflectionClass;

class AssistantCatalogSourceOfTruthTest extends Unit
{
    protected function _before(): void
    {
        ChatPreprocessService::resetCacheForTests();
    }

    public function testHisAreaConstantsExistInCatalog(): void
    {
        $ref = new ReflectionClass(AssistantContextHISArea::class);
        foreach ($ref->getConstants() as $id) {
            if (!is_string($id)) {
                continue;
            }
            $this->assertTrue(
                AssistantContextHISArea::isValid($id),
                'Constante HIS sin entrada en context-his-areas.yaml: ' . $id
            );
        }
    }

    public function testAspectConstantsExistInCatalog(): void
    {
        $ref = new ReflectionClass(AssistantContextHISAreaAspect::class);
        foreach ($ref->getConstants() as $aspect) {
            if (!is_string($aspect)) {
                continue;
            }
            $this->assertTrue(
                AssistantContextHISAreaAspect::isValid($aspect),
                'Constante aspecto sin entrada en area-aspects.yaml: ' . $aspect
            );
        }
    }

    public function testHintEntitiesAreExtractionCategories(): void
    {
        foreach (['servicio', 'efector', 'profesional', 'persona'] as $entity) {
            $this->assertContains(
                $entity,
                PreprocessExtractionCategoryCatalog::all(),
                'entity_ownership sin categoría preprocess: ' . $entity
            );
            $this->assertNotEmpty(HintResolutionMetadata::providerKeysForEntity($entity));
        }
    }

    public function testPreprocessTagsDerivedFromSmartCatalog(): void
    {
        $tags = PreprocessTagVocabularyCatalog::all();
        $this->assertContains('llegar_tarde', $tags);
        $this->assertContains('in_flow_question', $tags);
    }

    public function testRoutingHintsLoadedFromCatalog(): void
    {
        $this->assertSame(
            PreprocessRoutingHintCatalog::all(),
            ChatPreprocessService::routingHints()
        );
    }

    public function testRoutingHintConstantsExistInCatalog(): void
    {
        $ref = new ReflectionClass(PreprocessRoutingHintCatalog::class);
        foreach ($ref->getConstants() as $name => $id) {
            if (!is_string($id) || str_starts_with((string) $name, 'TAG_')) {
                continue;
            }
            $this->assertTrue(
                PreprocessRoutingHintCatalog::isValid($id),
                'Constante routing_hint sin entrada en preprocess-routing-hints.yaml: ' . $id
            );
        }
    }

    public function testRoutingHintTechnicalMapsLiveInPhp(): void
    {
        $this->assertSame(
            PreprocessRoutingHintCatalog::INCOMPLETAS,
            PreprocessRoutingHintCatalog::routingHintFromLegacyGoal('guide')
        );
        $this->assertSame(
            PreprocessRoutingHintCatalog::CLARA,
            PreprocessRoutingHintCatalog::applyAlias('directo')
        );
        $this->assertContains(
            PreprocessRoutingHintCatalog::TAG_IN_FLOW_QUESTION,
            PreprocessRoutingHintCatalog::extraPreprocessTags()
        );
    }
}
