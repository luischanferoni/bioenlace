<?php

namespace common\tests\unit\core\DataAccess;

use Codeception\Test\Unit;
use common\components\Platform\Core\DataAccess\AttributeGroupCatalog;
use common\components\Platform\Core\DataAccess\Edit\EditCatalogFormFieldBuilder;
use common\components\Platform\Core\DataAccess\Edit\EditMutationAuthorizationService;
use common\components\Platform\Core\DataAccess\Edit\OpenUiEditMutationDelegate;
use common\components\Platform\Core\DataAccess\PermissionContext;

class EditMutationPhase3Test extends Unit
{
    protected function _after(): void
    {
        AttributeGroupCatalog::resetCacheForTests();
    }

    public function testEntityGroupAttributesFromCatalog(): void
    {
        $catalog = new AttributeGroupCatalog();
        $identidad = $catalog->getEntityGroupAttributes('Persona.identidad_basica');
        if ($identidad !== []) {
            $this->assertContains('nombre', $identidad);
            $this->assertContains('apellido', $identidad);
        } else {
            $asignacion = $catalog->getEntityGroupAttributes('ProfesionalEfectorServicio.asignacion');
            $this->assertContains('id_efector', $asignacion);
        }
    }

    public function testMutationAuthRejectsUnknownFieldForMedico(): void
    {
        $auth = new EditMutationAuthorizationService();
        $ctx = new PermissionContext(1, ['Medico']);
        $aspectDef = [
            'kind' => 'field_group',
            'attribute_group' => 'Persona.identidad_basica',
            'fields' => ['apellido'],
        ];

        $this->expectException(\yii\web\ForbiddenHttpException::class);
        $auth->assertCanApplyScalarChanges(
            $ctx,
            'ProfesionalEfectorServicio',
            'apellido',
            $aspectDef,
            ['id_efector' => '1'],
            ['nombre' => 'Nuevo']
        );
    }

    public function testAgendaHorariosCatalogDefinesFormFieldsFromDatabase(): void
    {
        $catalog = new AttributeGroupCatalog();
        $defs = $catalog->getEntityGroupFieldDefinitions('ProfesionalEfectorServicioAgenda.configuracion');
        if ($defs === []) {
            $this->markTestSkipped('Ejecutá la migración data_access_attribute_field.');
        }
        $this->assertSame('date', $defs['vigente_desde']['type'] ?? null);
        $this->assertSame('weekly_scheduler', $defs['weekly_scheduler_widget']['widget_id'] ?? null);
        $this->assertContains('lunes_2', $defs['weekly_scheduler_widget']['value_fields'] ?? []);
    }

    public function testCatalogFormFieldBuilderMapsTypesToUiJson(): void
    {
        $catalog = new AttributeGroupCatalog();
        $defs = $catalog->getEntityGroupFieldDefinitions('ProfesionalEfectorServicioAgenda.configuracion');
        if ($defs === []) {
            $this->markTestSkipped('Ejecutá la migración data_access_attribute_field.');
        }
        $builder = new EditCatalogFormFieldBuilder($catalog);
        $aspectDef = [
            'attribute_group' => 'ProfesionalEfectorServicioAgenda.configuracion',
            'fields' => ['vigente_desde', 'intervalo_minutos', 'weekly_scheduler_widget'],
        ];
        $fields = $builder->buildUiFieldsForAspect('weekly_scheduler_widget', $aspectDef, [
            'vigente_desde' => '2026-06-01',
            'lunes_2' => '08:00-12:00',
        ], [
            'id_efector' => '1',
            'id_profesional_efector_servicio' => '42',
            'id_servicio' => '7',
        ]);
        $byName = [];
        foreach ($fields as $field) {
            $byName[$field['name']] = $field;
        }
        $this->assertSame('date', $byName['vigente_desde']['type'] ?? null);
        $this->assertSame('select', $byName['intervalo_minutos']['type'] ?? null);
        $this->assertSame('custom_widget', $byName['weekly_scheduler_widget']['type'] ?? null);
        $this->assertArrayNotHasKey('lunes_2', $byName);
    }

    public function testOpenUiDelegateBuildsAgendaAction(): void
    {
        $delegate = new OpenUiEditMutationDelegate();
        $action = $delegate->buildAction('weekly_scheduler_widget', [
            'ui_action' => 'profesional-agenda.configurar-agenda',
            'requires_params' => ['id_profesional_efector_servicio', 'id_servicio'],
            'fields' => ['vigente_desde', 'intervalo_minutos'],
            'ui_flow' => ['impact_preview_policy' => 'when_existing_agenda'],
        ], [
            'id_profesional_efector_servicio' => '42',
            'id_servicio' => '7',
        ]);

        $this->assertSame('weekly_scheduler_widget', $action['aspect_id']);
        $this->assertSame('profesional-agenda.configurar-agenda', $action['action_id']);
        $this->assertSame('42', $action['params']['id_profesional_efector_servicio']);
        $this->assertSame('7', $action['params']['id_servicio']);
        $this->assertSame('vigente_desde,intervalo_minutos', $action['params']['fields']);
        $this->assertSame('when_existing_agenda', $action['params']['impact_preview_policy']);
    }
}
