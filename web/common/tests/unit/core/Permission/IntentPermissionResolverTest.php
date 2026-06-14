<?php

namespace common\tests\unit\core\Permission;

use common\components\Platform\Core\Permission\IntentPermissionResolver;
use Codeception\Test\Unit;

class IntentPermissionResolverTest extends Unit
{
    public function testResolveReturnsIntentId(): void
    {
        $key = IntentPermissionResolver::resolve('atencion.mis-atenciones-como-paciente', []);
        $this->assertSame('atencion.mis-atenciones-como-paciente', $key);
    }

    public function testResolveIgnoresLegacyPermissionField(): void
    {
        $key = IntentPermissionResolver::resolve('turnos.crear-como-paciente', [
            'permission' => 'Turno.create',
            'rbac_route' => '/api/turnos/crear-como-paciente',
        ]);
        $this->assertSame('turnos.crear-como-paciente', $key);
    }
}
