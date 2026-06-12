<?php

namespace common\tests\unit\core\DataAccess;

use Codeception\Test\Unit;
use common\components\Core\DataAccess\AttributeGroupCatalog;

class DataAccessCatalogTest extends Unit
{
    public function testResolveSexoBiologicoFromMention(): void
    {
        $catalog = new AttributeGroupCatalog();
        $this->assertSame(2, $catalog->resolveSexoBiologicoFromMention('varones'));
        $this->assertSame(1, $catalog->resolveSexoBiologicoFromMention('mujeres'));
        $this->assertNull($catalog->resolveSexoBiologicoFromMention('indefinido'));
    }

    public function testMetricQueryPlanProfesionales(): void
    {
        $catalog = new AttributeGroupCatalog();
        $plan = $catalog->getMetricQueryPlan('profesionales_conteo_efector');
        $this->assertIsArray($plan);
        $this->assertArrayHasKey('root', $plan);
        $this->assertArrayHasKey('filters', $plan);
        $this->assertArrayHasKey('sexo_biologico', $plan['filters']);
    }

    public function testFilterEntityGroupMap(): void
    {
        $catalog = new AttributeGroupCatalog();
        $map = $catalog->filterEntityGroupMap('profesionales_conteo_efector');
        $this->assertSame('Persona.sexo_genero', $map['sexo_biologico'] ?? null);
    }

    public function testMetricOutputPlanListado(): void
    {
        $catalog = new AttributeGroupCatalog();
        $plan = $catalog->getMetricOutputPlan('profesionales_listado_efector');
        $this->assertIsArray($plan);
        $this->assertSame('rows', $plan['default'] ?? null);
        $this->assertArrayHasKey('rows', $plan);
        $rows = $plan['rows'] ?? null;
        $this->assertIsArray($rows);
        $orderBy = $rows['order_by'] ?? null;
        $this->assertIsArray($orderBy);
        $this->assertArrayHasKey('p.apellido', $orderBy);
        $this->assertSame('ASC', $orderBy['p.apellido']);
    }

    public function testMetricOutputPlanGrouped(): void
    {
        $catalog = new AttributeGroupCatalog();
        $plan = $catalog->getMetricOutputPlan('profesionales_conteo_por_servicio_efector');
        $this->assertIsArray($plan);
        $this->assertSame('grouped', $plan['default'] ?? null);
        $this->assertArrayHasKey('modes', $plan);
    }

    public function testLoadsMultiFileConfig(): void
    {
        AttributeGroupCatalog::resetCacheForTests();
        $catalog = new AttributeGroupCatalog();
        $this->assertNotEmpty($catalog->listEntityGroupOptions());
        $this->assertNotNull($catalog->getMetric('profesionales_conteo_efector'));
        $this->assertNotNull($catalog->getEditSurface('profesional_en_efector'));
        $this->assertStringContainsString('data-access-config', AttributeGroupCatalog::configDirectory());
    }
}
