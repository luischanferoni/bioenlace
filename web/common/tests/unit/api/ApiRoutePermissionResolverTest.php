<?php

namespace common\tests\unit\api;

use common\components\Core\Permission\ApiRoutePermissionResolver;
use Codeception\Test\Unit;

final class ApiRoutePermissionResolverTest extends Unit
{
    public function testCandidatesNormalizeVersionAndIndex(): void
    {
        $candidates = ApiRoutePermissionResolver::candidates('/api/info/index');
        $this->assertContains('/api/info/index', $candidates);
        $this->assertContains('/api/info', $candidates);
    }

    public function testCandidatesKeepApiPath(): void
    {
        $candidates = ApiRoutePermissionResolver::candidates('/api/listar/index');
        $this->assertSame(['/api/listar/index', '/api/listar'], $candidates);
    }
}
