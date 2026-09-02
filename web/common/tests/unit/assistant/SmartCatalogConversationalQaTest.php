<?php

namespace common\tests\unit\assistant;

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
            'sintoma_panza' => [
                'me duele la panza',
                [
                    'routing_hint' => 'clara',
                    'tags' => ['sintoma', 'dolor', 'necesito_atencion'],
                    'context_areas' => [],
                ],
                ['clara'],
            ],
            'turno_cardiologo' => [
                'quiero un turno con el cardiologo',
                [
                    'routing_hint' => 'clara',
                    'tags' => ['sacar_turno', 'turno'],
                    'context_areas' => ['appointments'],
                ],
                ['clara'],
            ],
            'mis_turnos' => [
                'cuales son mis turnos',
                [
                    'routing_hint' => 'clara',
                    'tags' => ['mis_turnos', 'appointments'],
                    'context_areas' => ['appointments'],
                ],
                ['clara', 'directo'],
            ],
            'llegar_tarde' => [
                'llego 10 min tarde hay problema',
                [
                    'routing_hint' => 'incompletas',
                    'tags' => ['llegar_tarde', 'appointments'],
                    'context_areas' => ['appointments'],
                ],
                ['incompletas'],
            ],
            'representacion' => [
                'contame representacion puedo operar por mi sobrino',
                [
                    'routing_hint' => 'directo',
                    'tags' => ['representacion', 'tutela'],
                    'context_areas' => ['representation'],
                ],
                ['directo', 'dudosa', 'incompletas'],
            ],
            'fuera_his_medium' => [
                'necesito una sesion con una medium',
                [
                    'routing_hint' => 'fuera_de_his',
                    'tags' => ['fuera_his'],
                    'context_areas' => [],
                ],
                ['fuera_de_his'],
            ],
            'saludo' => [
                'hola',
                [
                    'routing_hint' => 'dudosa',
                    'tags' => [],
                    'context_areas' => [],
                ],
                ['dudosa'],
            ],
            'listar_profesionales' => [
                'listar profesionales del centro',
                [
                    'routing_hint' => 'clara',
                    'tags' => ['listar_profesionales', 'staff'],
                    'context_areas' => ['geo_resources'],
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
