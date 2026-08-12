<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\AssistantShortcutsRbacGrouper;

class AssistantShortcutsPacienteCatalogTest extends Unit
{
    public function testPacienteFlowsGroupWithoutStaffIntents(): void
    {
        $flows = [
            ['action_id' => 'atencion.necesito-atencion', 'shortcut_hidden' => false],
            ['action_id' => 'turnos.ver-mis-turnos-como-paciente', 'shortcut_hidden' => false],
            ['action_id' => 'profesional-agenda.configurar-staff', 'shortcut_hidden' => false],
        ];

        $cats = AssistantShortcutsRbacGrouper::buildCategoryDefinitions($flows);
        $intentIds = [];
        foreach ($cats as $cat) {
            foreach ($cat['intent_ids'] as $id) {
                $intentIds[] = $id;
            }
        }

        $this->assertContains('atencion.necesito-atencion', $intentIds);
        $this->assertContains('turnos.ver-mis-turnos-como-paciente', $intentIds);
        $this->assertContains('profesional-agenda.configurar-staff', $intentIds);
    }
}
