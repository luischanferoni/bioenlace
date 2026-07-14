<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\CareCohort\Service\CareFollowupSchedulerService;
use common\components\Domain\Clinical\Service\ServiceRequestService;
use common\models\ConsultaPracticas;

class CareFollowupTouchpointsNormalizeTest extends Unit
{
    public function testEnsureMinTouchpointsFillsDefaults(): void
    {
        $svc = new CareFollowupSchedulerService();
        $out = $svc->ensureMinTouchpoints([]);
        $this->assertGreaterThanOrEqual(2, count($out));
        $this->assertArrayHasKey('delay_days', $out[0]);
        $this->assertArrayHasKey('delay_days', $out[1]);
    }

    public function testEnsureMinTouchpointsAppliesControlDelay(): void
    {
        $svc = new CareFollowupSchedulerService();
        $out = $svc->ensureMinTouchpoints([
            ['delay_days' => 2, 'title' => 'Temp', 'form_kind' => 'evolution_short'],
        ], 15);
        $this->assertGreaterThanOrEqual(2, count($out));
        $last = $out[count($out) - 1];
        $this->assertSame(15, (int) $last['delay_days']);
    }

    public function testResolvePlazoDiasFromPromptCampos(): void
    {
        $campos = (new ConsultaPracticas())->requeridosPrompt();
        $this->assertNotEmpty($campos);
        $row = [
            $campos[0] => 'Control en consultorio',
            $campos[1] => '15',
        ];
        $this->assertSame(15, ServiceRequestService::resolvePlazoDias($row, $campos[1]));
    }
}
