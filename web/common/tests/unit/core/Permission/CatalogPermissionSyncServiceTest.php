<?php

namespace common\tests\unit\core\Permission;

use Codeception\Test\Unit;
use common\components\Core\Permission\CatalogPermissionSyncService;

/**
 * Requiere BD Yii configurada (codeception/module-yii2).
 */
class CatalogPermissionSyncServiceTest extends Unit
{
    public function testResolveRoleNamesWithAccessToItemReturnsEmptyForBlank(): void
    {
        $this->assertSame([], (new CatalogPermissionSyncService())->resolveRoleNamesWithAccessToItem(''));
    }
}
