<?php

namespace common\tests\unit\core\DataAccess;

use Codeception\Test\Unit;
use common\components\Core\DataAccess\AttributeGroupCatalog;
use common\components\Core\DataAccess\AttributePermissionEvaluator;
use common\components\Core\DataAccess\PermissionContext;
use common\components\Core\DataAccess\QueryOperation;

class EditSurfaceAuthorizationTest extends Unit
{
    protected function _after(): void
    {
        AttributeGroupCatalog::resetCacheForTests();
    }

    public function testEditSurfaceRegisteredInCatalog(): void
    {
        $catalog = new AttributeGroupCatalog();
        $surface = $catalog->getEditSurface('ProfesionalEfectorServicio');
        $this->assertIsArray($surface);
        $this->assertArrayHasKey('aspects', $surface);
        $this->assertArrayHasKey('apellido', $surface['aspects']);
    }

    public function testAdminEfectorHasWriteOnPilotGroups(): void
    {
        $eval = new AttributePermissionEvaluator();
        $ctx = new PermissionContext(1, ['AdminEfector']);
        $this->assertTrue($eval->can($ctx, 'Persona.identidad_basica', QueryOperation::WRITE));
        $this->assertTrue($eval->can($ctx, 'ProfesionalEfectorServicio.asignacion', QueryOperation::WRITE));
    }

    public function testMedicoHasNoWriteOnPilotGroups(): void
    {
        $eval = new AttributePermissionEvaluator();
        $ctx = new PermissionContext(1, ['Medico']);
        $this->assertFalse($eval->can($ctx, 'Persona.identidad_basica', QueryOperation::WRITE));
        $this->assertFalse($eval->can($ctx, 'ProfesionalEfectorServicio.asignacion', QueryOperation::WRITE));
    }

    public function testWriteOperationIsRegistered(): void
    {
        $this->assertTrue(QueryOperation::isValid(QueryOperation::WRITE));
        $this->assertContains(QueryOperation::WRITE, QueryOperation::all());
    }
}
