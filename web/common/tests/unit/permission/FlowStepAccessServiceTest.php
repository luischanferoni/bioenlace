<?php

namespace common\tests\unit\permission;

use Codeception\Test\Unit;
use common\components\Core\Permission\IntentManifestIndex;

/**
 * Pasos open_ui de flows deben indexarse para autorización vía intent padre.
 */
final class FlowStepAccessServiceTest extends Unit
{
    protected function _before(): void
    {
        IntentManifestIndex::resetCache();
    }

    public function testIndicadoresAgendaFlowRegistersOpenUiAction(): void
    {
        $parents = IntentManifestIndex::parentIntentsForOpenUiAction('turnos.indicadores-agenda');

        $this->assertNotEmpty($parents);
        $intentIds = array_map(static fn (array $row): string => (string) ($row['intent_id'] ?? ''), $parents);
        $this->assertContains('turnos.indicadores-agenda-flow', $intentIds);
    }

    public function testIndicadoresAgendaFlowUsesHomePanelRbacRoute(): void
    {
        $meta = IntentManifestIndex::get('turnos.indicadores-agenda-flow');

        $this->assertIsArray($meta);
        $this->assertSame('/api/home/panel', $meta['rbac_route'] ?? null);
    }
}
