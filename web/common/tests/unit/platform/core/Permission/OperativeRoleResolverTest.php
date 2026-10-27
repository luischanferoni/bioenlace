<?php

namespace common\tests\unit\platform\core\Permission;

use Codeception\Test\Unit;
use common\components\Platform\Core\Permission\OperationalRoleManifest;
use common\components\Platform\Core\Permission\OperativeRoleResolver;

class OperativeRoleResolverTest extends Unit
{
    protected function _after(): void
    {
        OperationalRoleManifest::resetCacheForTests();
    }

    public function testGuestHasNoOperativeRole(): void
    {
        $resolver = new OperativeRoleResolver(new OperationalRoleManifest());

        $this->assertNull($resolver->resolve());
        $this->assertSame([], $resolver->resolveAll());
    }

    public function testSelectedServiceRoleWinsOverRbacRoles(): void
    {
        $resolver = new OperativeRoleResolver(new OperationalRoleManifest());

        // El usuario eligió el servicio de enfermería al iniciar sesión y además tiene el rol Medico.
        $this->assertSame('enfermeria', $resolver->resolveFor('enfermeria', ['Medico']));
        $this->assertSame('admin_efector', $resolver->resolveFor('AdminEfector', ['Medico', 'Enfermeria']));
    }

    public function testRbacRolesOnlyApplyWhenNoServiceIsSelected(): void
    {
        $resolver = new OperativeRoleResolver(new OperationalRoleManifest());

        $this->assertSame('admin_efector', $resolver->resolveFor(null, ['Medico', 'AdminEfector']));
        $this->assertSame('medico', $resolver->resolveFor(null, ['Medico']));
        $this->assertNull($resolver->resolveFor(null, []));
        $this->assertNull($resolver->resolveFor(null, ['paciente']));
    }

    public function testUnknownServiceFallsBackToRbacDefault(): void
    {
        $resolver = new OperativeRoleResolver(new OperationalRoleManifest());

        $this->assertSame('medico', $resolver->resolveFor('ServicioSinRolDeclarado', ['Medico']));
    }

    public function testResolverUsesTheSharedRoleManifest(): void
    {
        $manifest = new OperationalRoleManifest();
        $resolver = new OperativeRoleResolver($manifest);

        // Sin sesión staff no hay servicio ni roles: el resolver delega en el manifest y devuelve null.
        $this->assertNull($resolver->resolve());
    }
}
