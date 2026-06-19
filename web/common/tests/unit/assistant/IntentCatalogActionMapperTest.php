<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\IntentCatalogActionMapper;

final class IntentCatalogActionMapperTest extends Unit
{
    public function testMapsIntentFlowToDiscoveredAction(): void
    {
        $mapped = IntentCatalogActionMapper::toDiscoveredAction([
            'action_id' => 'tratamiento.adherencia-resumen-staff',
            'action_name' => 'Ver adherencia',
            'description' => 'Dashboard staff',
            'rbac_route' => '/api/clinical/care-plan/active',
            'keywords' => ['adherencia'],
        ]);

        $this->assertSame('tratamiento.adherencia-resumen-staff', $mapped['action_id']);
        $this->assertSame('/api/clinical/care-plan/active', $mapped['route']);
        $this->assertSame('clinical', $mapped['controller']);
        $this->assertSame('care-plan', $mapped['action']);
        $this->assertContains('adherencia', $mapped['tags']);
    }
}
