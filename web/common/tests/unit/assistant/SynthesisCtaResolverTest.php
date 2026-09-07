<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingService;
use common\components\Platform\Assistant\Planning\SynthesisCtaResolver;

class SynthesisCtaResolverTest extends Unit
{
    protected function _after(): void
    {
        SmartCatalogRegistry::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
        AssistantPlanningLogService::resetForTests();
    }

    public function testBareTurnoDeclaresDualCtasWithHumanLabels(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'Quiero un turno',
            'user_goal' => 'guide',
            'routing_hint' => 'incompletas',
            'tags' => ['pedido_turno_sin_destino', 'appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ], 1);

        $ids = SynthesisCtaResolver::declaredIntentIds($evaluation);
        $this->assertSame(
            ['turnos.crear-como-paciente', 'atencion.necesito-atencion'],
            $ids
        );

        $buttons = SynthesisCtaResolver::resolveAll($evaluation, 1);
        $this->assertCount(2, $buttons);
        $this->assertSame('turnos.crear-como-paciente', $buttons[0]['intent_id']);
        $this->assertSame('Turno con un especialista', $buttons[0]['label']);
        $this->assertSame('atencion.necesito-atencion', $buttons[1]['intent_id']);
        $this->assertSame('Solicitar Atención', $buttons[1]['label']);
    }

    public function testResolveAllEmptyWhenUserIdZero(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'Quiero un turno',
            'tags' => ['pedido_turno_sin_destino', 'appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ], 0);

        $this->assertSame([], SynthesisCtaResolver::resolveAll($evaluation, 0));
        $this->assertNotEmpty(SynthesisCtaResolver::declaredIntentIds($evaluation));
    }
}
