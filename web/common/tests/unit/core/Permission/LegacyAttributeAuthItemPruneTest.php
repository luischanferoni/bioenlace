<?php

namespace common\tests\unit\core\Permission;

use Codeception\Test\Unit;
use common\components\Platform\Core\Permission\CatalogPermissionSyncService;
use common\components\Platform\Core\Permission\PermissionCatalogService;

class LegacyAttributeAuthItemPruneTest extends Unit
{
    public function testLegacyAttributeKeyPattern(): void
    {
        $this->assertTrue(
            PermissionCatalogService::isLegacyAttributePermissionKey('Persona.nombre.edit')
        );
        $this->assertFalse(
            PermissionCatalogService::isLegacyAttributePermissionKey('condicion-laboral.editar-propio')
        );
    }

    public function testPruneDryRunWithoutDbReturnsGracefullyOrCandidates(): void
    {
        $result = (new CatalogPermissionSyncService())->pruneLegacyAttributeAuthItems(true);
        $this->assertTrue($result['dry_run']);
        $this->assertIsArray($result['candidates']);
        $this->assertSame(0, $result['removed']);
    }
}
