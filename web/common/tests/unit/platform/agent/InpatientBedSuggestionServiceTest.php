<?php

namespace common\tests\unit\platform\agent;

use common\components\Domain\Clinical\Inpatient\Application\Service\InpatientBedSuggestionService;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\tests\unit\DbTestCase;

class InpatientBedSuggestionServiceTest extends DbTestCase
{
    protected function _before(): void
    {
        parent::_before();
        AutonomousAgentMetadata::resetCacheForTests();
    }

    public function testRequirementsFromGuardiaDetectsOxigeno(): void
    {
        $svc = new InpatientBedSuggestionService();
        $guardia = new \common\models\Clinical\Emergency\EmergencyEpisode();
        $guardia->condiciones_derivacion = 'Requiere oxígeno continuo';

        $req = $svc->requirementsFromGuardia($guardia, null);

        $this->assertTrue($req['respirador']);
    }
}
