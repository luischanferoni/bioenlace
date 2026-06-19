<?php

namespace common\tests\unit\core\Permission;

use Codeception\Test\Unit;
use common\components\Platform\Core\Permission\FlowStepAccessService;
use common\components\Platform\Core\Permission\IntentManifestIndex;
use common\components\Platform\Core\Permission\IntentRequestContextService;

class IntentRequestContextServiceTest extends Unit
{
    protected function _before(): void
    {
        IntentManifestIndex::resetCache();
    }

    public function testResolvesDefaultPilotIntent(): void
    {
        $intentId = (new IntentRequestContextService())->resolveIntentId(
            [],
            'condicion-laboral.editar-propio'
        );

        $this->assertSame('condicion-laboral.editar-propio', $intentId);
    }

    public function testResolvesIntentFromBodyOverDefault(): void
    {
        $intentId = (new IntentRequestContextService())->resolveIntentId(
            ['intent_id' => 'condicion-laboral.editar-staff'],
            'condicion-laboral.editar-propio'
        );

        $this->assertSame('condicion-laboral.editar-staff', $intentId);
    }

    public function testDomainOperationFromPilotIntent(): void
    {
        $operation = (new IntentRequestContextService())->domainOperationForIntent('condicion-laboral.editar-propio');
        $this->assertSame('ProfesionalEfectorServicio.condicion_laboral_own', $operation);

        $staffOp = (new IntentRequestContextService())->domainOperationForIntent('condicion-laboral.editar-staff');
        $this->assertSame('ProfesionalEfectorServicio.condicion_laboral_staff', $staffOp);
    }

    public function testDomainOperationFromLicenciaFlowIntents(): void
    {
        $own = (new IntentRequestContextService())->domainOperationForIntent('licencia.cargar-como-profesional-flow');
        $this->assertSame('ProfesionalEfectorServicio.condicion_laboral_own', $own);

        $staff = (new IntentRequestContextService())->domainOperationForIntent('licencia.cargar-para-profesional-flow');
        $this->assertSame('ProfesionalEfectorServicio.condicion_laboral_staff', $staff);
    }

    public function testFlowIntentHeaderConstant(): void
    {
        $this->assertSame('X-Flow-Intent-Id', FlowStepAccessService::HEADER_FLOW_INTENT_ID);
    }
}
