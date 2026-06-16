<?php

namespace common\tests\unit\core\Permission;

use Codeception\Test\Unit;
use common\components\Platform\Core\Permission\IntentManifestIndex;
use common\components\Platform\Core\Permission\IntentMetricIndex;

class IntentMetricIndexTest extends Unit
{
    protected function _before(): void
    {
        IntentManifestIndex::resetCache();
        IntentMetricIndex::resetCache();
    }

    public function testBindsProfesionalesMetrics(): void
    {
        $this->assertSame(
            'profesionales.conteo-efector',
            IntentMetricIndex::intentForMetric('profesionales_conteo_efector')
        );
        $this->assertSame(
            'profesionales.listado-efector',
            IntentMetricIndex::intentForMetric('profesionales_listado_efector')
        );
        $this->assertSame(
            'profesionales_conteo_efector',
            IntentMetricIndex::metricForIntent('profesionales.conteo-efector')
        );
    }
}
