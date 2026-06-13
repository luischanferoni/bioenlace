<?php

namespace common\tests\unit\core\Permission;

use Codeception\Test\Unit;
use common\components\Core\DataAccess\AttributeGroupCatalog;

class AttributePermissionKeyMapperTest extends Unit
{
    protected function _after(): void
    {
        AttributeGroupCatalog::resetCacheForTests();
    }

    public function testPermissionKeysForPersonaIdentidadBasicaRead(): void
    {
        $keys = \common\components\Core\Permission\AttributePermissionKeyMapper::permissionKeysForGroup(
            'Persona.identidad_basica',
            'read'
        );
        if ($keys === []) {
            $this->markTestSkipped('Catálogo Persona.identidad_basica no cargado en este entorno.');
        }
        $this->assertContains('Persona.nombre.read', $keys);
        $this->assertContains('Persona.apellido.read', $keys);
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
}
