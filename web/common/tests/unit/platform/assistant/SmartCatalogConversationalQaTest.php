<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectCatalog;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingService;

/**
 * Checklist conversacional fase 08 — routing esperado post 1ª IA simulada.
 */
class SmartCatalogConversationalQaTest extends Unit
{
    protected function _after(): void
    {
        SmartCatalogRegistry::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
        AssistantContextAreaAspectCatalog::resetCacheForTests();
        AssistantPlanningLogService::resetForTests();
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: list<string>}>
     */
    protected function conversationalCases(): array
    {
        return [
            'efecto_adverso' => [
                'quiero informar un efecto adverso de una medicacion',
                [
                    'routing_hint' => 'pedido_claro',
                    'tags' => ['sintomas'],
                    'context_areas' => ['medication'],
                    'extractions' => [
                        ['span' => 'efecto adverso', 'synonyms' => []],
                    ],
                ],
                ['clara'],
            ],
            'sintoma_panza' => [
                'me duele la panza',
                [
                    'routing_hint' => 'pedido_claro',
                    'tags' => ['sintomas'],
                    'context_areas' => [],
                    'extractions' => [
                        ['span' => 'panza', 'synonyms' => []],
                    ],
                ],
                ['clara'],
            ],
            'turno_cardiologo' => [
                'quiero un turno con el cardiologo',
                [
                    'routing_hint' => 'pedido_claro',
                    'tags' => ['turno'],
                    'context_areas' => ['scheduling'],
                    'extractions' => [
                        ['span' => 'cardiólogo', 'synonyms' => []],
                    ],
                ],
                ['clara'],
            ],
            'turno_bare' => [
                'quiero un turno',
                [
                    'routing_hint' => 'pedido_claro',
                    'tags' => ['turno'],
                    'context_areas' => ['scheduling'],
                    'extractions' => [
                        ['span' => 'turno', 'synonyms' => []],
                    ],
                ],
                ['clara'],
            ],
            'estudio_ecografia' => [
                'necesito una ecografia',
                [
                    'routing_hint' => 'pedido_claro',
                    'tags' => ['estudio'],
                    'context_areas' => [],
                    'extractions' => [
                        ['span' => 'ecografía', 'synonyms' => []],
                    ],
                ],
                ['clara'],
            ],
            'mis_turnos' => [
                'cuales son mis turnos',
                [
                    'routing_hint' => 'pedido_claro',
                    'tags' => ['mis_turnos'],
                    'context_areas' => ['scheduling'],
                    'extractions' => [
                        ['span' => 'turnos', 'synonyms' => []],
                    ],
                ],
                ['clara'],
            ],
            'llegar_tarde' => [
                'llego 10 min tarde hay problema',
                [
                    'routing_hint' => 'pedido_claro',
                    'tags' => ['llegar_tarde', 'scheduling'],
                    'context_areas' => ['scheduling'],
                ],
                ['incompletas'],
            ],
            'representacion' => [
                'contame representacion puedo operar por mi sobrino',
                [
                    'routing_hint' => 'pedido_claro',
                    'tags' => ['representacion', 'tutela'],
                    'context_areas' => ['person'],
                ],
                ['clara', 'dudosa', 'incompletas'],
            ],
            'fuera_his_medium' => [
                'necesito una sesion con una medium',
                [
                    'routing_hint' => 'pedido_fuera_his',
                    'tags' => ['fuera_his'],
                    'context_areas' => [],
                ],
                ['fuera_de_his'],
            ],
            'saludo' => [
                'hola',
                [
                    'routing_hint' => 'sin_pedido',
                    'tags' => [],
                    'context_areas' => [],
                ],
                ['dudosa'],
            ],
            'listar_profesionales' => [
                'listar profesionales del centro',
                [
                    'routing_hint' => 'pedido_claro',
                    'tags' => ['profesionales'],
                    'context_areas' => ['geo_resources'],
                    'extractions' => [
                        ['span' => 'profesionales', 'synonyms' => []],
                    ],
                ],
                ['clara'],
            ],
        ];
    }

    /**
     * @dataProvider conversationalCases
     * @param list<string> $allowedRouting
     */
    public function testChecklistRouting(string $message, array $firstIaFixture, array $allowedRouting): void
    {
        $preprocess = array_merge([
            'normalized_text' => $message,
            'necesidad_usuario' => $message,
            'extractions' => [],
            'intent_ids_hint' => [],
        ], $firstIaFixture);

        $evaluation = SmartCatalogRoutingService::evaluate($preprocess, 0, $message);
        $result = $evaluation->decision->routingResult;

        $this->assertContains(
            $result,
            $allowedRouting,
            'Mensaje "' . $message . '" → routing ' . $result . '; esperado uno de: ' . implode(', ', $allowedRouting)
        );
    }
}
