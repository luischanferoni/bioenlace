<?php

namespace common\tests\unit\api;

use common\components\Platform\Core\Permission\ApiRoutePermissionResolver;
use Codeception\Test\Unit;

final class ApiRoutePermissionResolverTest extends Unit
{
    public function testPermissionRouteFromHttpPathUsesPublicUrl(): void
    {
        $route = ApiRoutePermissionResolver::permissionRouteFromHttpPath(
            'api/v1/clinical/care-plans/adherencia-resumen-staff'
        );

        $this->assertSame('/api/clinical/care-plans/adherencia-resumen-staff', $route);
    }

    public function testResolveCheckedRoutePrefersHttpPathOverControllerUniqueId(): void
    {
        $route = ApiRoutePermissionResolver::resolveCheckedRouteForAction(
            'api/v1/clinical/care-plans/adherencia-resumen-staff',
            'v1/clinical/care-plan/adherencia-resumen-staff'
        );

        $this->assertSame('/api/clinical/care-plans/adherencia-resumen-staff', $route);
    }

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
