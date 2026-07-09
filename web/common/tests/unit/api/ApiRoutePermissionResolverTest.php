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

    public function testCheckedRoutesIncludeHttpAndControllerPaths(): void
    {
        $routes = ApiRoutePermissionResolver::checkedRoutesForAction(
            'api/v1/clinical/care-plans/adherencia-resumen-staff',
            'v1/clinical/care-plan/adherencia-resumen-staff'
        );

        $this->assertContains('/api/clinical/care-plans/adherencia-resumen-staff', $routes);
        $this->assertContains('/api/clinical/care-plan/adherencia-resumen-staff', $routes);
    }

    public function testCheckedRoutesForAsistenteAlias(): void
    {
        $routes = ApiRoutePermissionResolver::checkedRoutesForAction(
            'api/v1/asistente/enviar',
            'v1/chat/recibir'
        );

        $this->assertContains('/api/asistente/enviar', $routes);
        $this->assertContains('/api/chat/recibir', $routes);
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

    public function testCandidatesIncludeEncounterGuardarAlternates(): void
    {
        $candidates = ApiRoutePermissionResolver::candidates('/api/clinical/encounter/guardar');

        $this->assertContains('/api/clinical/encounter/guardar', $candidates);
        $this->assertContains('/api/clinical/encounter/analizar', $candidates);
        $this->assertContains('/api/consulta/guardar', $candidates);
        $this->assertContains('/api/consulta/analizar', $candidates);
    }
}
