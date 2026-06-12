<?php

namespace common\tests\unit\core\DataAccess;

use Codeception\Test\Unit;
use common\components\Core\DataAccess\AttributeGroupCatalog;
use common\components\Core\DataAccess\DataAccessMetricDiscoveryService;

class DataAccessMetricDiscoveryTest extends Unit
{
    public function testMetricSupportsChannel(): void
    {
        $svc = new DataAccessMetricDiscoveryService();
        $this->assertTrue($svc->metricSupportsChannel('profesionales_conteo_efector', DataAccessMetricDiscoveryService::CHANNEL_INFO));
        $this->assertTrue($svc->metricSupportsChannel('profesionales_listado_efector', DataAccessMetricDiscoveryService::CHANNEL_LISTAR));
        $this->assertFalse($svc->metricSupportsChannel('profesionales_listado_efector', DataAccessMetricDiscoveryService::CHANNEL_INFO));
    }

    public function testResolveMetricIdFromAssistantKeywords(): void
    {
        $svc = new DataAccessMetricDiscoveryService();
        $ctx = new \common\components\Core\DataAccess\PermissionContext(0, ['AdminEfector']);
        $id = $svc->resolveMetricId(
            DataAccessMetricDiscoveryService::CHANNEL_INFO,
            '¿Cuántos profesionales hay en el centro?',
            [],
            $ctx
        );
        $this->assertSame('profesionales_conteo_efector', $id);
    }

    public function testAssistantKeywordsOnMetric(): void
    {
        $catalog = new AttributeGroupCatalog();
        $metric = $catalog->getMetric('profesionales_conteo_efector');
        $this->assertIsArray($metric);
        $kw = $metric['keywords'] ?? [];
        $this->assertContains('cuantos profesionales', $kw);
        $this->assertNotContains('oftalmologos', $kw);
    }
}
