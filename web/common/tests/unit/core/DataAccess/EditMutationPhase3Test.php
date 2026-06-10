<?php

namespace common\tests\unit\core\DataAccess;

use Codeception\Test\Unit;
use common\components\Core\DataAccess\AttributeGroupCatalog;
use common\components\Core\DataAccess\Edit\EditMutationAuthorizationService;
use common\components\Core\DataAccess\Edit\OpenUiEditMutationDelegate;
use common\components\Core\DataAccess\PermissionContext;

class EditMutationPhase3Test extends Unit
{
    protected function _after(): void
    {
        AttributeGroupCatalog::resetCacheForTests();
    }

    public function testEntityGroupAttributesFromCatalog(): void
    {
        $catalog = new AttributeGroupCatalog();
        $attrs = $catalog->getEntityGroupAttributes('Persona.identidad_basica');
        $this->assertContains('nombre', $attrs);
        $this->assertContains('apellido', $attrs);
    }

    public function testMutationAuthRejectsUnknownFieldForMedico(): void
    {
        $auth = new EditMutationAuthorizationService();
        $ctx = new PermissionContext(1, ['Medico']);
        $aspectDef = [
            'kind' => 'scalar_group',
            'attribute_group' => 'Persona.identidad_basica',
            'fields' => ['nombre', 'apellido', 'otro_nombre', 'otro_apellido'],
        ];

        $this->expectException(\yii\web\ForbiddenHttpException::class);
        $auth->assertCanApplyScalarChanges(
            $ctx,
            'profesional_en_efector',
            'identidad',
            $aspectDef,
            ['id_efector' => '1'],
            ['nombre' => 'Nuevo']
        );
    }

    public function testOpenUiDelegateBuildsAgendaAction(): void
    {
        $delegate = new OpenUiEditMutationDelegate();
        $action = $delegate->buildAction('agenda_horarios', [
            'ui_action' => 'profesional-agenda.configurar-agenda',
            'requires_params' => ['id_profesional_efector_servicio', 'id_servicio'],
        ], [
            'id_profesional_efector_servicio' => '42',
            'id_servicio' => '7',
        ]);

        $this->assertSame('agenda_horarios', $action['aspect_id']);
        $this->assertSame('profesional-agenda.configurar-agenda', $action['action_id']);
        $this->assertSame('42', $action['params']['id_profesional_efector_servicio']);
        $this->assertSame('7', $action['params']['id_servicio']);
    }
}
