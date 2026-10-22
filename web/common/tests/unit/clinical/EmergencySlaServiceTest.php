<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Emergency\Application\Service\EmergencySlaService;

class EmergencySlaServiceTest extends Unit
{
    public function testTriageWaitIntentUsesSemanticSeverity(): void
    {
        $service = new EmergencySlaService();

        $this->assertSame('danger', $service->triageEsperaIntent(EmergencySlaService::TRIAGE_ESPERA_ROJO));
        $this->assertSame('warning', $service->triageEsperaIntent(EmergencySlaService::TRIAGE_ESPERA_NARANJA));
        $this->assertSame('neutral', $service->triageEsperaIntent(EmergencySlaService::TRIAGE_ESPERA_GRIS));
    }

    public function testUnknownTriageWaitLevelIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new EmergencySlaService())->triageEsperaIntent('unknown');
    }
}
