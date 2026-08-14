<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\AssistantShortcutGroupLabels;
use common\components\Platform\Assistant\Catalog\AssistantShortcutsRbacGrouper;

class AssistantShortcutsRbacGrouperTest extends Unit
{
    protected function _after(): void
    {
        AssistantShortcutGroupLabels::resetCacheForTests();
    }

    public function testGroupsByIntentIdPrefix(): void
    {
        $flows = [
            $this->flow('urgencias.ver-tablero-guardia'),
            $this->flow('urgencias.triage-paciente-guardia'),
            $this->flow('internacion.ingreso-flow'),
        ];

        $cats = AssistantShortcutsRbacGrouper::buildCategoryDefinitions($flows);
        $byId = [];
        foreach ($cats as $cat) {
            $byId[$cat['id']] = $cat['intent_ids'];
        }

        $this->assertSame(
            ['urgencias.triage-paciente-guardia', 'urgencias.ver-tablero-guardia'],
            $byId['urgencias']
        );
        $this->assertSame(['internacion.ingreso-flow'], $byId['internacion']);
    }

    public function testAdministrativoOnlySeesGrantedIntents(): void
    {
        $flows = [
            $this->flow('urgencias.ver-tablero-guardia'),
            $this->flow('condicion-laboral.editar-staff'),
        ];

        $cats = AssistantShortcutsRbacGrouper::buildCategoryDefinitions($flows);
        $ids = array_column($cats, 'id');

        $this->assertContains('urgencias', $ids);
        $this->assertContains('condicion-laboral', $ids);
        $this->assertNotContains('profesional-agenda', $ids);
    }

    public function testHiddenShortcutIsOmitted(): void
    {
        $flows = [
            $this->flow('urgencias.ver-tablero-guardia'),
            [
                'action_id' => 'profesional-agenda.configurar-propio',
                'shortcut_hidden' => true,
            ],
            [
                'action_id' => 'profesional-cobertura.gestionar-propio',
                'shortcut_hidden' => true,
            ],
            [
                'action_id' => 'profesional-agenda.configurar-staff',
                'shortcut_hidden' => true,
            ],
            [
                'action_id' => 'profesional-cobertura.gestionar-staff',
                'shortcut_hidden' => true,
            ],
            $this->flow('profesional-horarios.gestionar-propio'),
        ];

        $cats = AssistantShortcutsRbacGrouper::buildCategoryDefinitions($flows);
        $intentIds = [];
        foreach ($cats as $cat) {
            foreach ($cat['intent_ids'] as $id) {
                $intentIds[] = $id;
            }
        }

        $this->assertContains('profesional-horarios.gestionar-propio', $intentIds);
        $this->assertNotContains('profesional-agenda.configurar-propio', $intentIds);
        $this->assertNotContains('profesional-cobertura.gestionar-propio', $intentIds);
        $this->assertNotContains('profesional-agenda.configurar-staff', $intentIds);
        $this->assertNotContains('profesional-cobertura.gestionar-staff', $intentIds);
    }

    public function testGroupIdFromIntentId(): void
    {
        $this->assertSame('turnos', AssistantShortcutsRbacGrouper::groupIdFromIntentId('turnos.ver-mis-turnos-como-paciente'));
        $this->assertSame('data-access', AssistantShortcutsRbacGrouper::groupIdFromIntentId('data-access.listar'));
    }

    /**
     * @return array<string, mixed>
     */
    private function flow(string $intentId): array
    {
        return [
            'action_id' => $intentId,
            'shortcut_hidden' => false,
        ];
    }
}
