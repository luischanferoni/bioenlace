<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\ControlSeguimientoHubService;

class ControlSeguimientoHubServiceTest extends Unit
{
    protected function _before(): void
    {
        ControlSeguimientoHubService::resetCacheForTests();
    }

    public function testHubSiempreIncluyeFallbackGeneral(): void
    {
        $svc = new ControlSeguimientoHubService();
        $items = $svc->listHubItems(0);
        $ids = array_column($items, 'id');
        $this->assertContains(ControlSeguimientoHubService::ANCHOR_GENERAL, $ids);
        $this->assertContains(ControlSeguimientoHubService::ANCHOR_CONSULTA_GENERAL, $ids);
        $this->assertContains(ControlSeguimientoHubService::ANCHOR_CONSULTA_PREVIA, $ids);
    }

    public function testApplyAnchorCarePlan(): void
    {
        $svc = new ControlSeguimientoHubService();
        $draft = ['control_hub_anchor' => 'cp:42'];
        $svc->applyAnchorToDraft($draft);
        $this->assertSame('42', $draft['care_plan_id'] ?? null);
        $this->assertSame('seguimiento', $draft['intake_tipo'] ?? null);
        $this->assertSame('care_plan', $draft['control_hub_kind'] ?? null);
        $this->assertSame('seguimiento_cronico', $draft['triage_raiz'] ?? null);
    }

    public function testApplyAnchorPrefillsFromCarePlanId(): void
    {
        $svc = new ControlSeguimientoHubService();
        $draft = ['care_plan_id' => '7'];
        $svc->applyAnchorToDraft($draft);
        $this->assertSame('cp:7', $draft['control_hub_anchor'] ?? null);
        $this->assertSame('care_plan', $draft['control_hub_kind'] ?? null);
    }

    public function testConditionDefaultActionsDesdeMetadata(): void
    {
        $svc = new ControlSeguimientoHubService();
        $actions = $svc->conditionDefaultActions();
        $codes = array_column($actions, 'code');
        $this->assertContains('consulta_mensaje', $codes);
        $this->assertContains('solicitar_turno', $codes);
    }
}
