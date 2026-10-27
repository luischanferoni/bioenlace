<?php

namespace common\tests\unit\platform\core\Permission;

use Codeception\Test\Unit;
use common\components\Platform\Core\Permission\OperationalRoleManifest;

class OperationalRoleManifestTest extends Unit
{
    protected function _after(): void
    {
        OperationalRoleManifest::resetCacheForTests();
    }

    public function testLoadsCanonicalRolesFromMetadata(): void
    {
        $manifest = new OperationalRoleManifest();
        $roles = $manifest->canonicalRoles();

        $this->assertContains('medico', $roles);
        $this->assertContains('enfermeria', $roles);
        $this->assertContains('admin_efector', $roles);
        $this->assertContains('admin_ministerio', $roles);
        $this->assertNotContains('paciente', $roles);
    }

    public function testEveryAliasPointsToADeclaredCanonicalRole(): void
    {
        $manifest = new OperationalRoleManifest();
        $roles = $manifest->canonicalRoles();

        foreach ($manifest->aliases() as $alias => $canonical) {
            $this->assertContains(
                $canonical,
                $roles,
                sprintf('El alias %s apunta a %s, que no está declarado en roles.', $alias, $canonical)
            );
        }
    }

    public function testPrecedenceOnlyDeclaresKnownRoles(): void
    {
        $manifest = new OperationalRoleManifest();
        $roles = $manifest->canonicalRoles();

        foreach ($manifest->precedence() as $canonical) {
            $this->assertContains($canonical, $roles);
        }
    }

    public function testScheduleRequirementIsPerRole(): void
    {
        $manifest = new OperationalRoleManifest();

        $this->assertTrue($manifest->requiresActiveSchedule('enfermeria'));
        $this->assertTrue($manifest->requiresActiveSchedule('medico'));
        $this->assertFalse($manifest->requiresActiveSchedule('administrativo'));
        // Sin rol resuelto se mantiene el comportamiento histórico.
        $this->assertTrue($manifest->requiresActiveSchedule(null));
    }

    public function testNetworkPanelIsPerRole(): void
    {
        $manifest = new OperationalRoleManifest();

        $this->assertTrue($manifest->belongsToNetworkPanel('admin_ministerio'));
        $this->assertTrue($manifest->belongsToNetworkPanel('nivel_central'));
        $this->assertFalse($manifest->belongsToNetworkPanel('admin_efector'));
        $this->assertFalse($manifest->belongsToNetworkPanel(null));
    }

    public function testHomeNavLabelPerRole(): void
    {
        $manifest = new OperationalRoleManifest();

        $this->assertSame('Gestión', $manifest->homeNavLabel('admin_efector'));
        $this->assertSame('Red', $manifest->homeNavLabel('admin_ministerio'));
        $this->assertSame('Pacientes', $manifest->homeNavLabel('medico'));
        $this->assertSame('Pacientes', $manifest->homeNavLabel('enfermeria'));
        $this->assertSame('Pacientes', $manifest->homeNavLabel(null));
    }

    public function testCanonicalForNormalizesKnownVariants(): void
    {
        $manifest = new OperationalRoleManifest();

        $this->assertSame('medico', $manifest->canonicalFor('Medico'));
        $this->assertSame('medico', $manifest->canonicalFor(' medico '));
        $this->assertSame('enfermeria', $manifest->canonicalFor('enfermeria'));
        $this->assertSame('enfermeria', $manifest->canonicalFor('Enfermeria'));
        $this->assertSame('admin_efector', $manifest->canonicalFor('AdminEfector'));
        $this->assertSame('nivel_central', $manifest->canonicalFor('nivelCentral'));
    }

    public function testCanonicalForReturnsNullForUnknownOrBlank(): void
    {
        $manifest = new OperationalRoleManifest();

        $this->assertNull($manifest->canonicalFor(''));
        $this->assertNull($manifest->canonicalFor('   '));
        $this->assertNull($manifest->canonicalFor('paciente'));
        $this->assertNull($manifest->canonicalFor('rolInventado'));
    }

    public function testSelectReturnsNullWithoutKnownRoles(): void
    {
        $manifest = new OperationalRoleManifest();

        $this->assertNull($manifest->select([]));
        $this->assertNull($manifest->select(['paciente', 'rolInventado']));
    }

    public function testSelectPrefersManagementRoleOverClinicalRole(): void
    {
        $manifest = new OperationalRoleManifest();

        $this->assertSame('admin_efector', $manifest->select(['Medico', 'AdminEfector']));
        $this->assertSame('admin_efector', $manifest->select(['AdminEfector', 'Medico']));
        $this->assertSame('admin_ministerio', $manifest->select(['nivelCentral', 'AdminMinisterio']));
        $this->assertSame('medico', $manifest->select(['Medico']));
        $this->assertSame('enfermeria', $manifest->select(['enfermeria']));
    }

    public function testSelectIsTheDefaultWhenNoServiceIsSelected(): void
    {
        $manifest = new OperationalRoleManifest();

        // `select` sólo se usa como valor por defecto (wizard post-login): el servicio elegido
        // en sesión siempre gana antes de llegar acá.
        $this->assertSame('enfermeria', $manifest->select(['enfermeria', 'Medico']));
    }

    public function testSelectAllOrdersByPrecedence(): void
    {
        $manifest = new OperationalRoleManifest();

        $this->assertSame(
            ['admin_efector', 'medico'],
            $manifest->selectAll(['Medico', 'AdminEfector'])
        );
        $this->assertSame([], $manifest->selectAll(['paciente']));
    }
}
