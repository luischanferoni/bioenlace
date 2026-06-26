<?php

namespace common\tests\unit\agent;

use common\components\Domain\Clinical\Inpatient\Service\InternacionCamaSugerenciaService;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\tests\unit\DbTestCase;

class InternacionCamaSugerenciaServiceTest extends DbTestCase
{
    protected function _before(): void
    {
        parent::_before();
        AutonomousAgentMetadata::resetCacheForTests();
    }

    public function testRequirementsFromGuardiaDetectsOxigeno(): void
    {
        $svc = new InternacionCamaSugerenciaService();
        $guardia = new \common\models\Guardia();
        $guardia->condiciones_derivacion = 'Requiere oxígeno continuo';

        $req = $svc->requirementsFromGuardia($guardia, null);

        $this->assertTrue($req['respirador']);
    }
}
