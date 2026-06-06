<?php

namespace common\tests\unit\api;

use Codeception\Test\Unit;
use frontend\modules\api\v1\components\ApiGhostAccessControl;

/**
 * Rutas RBAC vs uniqueId Yii (actionIndex).
 */
final class ApiGhostAccessControlRouteTest extends Unit
{
    public function testInfoIndexMapsToApiInfoPermission(): void
    {
        $candidates = ApiGhostAccessControl::permissionRouteCandidates('/api/info/index');

        $this->assertContains('/api/info/index', $candidates);
        $this->assertContains('/api/info', $candidates);
    }

    public function testListarIndexMapsToApiListarPermission(): void
    {
        $candidates = ApiGhostAccessControl::permissionRouteCandidates('/api/listar/index');

        $this->assertContains('/api/listar/index', $candidates);
        $this->assertContains('/api/listar', $candidates);
    }
}
