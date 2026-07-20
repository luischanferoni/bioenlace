<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\ControlSeguimientoHubService;

class ControlSeguimientoHubServiceTest extends Unit
{
    protected function _before(): void
    {
        ControlSeguimientoHubService::resetCacheForTests();
        \common\components\Domain\Clinical\Service\CareProtocolCatalogService::resetCacheForTests();
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

    public function testConditionActionsPrefierenProtocoloCuandoHayCodigo(): void
    {
        $svc = new ControlSeguimientoHubService();
        $items = $svc->listConditionActionItems('I10');
        $this->assertNotEmpty($items);
        $this->assertSame('protocol', $items[0]['meta']['source'] ?? null);
        $this->assertSame('hta_control_periodico', $items[0]['meta']['protocol_id'] ?? null);
    }

    public function testResolveConditionActionModalidad(): void
    {
        $svc = new ControlSeguimientoHubService();
        $resolved = $svc->resolveConditionAction('E11', 'solicitar_turno');
        $this->assertNotNull($resolved);
        $this->assertSame('modalidad', $resolved['outcome']);
        $this->assertSame('diabetes_control_periodico', $resolved['protocol_id']);
    }
}
