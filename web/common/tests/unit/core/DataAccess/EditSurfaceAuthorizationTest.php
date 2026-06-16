<?php

namespace common\tests\unit\core\DataAccess;

use Codeception\Test\Unit;
use common\components\Platform\Core\DataAccess\AttributeGroupCatalog;
use common\components\Platform\Core\DataAccess\QueryOperation;

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

    public function testEntityGroupScopeFromYaml(): void
    {
        $catalog = new AttributeGroupCatalog();
        $scope = $catalog->getEntityGroupScopeChecker('ProfesionalEfectorServicio.asignacion');
        if ($scope === null) {
            $this->markTestSkipped('ProfesionalEfectorServicio.yaml sin groups.scope_checker.');
        }
        $this->assertSame('efector_sesion', $scope);
    }

    public function testWriteOperationIsRegistered(): void
    {
        $this->assertTrue(QueryOperation::isValid(QueryOperation::WRITE));
        $this->assertContains(QueryOperation::WRITE, QueryOperation::all());
    }
}
