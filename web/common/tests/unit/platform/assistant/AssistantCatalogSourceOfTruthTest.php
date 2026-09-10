<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\IntentSchemaPaths;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use common\components\Platform\Assistant\Context\AssistantContextHISAreaAspect;
use common\components\Platform\Assistant\Preprocess\PreprocessExtractionCategoryCatalog;
use common\components\Platform\Assistant\Preprocess\PreprocessRoutingHintCatalog;
use common\components\Platform\Assistant\Preprocess\PreprocessTagVocabularyCatalog;
use common\components\Platform\Assistant\Service\HintCandidateProviderRegistry;
use common\components\Platform\Assistant\Service\HintResolutionMetadata;
use ReflectionClass;
use Symfony\Component\Yaml\Yaml;

class AssistantCatalogSourceOfTruthTest extends Unit
{
    protected function _before(): void
    {
        ChatPreprocessService::resetCacheForTests();
        PreprocessExtractionCategoryCatalog::resetCacheForTests();
        HintCandidateProviderRegistry::resetForTests();
        IntentSchemaPaths::resetIndexCache();
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
                'Constante aspecto sin entrada en ASPECTS: ' . $aspect
            );
        }
    }

    public function testDeclaredEntitiesHaveYamlText(): void
    {
        $texts = PreprocessExtractionCategoryCatalog::textsFromYaml();
        foreach (HintCandidateProviderRegistry::allDeclaredEntities() as $entity) {
            $this->assertArrayHasKey(
                $entity,
                $texts,
                'Entidad declarada por un HintCandidateProvider sin texto en preprocess-extraction-categories.yaml: ' . $entity
            );
            $this->assertNotSame(
                '',
                trim((string) $texts[$entity]),
                'Texto vacío para entidad de extracción: ' . $entity
            );
            $this->assertTrue(PreprocessExtractionCategoryCatalog::isValid($entity));
            $this->assertNotEmpty(HintResolutionMetadata::providerKeysForEntity($entity));
        }
    }

    public function testYamlExtractionTextsHaveDeclaringDomain(): void
    {
        foreach (array_keys(PreprocessExtractionCategoryCatalog::textsFromYaml()) as $entity) {
            $this->assertContains(
                $entity,
                HintCandidateProviderRegistry::allDeclaredEntities(),
                'Texto huérfano en preprocess-extraction-categories.yaml (ningún provider lo declara): ' . $entity
            );
        }
    }

    public function testExtractionCategoriesDoNotOverlapPreprocessTags(): void
    {
        $tags = array_fill_keys(PreprocessTagVocabularyCatalog::all(), true);
        foreach (PreprocessExtractionCategoryCatalog::all() as $entity) {
            $this->assertArrayNotHasKey(
                $entity,
                $tags,
                'La misma palabra no puede ser entidad de extracción y tag: ' . $entity
            );
        }
    }

    public function testIntentHintEntitiesBelongToCatalog(): void
    {
        $entities = [];
        foreach (IntentSchemaPaths::discoverYamlFiles() as $path) {
            $data = Yaml::parseFile($path);
            if (!is_array($data)) {
                continue;
            }
            self::collectHintEntities($data, $entities);
        }
        $this->assertNotEmpty($entities, 'Se esperaba al menos un hint.entity en los intents');
        foreach (array_keys($entities) as $entity) {
            $this->assertTrue(
                PreprocessExtractionCategoryCatalog::isValid($entity),
                'hint.entity de un intent fuera del catálogo de extracción: ' . $entity
            );
        }
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, true> $out
     */
    private static function collectHintEntities(array $node, array &$out): void
    {
        if (isset($node['hint']) && is_array($node['hint'])) {
            $entity = strtolower(trim((string) ($node['hint']['entity'] ?? '')));
            if ($entity !== '') {
                $out[$entity] = true;
            }
        }
        foreach ($node as $value) {
            if (is_array($value)) {
                self::collectHintEntities($value, $out);
            }
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
            if (!is_string($id) || str_starts_with((string) $name, 'TAG_') || str_starts_with((string) $name, 'PATH_')) {
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
            PreprocessRoutingHintCatalog::PEDIDO_CLARO,
            PreprocessRoutingHintCatalog::routingHintFromLegacyGoal('guide')
        );
        $this->assertContains(
            PreprocessRoutingHintCatalog::TAG_IN_FLOW_QUESTION,
            PreprocessRoutingHintCatalog::extraPreprocessTags()
        );
    }

    public function testLegacyUserGoalDoesNotAliasPhpPathsAsHints(): void
    {
        $this->assertSame(
            'guide',
            PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint(
                PreprocessRoutingHintCatalog::PATH_NEEDS_CONTEXT
            )
        );
        $this->assertSame(
            'operational',
            PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint(
                PreprocessRoutingHintCatalog::PATH_MATCH_DIRECT
            )
        );
        $this->assertSame(
            'operational',
            PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint(
                PreprocessRoutingHintCatalog::PEDIDO_CLARO
            )
        );
        $this->assertSame(
            'guide',
            PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint('incompletas')
        );
    }
}
