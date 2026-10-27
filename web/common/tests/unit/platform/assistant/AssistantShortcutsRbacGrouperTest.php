<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\AssistantShortcutGroupLabels;
use common\components\Platform\Assistant\Catalog\AssistantShortcutGrouping;
use common\components\Platform\Assistant\Catalog\AssistantShortcutsRbacGrouper;

class AssistantShortcutsRbacGrouperTest extends Unit
{
    protected function _after(): void
    {
        AssistantShortcutGroupLabels::resetCacheForTests();
        AssistantShortcutGrouping::resetCacheForTests();
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
        // `condicion-laboral.editar-staff` está mapeado al grupo de gestión de profesionales.
        $this->assertContains('profesionales-efector', $ids);
        $this->assertNotContains('profesional-agenda', $ids);
    }

    public function testManagementIntentsCollapseIntoOneCategoryWithSubgroups(): void
    {
        $flows = [
            $this->flow('profesionales.listado-efector'),
            $this->flow('profesionales.conteo-efector'),
            $this->flow('profesional-efector-servicio.crear-flow'),
            $this->flow('profesional-efector-servicio.baja-flow'),
            $this->flow('profesional-identidad.editar-staff'),
            $this->flow('condicion-laboral.editar-staff'),
            $this->flow('licencia.cargar-para-profesional-flow'),
        ];

        $cats = AssistantShortcutsRbacGrouper::buildCategoryDefinitions($flows);

        $this->assertCount(1, $cats);
        $this->assertSame('profesionales-efector', $cats[0]['id']);
        $this->assertSame('Profesionales del efector', $cats[0]['titulo']);

        $subgroups = [];
        foreach ($cats[0]['subgroups'] as $sg) {
            $subgroups[$sg['id']] = $sg['intent_ids'];
        }
        $this->assertSame(
            ['profesionales.listado-efector', 'profesionales.conteo-efector'],
            $subgroups['padron']
        );
        $this->assertSame(
            ['profesional-efector-servicio.crear-flow', 'profesional-efector-servicio.baja-flow'],
            $subgroups['altas-bajas']
        );
        $this->assertSame(
            ['profesional-identidad.editar-staff', 'condicion-laboral.editar-staff'],
            $subgroups['datos-laborales']
        );
        $this->assertSame(['licencia.cargar-para-profesional-flow'], $subgroups['licencias']);

        // `intent_ids` de la categoría contiene todo, para clientes que no renderizan subgrupos.
        $this->assertCount(7, $cats[0]['intent_ids']);
    }

    public function testSelfServiceIntentsGoToMiTrabajo(): void
    {
        $flows = [
            $this->flow('condicion-laboral.editar-propio'),
            $this->flow('licencia.cargar-como-profesional-flow'),
            $this->flow('profesional-horarios.gestionar-propio'),
        ];

        $cats = AssistantShortcutsRbacGrouper::buildCategoryDefinitions($flows);

        $this->assertCount(1, $cats);
        $this->assertSame('mi-trabajo', $cats[0]['id']);
        $this->assertSame('Mi trabajo', $cats[0]['titulo']);
        $this->assertSame([], $cats[0]['subgroups']);
    }

    public function testUnmappedIntentsKeepFamilyGrouping(): void
    {
        $flows = [
            $this->flow('internacion.mapa-camas-flow'),
            $this->flow('turnos.indicadores-agenda-flow'),
            $this->flow('condicion-laboral.editar-staff'),
        ];

        $cats = AssistantShortcutsRbacGrouper::buildCategoryDefinitions($flows);
        $ids = array_column($cats, 'id');

        // Primero las categorías declaradas en la metadata, después las familias.
        $this->assertSame('profesionales-efector', $ids[0]);
        $this->assertContains('internacion', $ids);
        $this->assertContains('turnos', $ids);
    }

    public function testNotGrantedIntentsAreNotRendered(): void
    {
        $flows = [$this->flow('profesionales.listado-efector')];

        $cats = AssistantShortcutsRbacGrouper::buildCategoryDefinitions($flows);

        $this->assertCount(1, $cats);
        $this->assertSame(['profesionales.listado-efector'], $cats[0]['intent_ids']);
        $this->assertCount(1, $cats[0]['subgroups']);
        $this->assertSame('padron', $cats[0]['subgroups'][0]['id']);
    }

    public function testHiddenShortcutIsOmitted(): void
    {
        $flows = [
            $this->flow('urgencias.ver-tablero-guardia'),
            [
                'action_id' => 'profesional-agenda.configurar-staff',
                'shortcut_hidden' => true,
            ],
            [
                'action_id' => 'profesional-horarios.gestionar-staff',
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
        $this->assertNotContains('profesional-agenda.configurar-staff', $intentIds);
        $this->assertNotContains('profesional-horarios.gestionar-staff', $intentIds);
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
