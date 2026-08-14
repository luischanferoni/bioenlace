<?php

namespace common\tests\unit\core\Permission;

use Codeception\Test\Unit;
use common\components\Platform\Core\Permission\CapabilityAccessService;
use common\components\Platform\Core\Permission\CapabilityManifestIndex;

class CapabilityManifestIndexTest extends Unit
{
    protected function _before(): void
    {
        CapabilityManifestIndex::resetCacheForTests();
    }

    public function testLoadsGuardiaCapabilities(): void
    {
        $all = CapabilityManifestIndex::all();
        $this->assertArrayHasKey('guardia.ingreso', $all);
        $this->assertArrayHasKey('guardia.triage', $all);
        $this->assertArrayHasKey('guardia.retiro_administrativo', $all);

        $ingresoRoutes = CapabilityManifestIndex::routesForCapability('guardia.ingreso');
        $this->assertContains('/api/clinical/emergency-guardia/ingresar', $ingresoRoutes);

        $adminRoles = CapabilityManifestIndex::defaultRolesForCapability('guardia.ingreso');
        $this->assertContains('Administrativo', $adminRoles);

        $retiroRoles = CapabilityManifestIndex::defaultRolesForCapability('guardia.retiro_administrativo');
        $this->assertContains('Administrativo', $retiroRoles);
        $this->assertNotContains('Medico', $retiroRoles);

        $this->assertArrayHasKey('encounter.documentar_nota', $all);
        $docRoutes = CapabilityManifestIndex::routesForCapability('encounter.documentar_nota');
        $this->assertContains('/api/clinical/encounter/captura-guardar', $docRoutes);
    }

    public function testUserCannotExecuteBlankCapability(): void
    {
        $this->assertFalse(CapabilityAccessService::userCanExecuteCapability(1, ''));
        $this->assertFalse(CapabilityAccessService::userCanExecuteCapability(0, 'guardia.ingreso'));
    }

    public function testPermissionCatalogFindsCapabilityRow(): void
    {
        $catalog = new \common\components\Platform\Core\Permission\PermissionCatalogService();
        $this->assertTrue($catalog->isCapabilityPermissionKey('guardia.ingreso'));
        $this->assertTrue($catalog->isCatalogPermissionKey('guardia.ingreso'));
        $this->assertFalse($catalog->isIntentPermissionKey('guardia.ingreso'));

        $row = $catalog->findPermissionRow('guardia.ingreso');
        $this->assertNotNull($row);
        $this->assertSame('capability', $row['kind']);
        $this->assertSame('guardia.ingreso', $row['key']);

        $manifest = $catalog->buildCapabilityManifest('guardia.ingreso');
        $this->assertNotNull($manifest);
        $this->assertSame('guardia.ingreso', $manifest['capability_id']);
        $this->assertNotEmpty($manifest['routes']);
    }

    public function testLegacyPermissionAliases(): void
    {
        $all = \common\components\Platform\Core\Permission\LegacyPermissionAliasIndex::all();
        $this->assertArrayHasKey('analisis', $all);
        $this->assertSame('encounter.capturar', $all['analisis']['replacement_capability']);
        $this->assertArrayHasKey('front_ver_historial_paciente', $all);

        $deprecated = (new \common\components\Platform\Core\Permission\PermissionCatalogService())->listDeprecatedPermissions();
        $this->assertNotEmpty($deprecated);
    }
}
