<?php

namespace common\tests\unit\permission;

use Codeception\Test\Unit;
use common\components\Platform\Core\Permission\FlowStepAccessService;
use common\components\Platform\Core\Permission\IntentManifestIndex;

/**
 * Pasos open_ui de flows deben indexarse para autorización vía intent padre.
 */
final class FlowStepAccessServiceTest extends Unit
{
    protected function _before(): void
    {
        IntentManifestIndex::resetCache();
        FlowStepAccessService::resetRouteIndexForTests();
    }

    public function testAdherenciaStaffOpenUiRouteResolvesInIndex(): void
    {
        $svc = new FlowStepAccessService();
        $ref = new \ReflectionClass(FlowStepAccessService::class);
        $method = $ref->getMethod('actionIdsForRoute');
        $method->setAccessible(true);

        $ids = $method->invoke(
            $svc,
            '/api/v1/clinical/care-plans/adherencia-resumen-staff'
        );

        $this->assertContains('clinical.care-plan.adherencia-resumen-staff', $ids);
    }

    public function testAdherenciaStaffFlowRegistersOpenUiAction(): void
    {
        $parents = IntentManifestIndex::parentIntentsForOpenUiAction('clinical.care-plan.adherencia-resumen-staff');

        $this->assertNotEmpty($parents);
        $intentIds = array_map(static fn (array $row): string => (string) ($row['intent_id'] ?? ''), $parents);
        $this->assertContains('tratamiento.adherencia-resumen-staff', $intentIds);
    }

    public function testIndicadoresAgendaFlowUsesHomePanelRbacRoute(): void
    {
        $meta = IntentManifestIndex::get('turnos.indicadores-agenda-flow');

        $this->assertIsArray($meta);
        $this->assertSame('/api/home/panel', $meta['rbac_route'] ?? null);
    }

    public function testVerificarTutelaStaffFlowSubmitRouteResolvesInIndex(): void
    {
        $svc = new FlowStepAccessService();
        $ref = new \ReflectionClass(FlowStepAccessService::class);
        $method = $ref->getMethod('actionIdsForRoute');
        $method->setAccessible(true);

        $ids = $method->invoke(
            $svc,
            '/api/person-representation/verificar-vinculo-para-staff'
        );

        $this->assertContains('person-representation.verificar-vinculo-para-staff', $ids);
    }

    public function testVerificarTutelaStaffFlowRegistersFlowSubmitAction(): void
    {
        $parents = IntentManifestIndex::parentIntentsForFlowSubmitAction(
            'person-representation.verificar-vinculo-para-staff'
        );

        $this->assertNotEmpty($parents);
        $intentIds = array_map(static fn (array $row): string => (string) ($row['intent_id'] ?? ''), $parents);
        $this->assertContains('personas.verificar-tutela-staff-flow', $intentIds);
    }
}
