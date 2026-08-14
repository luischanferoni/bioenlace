<?php

namespace common\tests\unit\core\DataAccess;

use Codeception\Test\Unit;
use common\components\Platform\Core\DataAccess\AttributeGroupCatalog;
use common\components\Platform\Core\DataAccess\MetricExecutionResult;
use common\components\Platform\Core\DataAccess\Presentation\MetricPresentationRegistry;
use common\components\Platform\Core\DataAccess\QueryOutputMode;

class MetricPresentationRegistryTest extends Unit
{
    protected function _before(): void
    {
        AttributeGroupCatalog::resetCacheForTests();
    }

    public function testGenericGroupedUsesCatalogLabelNotMetricId(): void
    {
        $result = new MetricExecutionResult(
            'profesionales_conteo_por_servicio_efector',
            QueryOutputMode::GROUPED,
            ['count_by_servicio' => 6],
            [],
            [
                ['total' => 6, 'id_servicio' => 7, 'servicio_nombre' => 'MED GENERAL'],
            ],
            [],
            false,
            ['group_count' => 1]
        );

        $params = MetricPresentationRegistry::buildGenericInfoRenderParams($result);

        $this->assertSame('Distribución de profesionales por servicio', $params['info_title']);
        $this->assertStringNotContainsString('profesionales_conteo_por_servicio_efector', $params['info_texto']);
        $this->assertStringContainsString('MED GENERAL: 6', $params['info_texto']);
    }
}
